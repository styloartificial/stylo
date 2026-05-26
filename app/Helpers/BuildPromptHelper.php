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
            Kamu adalah AI fashion stylist dan AI virtual try-on expert.

            Saya akan memberikan foto seseorang beserta preferensi outfit mereka.

            TUGAS UTAMA:
            1. Analisis foto orang secara detail:
            - wajah
            - postur tubuh
            - ukuran tubuh
            - warna kulit
            - proporsi tubuh

            2. Ganti outfit lama dengan outfit BARU sesuai kategori yang dipilih user.

            3. Pertahankan IDENTITAS asli orang:
            - wajah TIDAK berubah
            - bentuk tubuh TIDAK berubah
            - pose TIDAK berubah
            - background TIDAK berubah
            - framing foto TIDAK berubah

            4. HANYA outfit/pakaian yang boleh berubah.

            ==================================================
            PROFIL USER
            ==================================================

            - Gender: $userGender
            - Tinggi: $userHeight cm
            - Berat: $userWeight kg
            - Skin tone: $userSkinTone
            - Body shape: $userBodyShape

            ==================================================
            PREFERENSI OUTFIT
            ==================================================

            " . (!empty($scanCategoryItems)   ? "- Item outfit: $scanCategoryItems\n" : "") . "
            " . (!empty($scanCategoryOccasion) ? "- Occasion/acara: $scanCategoryOccasion\n" : "") . "
            " . (!empty($scanCategoryStyle)    ? "- Style/gaya: $scanCategoryStyle\n" : "") . "
            " . (!empty($scanCategoryHijab)    ? "- Style hijab: $scanCategoryHijab\n" : "") . "
            " . ($outfitDetail ? "- Detail tambahan user: $outfitDetail\n" : "") . "

            ==================================================
            ATURAN WAJIB
            ==================================================

            - Wajah HARUS sama dengan foto asli
            - Bentuk tubuh HARUS sama
            - Background HARUS sama
            - Foto HARUS realistis
            - Outfit baru HARUS natural dan menyatu dengan tubuh asli
            - Jangan membuat deformasi tangan, wajah, atau kain
            - Jangan crop tubuh
            - Jangan mengubah pencahayaan secara ekstrem

            ==================================================
            ATURAN KHUSUS ITEM
            ==================================================

            - Jika item 'Atasan' dipilih:
            hanya ganti atasan.

            - Jika item 'Bawahan' dipilih:
            hanya ganti bawahan.

            - Jika item 'Outer' dipilih:
            tambahkan outer yang cocok.

            - Jika item 'Hijab' dipilih:
            AKTIFKAN MODE MODEST / FULL COVER HIJAB OUTFIT.

            ==================================================
            ATURAN SUPER PENTING UNTUK HIJAB
            ==================================================

            Jika user memilih kategori HIJAB, maka WAJIB:

            - Seluruh rambut HARUS tertutup sempurna
            - Tidak boleh ada rambut terlihat
            - Leher HARUS tertutup
            - Dada HARUS tertutup
            - Lengan HARUS panjang
            - Bawahan HARUS panjang sampai mata kaki
            - Outfit HARUS longgar dan sopan
            - Tidak boleh pakaian ketat
            - Tidak boleh crop top
            - Tidak boleh rok pendek
            - Tidak boleh celana pendek
            - Tidak boleh sleeveless
            - Tidak boleh transparan
            - Tidak boleh memperlihatkan kulit selain wajah dan tangan

            Jika foto asli user BELUM berhijab:
            - ubah outfit menjadi fully modest hijab fashion
            - tambahkan hijab yang natural dan realistis
            - rambut asli jangan terlihat sama sekali

            Jika foto asli user SUDAH berhijab:
            - pertahankan penggunaan hijab
            - upgrade style hijab sesuai kategori yang dipilih
            - jika user memilih hijab motif, gunakan hijab motif
            - jika user memilih hijab pashmina, ubah menjadi pashmina
            - tetap pastikan seluruh aurat tertutup dengan rapi

            Style hijab harus:
            - realistis
            - rapi
            - natural
            - fashionable
            - menyatu dengan outfit

            ==================================================
            WARNA DAN STYLE
            ==================================================

            - Warna outfit HARUS cocok dengan skin tone $userSkinTone
            - Outfit HARUS cocok dengan body shape $userBodyShape
            - Outfit HARUS cocok dengan occasion yang dipilih
            - Outfit HARUS cocok dengan style yang dipilih

            ==================================================
            OUTPUT FORMAT
            ==================================================

            WAJIB output JSON VALID tanpa teks tambahan.

            {
            \"summary\": \"jelaskan outfit baru secara detail\",
            \"title\": \"judul outfit\",
            \"products\": [
                {
                \"name\": \"nama produk\",
                \"brand\": \"brand\",
                \"category\": \"kategori produk\"
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

            \Illuminate\Support\Facades\Log::info("Payload to BytePlusService", [
                'prompt' => $prompt,
                'images_url' => $imagesUrl,
            ]);
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

            $scanResult = $scan->scanResult()->create([
                'summary'  => $summary,
                'img_urls' => $summaryUrls
            ]);

            \Illuminate\Support\Facades\Log::info("=== DEBUG SCAN RESULT ===", [
                'summaryUrls'        => $summaryUrls,
                'scanResult_img_urls' => $scanResult->img_urls,
                'index_0'            => $scanResult->img_urls[0] ?? 'KOSONG',
            ]);

            $scan->title = $title;
            $scan->img_url = $scanResult->img_urls[0] ?? $scan->img_url;
            $scan->save();

            \Illuminate\Support\Facades\Log::info("=== DEBUG SCAN IMG_URL ===", [
                'scan_img_url' => $scan->img_url,
            ]);

            return $products;
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
