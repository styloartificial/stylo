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

        $scanCategories = $scan->scanItemCategories()->with('itemCategory')->get()->map(function ($item) {
            return (object)[
                'type'  => $item->type,
                'title' => $item->itemCategory->title ?? '',
            ];
        });
        

        $scanCategoryItems   = $scanCategories->where('type', 'item')->pluck('title')->implode(', ');
        $scanCategoryOccasion = $scanCategories->where('type', 'occasion')->pluck('title')->implode(', ');
        $scanCategoryStyle   = $scanCategories->where('type', 'style')->pluck('title')->implode(', ');
        $scanCategoryHijab   = $userGender == "MALE"
            ? null
            : $scanCategories->where('type', 'hijab')->pluck('title')->implode(', ');

        \Illuminate\Support\Facades\Log::info("=== SCAN CATEGORIES ===", [
            'items'    => $scanCategoryItems,
            'occasion' => $scanCategoryOccasion,
            'style'    => $scanCategoryStyle,
            'hijab'    => $scanCategoryHijab,
        ]);
        $promptParts = [];

        if (!empty($scanCategoryItems))   $promptParts[] = "item: $scanCategoryItems";
        if (!empty($scanCategoryOccasion)) $promptParts[] = "occasion: $scanCategoryOccasion";
        if (!empty($scanCategoryStyle))   $promptParts[] = "style: $scanCategoryStyle";
        if (!empty($scanCategoryHijab))   $promptParts[] = "hijab: $scanCategoryHijab";

        $promptCategoryText = implode(', ', $promptParts);

        $outfitDetail = $scan->outfit_detail ?? null;

        $prompt = "
            Kamu adalah AI fashion stylist. Saya akan memberikan foto seseorang beserta preferensi outfit mereka.

            TUGASMU:
            1. Lihat foto orang ini dengan detail (wajah, postur, ukuran tubuh, warna kulit)
            2. Buat rekomendasi outfit BARU yang menggantikan outfit yang sedang dipakai
            3. Outfit baru harus sesuai dengan kategori dan preferensi yang dipilih
            4. Generate gambar orang yang SAMA dengan outfit baru (wajah, postur, ukuran TIDAK BOLEH berubah, hanya outfit yang diganti)

            Profil pengguna:
            - Gender: $userGender
            - Tinggi: $userHeight cm
            - Berat: $userWeight kg
            - Skin tone: $userSkinTone
            - Body shape: $userBodyShape

            Kategori outfit yang WAJIB diterapkan:
            " . (!empty($scanCategoryItems)   ? "- Item yang HARUS diganti/difokuskan: $scanCategoryItems\n"        : "") .
            (!empty($scanCategoryOccasion) ? "- Outfit HARUS cocok untuk acara: $scanCategoryOccasion\n"          : "") .
            (!empty($scanCategoryStyle)    ? "- Gaya/style yang WAJIB diterapkan: $scanCategoryStyle\n"           : "") .
            (!empty($scanCategoryHijab)    ? "- Pilihan hijab yang dipakai: $scanCategoryHijab\n"                 : "") .
            ($outfitDetail                 ? "- Permintaan spesifik dari user: $outfitDetail\n"                   : "") . "

            ATURAN PENTING untuk gambar yang di-generate:
            - Wajah orang TIDAK BOLEH berubah
            - Postur dan ukuran tubuh TIDAK BOLEH berubah  
            - Background TIDAK BOLEH berubah
            - Ukuran dan layout foto TIDAK BOLEH berubah
            - HANYA outfit/pakaian yang boleh berubah sesuai kategori di atas
            - Jika item yang dipilih adalah 'Atasan' maka HANYA atasan yang diganti
            - Jika item yang dipilih adalah 'Bawahan' maka HANYA bawahan yang diganti
            - Jika item yang dipilih adalah 'Outer' maka tambahkan outer di atas outfit yang ada
            - Jika item yang dipilih adalah 'Hijab' maka tambahkan hijab di atas outfit yang ada
            - Jika user memilih dua kategori misal atasan dan bawahan, maka ganti item yang dipilih
            - Ambil semua produk yang direkomendasikan sesuai item yang dipilih 
            - kalau memilih hijab ya tampilkan juga hijab yang direkomendasikan begitu pula dengan kategori lain
            - Warna outfit yang direkomendasikan harus cocok dengan skin tone $userSkinTone

            WAJIB ikuti format JSON ini tanpa tambahan teks lain:

            {
                \"summary\": \"string (jelaskan outfit baru yang direkomendasikan secara detail: item apa yang diganti, warna, bahan, dan alasan kenapa cocok untuk $userGender dengan body shape $userBodyShape dan acara/style yang dipilih)\",
                \"title\": \"string (judul singkat outfit rekomendasi, contoh: 'Casual Minimalist Work Look')\",
                \"products\": [
                    {
                        \"name\": \"string (nama produk spesifik yang direkomendasikan sesuai item yang dipilih)\",
                        \"brand\": \"string (brand fashion yang relevan)\",
                        \"category\": \"string (harus salah satu dari: $scanCategoryItems)\"
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
