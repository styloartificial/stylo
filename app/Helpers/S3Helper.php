<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class S3Helper
{
    public static function storeFileTemp(UploadedFile $file): string
    {
        $uuid = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $fileName = "{$uuid}.{$extension}";

        Storage::disk('local')->putFileAs(
            'temp',
            $file,
            $fileName
        );

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

    public static function storeFileToS3(string $path, string $fileName): string
    {
        $localPath = "temp/{$fileName}";

        if (!Storage::disk('local')->exists($localPath)) {
            throw new \Exception("Temp file not found: {$fileName}");
        }

        $s3Path = trim($path, '/') . '/' . $fileName;

        Storage::disk('s3')->put(
            $s3Path,
            Storage::disk('local')->get($localPath),
            [
                'visibility' => 'public',
                'ACL'        => 'public-read',
            ]
        );

        return $s3Path;
    }

    public static function getUrlFileS3(string $path, string $fileName): string
    {
        $s3Path = trim($path, '/') . '/' . $fileName;

        return Storage::disk('s3')->url($s3Path);
    }

    public static function downloadToTemp(string $source): string
    {
        $tempDir = storage_path('app/temp');

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

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

        if (!Storage::disk('s3')->exists($source)) {
            throw new \Exception("File not found in S3: {$source}");
        }

        $fileContents = Storage::disk('s3')->get($source);

        $extension = pathinfo($source, PATHINFO_EXTENSION);
        $tempFileName = (string) Str::uuid() . ($extension ? ".{$extension}" : '');

        file_put_contents(
            "{$tempDir}/{$tempFileName}",
            $fileContents
        );

        return $tempFileName;
    }
}
