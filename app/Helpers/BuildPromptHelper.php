<?php

namespace App\Helpers;

use App\Services\FirebaseService;
use App\Services\OpenAIService;
use App\Helpers\S3Helper;
use App\Helpers\FirebaseLogHelper;
use App\Models\Scan;

class BuildPromptHelper {
    public static function run(Scan $scan) {

        // =========================
        // INITIAL
        // =========================
        $db = FirebaseService::database();
        $ticketId = $scan->ticket_id;

        FirebaseLogHelper::logPromptBuild($db, $ticketId);

        $user = $scan->user;
        $userDetail = $user->userDetail;

        // =========================
        // VALIDATION
        // =========================
        if (!$userDetail) {
            throw new \Exception("User detail tidak ditemukan");
        }

        if (empty($scan->img_url)) {
            throw new \Exception("Scan image wajib ada");
        }

        // =========================
        // IMAGE DOWNLOAD (SAFE)
        // =========================
        $userProfileImg = !empty($userDetail->img_url)
            ? S3Helper::downloadToTemp($userDetail->img_url)
            : null;

        $scanImg = S3Helper::downloadToTemp($scan->img_url);

        $userGender    = $userDetail->gender;
        $userHeight    = $userDetail->height;
        $userWeight    = $userDetail->weight;
        $userSkinTone  = $userDetail->skinTone
            ? "{$userDetail->skinTone->title} ({$userDetail->skinTone->description})"
            : "tidak diketahui";

        // ✅ tambah body shape
        $userBodyShape = $userDetail->bodyShape
            ? "{$userDetail->bodyShape->title} ({$userDetail->bodyShape->description})"
            : "tidak diketahui";

        // =========================
        // CATEGORY PROCESSING
        // =========================
        $scanCategories = collect($scan->categories);

        $scanCategoryItems   = $scanCategories->where('type', 'item')->pluck('title')->implode(', ');
        $scanCategoryOccasion = $scanCategories->where('type', 'occasion')->pluck('title')->implode(', ');
        $scanCategoryStyle   = $scanCategories->where('type', 'style')->pluck('title')->implode(', ');
        $scanCategoryHijab   = $userGender == "MALE"
            ? null
            : $scanCategories->where('type', 'hijab')->pluck('title')->implode(', ');

        // =========================
        // BUILD PROMPT
        // =========================
        $promptParts = [];

        if (!empty($scanCategoryItems))   $promptParts[] = "item: $scanCategoryItems";
        if (!empty($scanCategoryOccasion)) $promptParts[] = "occasion: $scanCategoryOccasion";
        if (!empty($scanCategoryStyle))   $promptParts[] = "style: $scanCategoryStyle";
        if (!empty($scanCategoryHijab))   $promptParts[] = "hijab: $scanCategoryHijab";

        $promptCategoryText = implode(', ', $promptParts);

        $outfitDetail = $scan->outfit_detail ?? null;

        // ✅ tambah body shape ke prompt
        $prompt = "
        Berdasarkan foto orang ini, analisa outfit dan buatkan rekomendasi.

        Detail:
        - Gender: $userGender
        - Tinggi: $userHeight cm
        - Berat: $userWeight kg
        - Skin tone: $userSkinTone
        - Body shape: $userBodyShape
        - Preferensi: $promptCategoryText
        " . ($outfitDetail ? "- Detail tambahan dari user: $outfitDetail\n" : "") . "
        WAJIB ikuti format JSON ini tanpa tambahan teks lain:

        {
        \"summary\": \"string (penjelasan outfit lengkap dalam 1 paragraf)\",
        \"products\": [
            {
            \"name\": \"string\",
            \"brand\": \"string\",
            \"category\": \"string\"
            }
        ]
        }

        Tambahkan juga 3 gambar dengan pose berbeda tanpa mengubah wajah.
        ";

        // =========================
        // TEMP IMAGES
        // =========================
        $tempImages = array_values(array_filter([
            $userProfileImg,
            $scanImg
        ]));

        $promptBuild = [
            'prompt' => $prompt,
            'temp_images' => $tempImages,
            'generate_images' => 3
        ];

        // =========================
        // EXECUTE AI
        // =========================
        try {
            FirebaseLogHelper::logPromptSent($db, $ticketId);

            $result = OpenAIService::run($promptBuild);

            FirebaseLogHelper::logPromptCompleted($db, $ticketId);

            // =========================
            // HANDLE AI RESPONSE
            // =========================
            $analysis = $result['analysis'] ?? [];
            $summary  = $analysis['summary'] ?? null;
            $products = $analysis['products'] ?? [];

            if (empty($summary)) {
                $summary = "Tidak dapat menghasilkan summary outfit saat ini.";
            }

            // =========================
            // SAVE IMAGES
            // =========================
            $summaryUrls = [];

            if (!empty($result['images'])) {
                foreach ($result['images'] as $tempImg) {
                    $s3Path = S3Helper::storeFileToS3(
                        "scans/{$scan->ticket_id}/summary",
                        $tempImg
                    );
                    $summaryUrls[] = $s3Path;
                    S3Helper::removeFileTemp($tempImg);
                }
            }

            // =========================
            // SAVE RESULT
            // =========================
            $scan->scanResult()->create([
                'summary'  => $summary,
                'img_urls' => $summaryUrls
            ]);

            return $products;

        } catch (\Throwable $e) {
            throw $e;
        } finally {
            // =========================
            // CLEANUP
            // =========================
            if ($userProfileImg) S3Helper::removeFileTemp($userProfileImg);
            if ($scanImg) S3Helper::removeFileTemp($scanImg);
        }
    }
}