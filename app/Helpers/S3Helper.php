<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class S3Helper
{
    protected static function baseUrl(): string
    {
        return config('services.supabase.url') . '/storage/v1';
    }

    protected static function apiKey(): string
    {
        return config('services.supabase.key');
    }

    protected static function bucket(): string
    {
        return config('services.supabase.bucket');
    }

    /*
    |--------------------------------------------------------------------------
    | TEMP STORAGE (LOCAL)
    |--------------------------------------------------------------------------
    */

    public static function storeFileTemp(UploadedFile $file): string
    {
        $uuid = (string) Str::uuid();
        $mime = $file->getMimeType();

        $isImage = str_starts_with($mime, 'image/');

        $extension = $file->getClientOriginalExtension();

        if (!$isImage) {
            $fileName = "{$uuid}.{$extension}";
            Storage::disk('local')->putFileAs('temp', $file, $fileName);

            return $fileName;
        }

        $fileName = "{$uuid}.webp";
        $tempPath = storage_path("app/temp/{$fileName}");

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = imagecreatefromjpeg($file->getRealPath());
                break;

            case 'image/png':
                $image = imagecreatefrompng($file->getRealPath());
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;

            case 'image/gif':
                $image = imagecreatefromgif($file->getRealPath());
                break;

            case 'image/webp':
                Storage::disk('local')->putFileAs('temp', $file, $fileName);
                return $fileName;

            default:
                $fileName = "{$uuid}.{$extension}";
                Storage::disk('local')->putFileAs('temp', $file, $fileName);
                return $fileName;
        }

        imagewebp($image, $tempPath, 80);
        imagedestroy($image);

        Storage::disk('local')->put(
            "temp/{$fileName}",
            file_get_contents($tempPath)
        );

        unlink($tempPath);
        return $fileName;
    }

    public static function getFileTemp(string $fileName): ?string
    {
        $path = "temp/{$fileName}";

        if (!Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->get($path);
    }

    public static function removeFileTemp(string $fileName): bool
    {
        $path = "temp/{$fileName}";

        return Storage::disk('local')->exists($path)
            ? Storage::disk('local')->delete($path)
            : false;
    }

    /*
    |--------------------------------------------------------------------------
    | SUPABASE STORAGE
    |--------------------------------------------------------------------------
    */

    public static function storeFileToS3(string $path, string $fileName): string
    {
        $localPath = "temp/{$fileName}";

        if (!Storage::disk('local')->exists($localPath)) {
            throw new \Exception("Temp file not found: {$fileName}");
        }

        $fileContent = Storage::disk('local')->get($localPath);

        $supabasePath = trim($path, '/') . '/' . $fileName;

        $response = Http::withHeaders([
            'apikey'        => self::apiKey(),
            'Authorization' => 'Bearer ' . self::apiKey(),
        ])->attach(
            'file',
            $fileContent,
            $fileName
        )->post(self::baseUrl() . "/object/" . self::bucket() . "/" . $supabasePath);

        if (!$response->successful()) {
            throw new \Exception("Upload failed: " . $response->body());
        }

        return $supabasePath;
    }

    public static function getUrlFileS3(string $path, string $fileName): string
    {
        $supabasePath = trim($path, '/') . '/' . $fileName;

        return config('services.supabase.url') .
            "/storage/v1/object/public/" .
            self::bucket() . "/" . $supabasePath;
    }

    /**
     * Hapus file dari Supabase Storage.
     * $relativePath adalah path relatif setelah bucket, misal: "scans/{ticketId}/file.webp"
     */
    public static function deleteFile(string $relativePath): bool
    {
        $relativePath = trim($relativePath, '/');

        $response = Http::withHeaders([
            'apikey'        => self::apiKey(),
            'Authorization' => 'Bearer ' . self::apiKey(),
        ])->delete(self::baseUrl() . "/object/" . self::bucket() . "/" . $relativePath);

        return $response->successful();
    }

    /**
     * Ekstrak relative path dari full Supabase URL.
     * Contoh: "https://xxx.supabase.co/storage/v1/object/public/bucket/scans/id/file.webp"
     *   → return "scans/id/file.webp"
     * Return null jika URL bukan dari Supabase kita.
     */
    public static function extractRelativePath(?string $fullUrl): ?string
    {
        if (empty($fullUrl)) {
            return null;
        }

        $baseUrl = config('services.supabase.url');
        $bucket  = self::bucket();

        $prefix = rtrim($baseUrl, '/') . "/storage/v1/object/public/" . $bucket . "/";

        if (!str_starts_with($fullUrl, $prefix)) {
            return null; // bukan URL Supabase kita
        }

        return substr($fullUrl, strlen($prefix));
    }

    public static function downloadToTemp(string $source): string
    {
        $tempDir = storage_path('app/temp');

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // 🔹 Jika URL langsung
        if (filter_var($source, FILTER_VALIDATE_URL)) {

            $response = Http::get($source);

            if (!$response->successful()) {
                throw new \Exception("Failed to download file from URL: {$source}");
            }

            $extension = pathinfo(
                parse_url($source, PHP_URL_PATH),
                PATHINFO_EXTENSION
            );

            $tempFileName = (string) Str::uuid() . ($extension ? ".{$extension}" : '');

            file_put_contents(
                "{$tempDir}/{$tempFileName}",
                $response->body()
            );

            return $tempFileName;
        }

        // 🔹 Jika dari Supabase Storage
        $fileUrl = config('services.supabase.url') .
            "/storage/v1/object/public/" .
            self::bucket() . "/" . $source;

        $response = Http::get($fileUrl);

        if (!$response->successful()) {
            throw new \Exception("File not found in Supabase: {$source}");
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION);
        $tempFileName = (string) Str::uuid() . ($extension ? ".{$extension}" : '');

        file_put_contents(
            "{$tempDir}/{$tempFileName}",
            $response->body()
        );

        return $tempFileName;
    }

    /**
 * Download gambar dari URL eksternal (misal signed URL Tokopedia),
 * lalu convert ke .webp, simpan di temp storage.
 * Return: nama file webp di temp (buat dipakai storeFileToS3).
 */
    public static function downloadAndConvertToWebp(string $url): string
    {
        $tempDir = storage_path('app/temp');

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $response = Http::timeout(15)->get($url);

        if (!$response->successful()) {
            throw new \Exception("Failed to download image from URL: {$url}");
        }

        $rawContent = $response->body();

        // Deteksi mime dari konten yang didownload (bukan dari URL, karena
        // signed URL Tokopedia gak selalu punya extension yang jelas di path-nya)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->buffer($rawContent);

        $uuid         = (string) Str::uuid();
        $rawTempPath  = "{$tempDir}/{$uuid}_raw";
        $webpFileName = "{$uuid}.webp";
        $webpTempPath = "{$tempDir}/{$webpFileName}";

        file_put_contents($rawTempPath, $rawContent);

        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = imagecreatefromjpeg($rawTempPath);
                break;

            case 'image/png':
                $image = imagecreatefrompng($rawTempPath);
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;

            case 'image/gif':
                $image = imagecreatefromgif($rawTempPath);
                break;

            case 'image/webp':
                // udah webp, langsung pakai tanpa convert ulang
                rename($rawTempPath, $webpTempPath);
                Storage::disk('local')->put(
                    "temp/{$webpFileName}",
                    file_get_contents($webpTempPath)
                );
                unlink($webpTempPath);
                return $webpFileName;

            default:
                unlink($rawTempPath);
                throw new \Exception("Unsupported image mime type: {$mime}");
        }

        imagewebp($image, $webpTempPath, 80);
        imagedestroy($image);
        unlink($rawTempPath);

        Storage::disk('local')->put(
            "temp/{$webpFileName}",
            file_get_contents($webpTempPath)
        );

        unlink($webpTempPath);

        return $webpFileName;
    }
}
