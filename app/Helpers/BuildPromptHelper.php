<?php

namespace App\Helpers;

use App\Services\FirebaseService;
use App\Helpers\S3Helper;
use App\Helpers\FirebaseLogHelper;
use App\Models\Scan;
use App\Services\ByteplusService;
use Illuminate\Support\Facades\Http;

class BuildPromptHelper
{
    public static function run(Scan $scan)
    {

        $db = FirebaseService::database();
        $ticketId = $scan->ticket_id;

        FirebaseLogHelper::logPromptBuild($db, $ticketId);

        $user = $scan->user;
        $userDetail = $user->userDetail;

        if (!$userDetail) {
            throw new \Exception("User detail tidak ditemukan");
        }

        if (empty($scan->img_url)) {
            throw new \Exception("Scan image wajib ada");
        }

        $userGender    = $userDetail->gender;
        $userHeight    = $userDetail->height;
        $userWeight    = $userDetail->weight;
        $userSkinTone  = $userDetail->skinTone
            ? "{$userDetail->skinTone->title} ({$userDetail->skinTone->description})"
            : "tidak diketahui";

        $userBodyShape = $userDetail->bodyShape
            ? "{$userDetail->bodyShape->title} ({$userDetail->bodyShape->description})"
            : "tidak diketahui";

        $scanCategories = collect($scan->categories);

        $scanCategoryItems   = $scanCategories->where('type', 'item')->pluck('title')->implode(', ');
        $scanCategoryOccasion = $scanCategories->where('type', 'occasion')->pluck('title')->implode(', ');
        $scanCategoryStyle   = $scanCategories->where('type', 'style')->pluck('title')->implode(', ');
        $scanCategoryHijab   = $userGender == "MALE"
            ? null
            : $scanCategories->where('type', 'hijab')->pluck('title')->implode(', ');

        $promptParts = [];

        if (!empty($scanCategoryItems))   $promptParts[] = "item: $scanCategoryItems";
        if (!empty($scanCategoryOccasion)) $promptParts[] = "occasion: $scanCategoryOccasion";
        if (!empty($scanCategoryStyle))   $promptParts[] = "style: $scanCategoryStyle";
        if (!empty($scanCategoryHijab))   $promptParts[] = "hijab: $scanCategoryHijab";

        $promptCategoryText = implode(', ', $promptParts);

        $outfitDetail = $scan->outfit_detail ?? null;

        $prompt = "
        Berdasarkan foto orang ini, analisa outfit dan buatkan rekomendasi outfit yang sesuai.

        Profil pengguna:
        - Gender: $userGender
        - Tinggi: $userHeight cm
        - Berat: $userWeight kg
        - Skin tone: $userSkinTone
        - Body shape: $userBodyShape

        Preferensi outfit:
        " . (!empty($scanCategoryItems)   ? "- Item yang difokuskan: $scanCategoryItems\n"   : "") .
        (!empty($scanCategoryOccasion) ? "- Acara/occasion: $scanCategoryOccasion\n"       : "") .
        (!empty($scanCategoryStyle)    ? "- Gaya/style yang diinginkan: $scanCategoryStyle\n" : "") .
        (!empty($scanCategoryHijab)    ? "- Pilihan hijab: $scanCategoryHijab\n"            : "") .
        ($outfitDetail                 ? "- Detail tambahan dari user: $outfitDetail\n"     : "") . "
        Gunakan semua informasi di atas untuk menghasilkan rekomendasi yang personal dan relevan.

        WAJIB ikuti format JSON ini tanpa tambahan teks lain:

        {
            \"summary\": \"string (penjelasan outfit lengkap dalam 1 paragraf, sebutkan item spesifik yang direkomendasikan)\",
            \"title\": \"string (judul singkat untuk outfit ini, didapat dari rangkuman summary)\",
            \"products\": [
                {
                \"name\": \"string (nama produk spesifik)\",
                \"brand\": \"string (brand yang direkomendasikan)\",
                \"category\": \"string (kategori produk)\"
                }
            ]
        }
        ";

        \Illuminate\Support\Facades\Log::info("prompt: {$prompt}");

        $imagesUrl = [
            $scan->img_url,
            $userDetail->img_url ?? null,
        ];

        try {
            FirebaseLogHelper::logPromptSent($db, $ticketId);

            $result = ByteplusService::run($prompt, $imagesUrl, 3);

            FirebaseLogHelper::logPromptCompleted($db, $ticketId);

            $analysis = $result['analysis'] ?? [];
            $summary  = $analysis['summary'] ?? null;
            $products = $analysis['products'] ?? [];
            $title = $analysis['title'] ?? null;

            if (empty($summary)) {
                $summary = "Tidak dapat menghasilkan summary outfit saat ini.";
            }

            $summaryUrls = [];

            if (!empty($result['images'])) {
                foreach ($result['images'] as $image) {
                    $response = Http::get($image);

                    $extension = pathinfo(parse_url($image, PHP_URL_PATH), PATHINFO_EXTENSION);
                    $tempFileName = (string) \Illuminate\Support\Str::uuid() . ($extension ? ".{$extension}" : '');

                    \Illuminate\Support\Facades\Storage::disk('local')->put("temp/{$tempFileName}", $response->body());

                    $s3Path = S3Helper::storeFileToS3(
                        "scans/{$scan->ticket_id}/summary",
                        $tempFileName
                    );

                    S3Helper::removeFileTemp($tempFileName);

                    $summaryUrls[] = $s3Path;
                }
            }

            $scan->scanResult()->create([
                'summary'  => $summary,
                'img_urls' => $summaryUrls
            ]);
            $scan->title = $title;
            $scan->save();

            return $products;
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
