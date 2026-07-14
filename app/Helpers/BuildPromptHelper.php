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

        $selectedItems = array_filter(array_map('trim', explode(',', strtolower($scanCategoryItems ?? ''))));

        // FIX: cek spesifik "hijab", bukan cuma !empty (karena ada pilihan "Non Hijab" yang juga type=hijab)
        $isHijabSelected = strtolower(trim($scanCategoryHijab ?? '')) === 'hijab';

        $itemRulesMap = [
            'atasan'     => "- Atasan: hapus & ganti atasan saja",
            'bawahan'    => "- Bawahan: hapus & ganti bawahan saja",
            'outer'      => "- Outer: tambahkan outer yang sesuai",
            'dress'      => "- Dress: hapus & ganti dengan dress lain",
            'gamis'      => "- Gamis: hapus & ganti dengan gamis lain",
            'sepatu'     => "- Sepatu: hapus & ganti sepatu saja",
            'aksesories' => "- Aksesories: tambahkan/ganti aksesori yang sesuai",
        ];
        $itemRules = [];
        foreach ($itemRulesMap as $key => $rule) {
            if (in_array($key, $selectedItems)) $itemRules[] = $rule;
        }
        if ($isHijabSelected) $itemRules[] = "- Hijab: aktifkan mode hijab penuh (lihat aturan di bawah)";

        $aturanPerItemSection = !empty($itemRules) ? "

        ATURAN PER ITEM — hanya item ini yang boleh diubah, item lain WAJIB tetap seperti foto asli:
        " . implode("\n", $itemRules) . "
        " : "";

        $modeHijabSection = $isHijabSelected ? "

        MODE HIJAB (wajib!):
        - Tutup SELURUH rambut secara realistis — tidak boleh ada helai atau garis rambut terlihat sama sekali
        - Foto asli belum berhijab → buat hijab baru yang menutup 100% rambut. Foto asli sudah berhijab → pertahankan, cukup upgrade stylenya
        - Tutup juga leher & dada, lengan panjang, bawahan panjang sampai mata kaki, siluet longgar dan sopan
        - Dilarang keras: pakaian ketat/transparan, crop top, rok/celana pendek, tanpa lengan, kulit terlihat selain wajah dan tangan
        - Hasil akhir harus rapi, natural, dan menyatu dengan warna & gaya outfit
        " : "

        MODE NON-HIJAB (wajib!):
        - Jangan tambahkan hijab dalam bentuk apapun, rambut asli WAJIB tetap terlihat apa adanya
        - Kalau foto asli sudah berhijab (bukan dari pilihan kategori ini), pertahankan apa adanya, jangan dilepas atau diubah gayanya
        ";

        $prompt = "
        Kamu adalah AI fashion stylist dan virtual try-on expert.

        TUGAS: Ganti HANYA outfit pada foto sesuai preferensi user. Pertahankan wajah & identitas, bentuk & postur tubuh, pose, background, framing, dan pencahayaan — jangan diubah sama sekali. Yang boleh diubah hanya pakaian dan aksesori sesuai kategori yang dipilih.

        PROFIL USER
        - Gender: $userGender
        - Tinggi: $userHeight cm, Berat: $userWeight kg
        - Warna kulit: $userSkinTone
        - Bentuk tubuh: $userBodyShape

        PREFERENSI OUTFIT
        " . (!empty($scanCategoryItems)    ? "- Item: $scanCategoryItems\n"    : "")
        . (!empty($scanCategoryOccasion)   ? "- Occasion: $scanCategoryOccasion\n" : "")
        . (!empty($scanCategoryStyle)      ? "- Gaya: $scanCategoryStyle\n"    : "")
        . (!empty($scanCategoryHijab)      ? "- Hijab: $scanCategoryHijab\n"    : "")
        . ($outfitDetail ? "
        DETAIL DARI USER (prioritas tinggi, wajib dipatuhi!): \"$outfitDetail\"
        - Sesuatu yang disukai user → wajib diterapkan. Yang tidak disukai → wajib dihindari
        - Warna/bahan/item spesifik yang disebut → gunakan persis, jangan tambahkan elemen lain yang tidak disebut
        " : "")
        . $aturanPerItemSection
        . $modeHijabSection . "

        WARNA & FIT: pilih warna yang cocok dengan warna kulit ($userSkinTone) dan siluet yang sesuai bentuk tubuh ($userBodyShape), selaraskan dengan occasion dan gaya. Outfit harus tampak realistis di tubuh — tanpa distorsi kain, tangan/wajah tidak cacat, tidak ada anggota tubuh terpotong.

        OUTPUT — JSON valid saja, tanpa teks tambahan apapun di luar JSON.

        summary: satu paragraf mengalir, sudut pandang kedua (kamu/tubuhmu/kulitmu). Jelaskan KENAPA outfit ini cocok untuk profil user (warna kulit, bentuk tubuh, proporsi tinggi-berat, occasion) — bukan sekadar sebutkan item yang dipakai. Sertakan tips styling singkat. Contoh tone: \"Warna kulitmu yang sangat terang sangat cocok dipadukan dengan warna coklat hangat seperti ini — earth tone terbukti menonjolkan kecerahan kulit tanpa terlihat pucat. Potongan mock neck pada blouse ini mempertegas bentuk tubuh hourglass-mu secara elegan, pas untuk suasana kantor yang profesional. Padukan dengan rok pensil hitam agar lekuk pinggangmu makin terdefinisi.\"

        visual_prompt: bahasa Inggris, deskripsi visual detail (warna spesifik, bahan, potongan/cut, panjang) HANYA untuk item kategori yang dipilih — jangan deskripsikan item lain. Dasarkan pada outfit_detail user kalau ada; kalau kosong, tentukan sendiri berdasarkan item, style, occasion, dan profil user. Contoh: \"oversized off-white linen shirt with rolled sleeves, wide-leg beige trousers with a relaxed fit, minimal clean look, earth tone palette, casual chic style\"

        products: semua produk wajib sesuai gender user ($userGender). " . ($userGender == "MALE"
            ? "Dilarang merekomendasikan produk perempuan (blouse, rok, dress, gamis, hijab, dll), gunakan terminologi produk pria: kemeja pria, blazer pria, celana chino pria, dst."
            : "Dilarang merekomendasikan produk laki-laki, gunakan terminologi produk wanita: blouse, rok, dress, celana kulot wanita, dst.") . " Tiap produk isi name (gender + jenis item + bahan/material + model/cut + warna spesifik, contoh: " . ($userGender == "MALE" ? "kemeja flannel slim fit lengan panjang navy blue pria" : "blouse linen oversized lengan panjang putih tulang wanita") . "), brand (isi 'unbranded' kalau tidak spesifik), dan category.

        {
        \"title\": \"judul outfit\",
        \"summary\": \"...\",
        \"visual_prompt\": \"...\",
        \"products\": [
            {
            \"name\": \"...\",
            \"brand\": \"...\",
            \"category\": \"...\"
            }
        ]
        }
        ";

        $imagesUrl = [
            $scan->img_url,
            $userDetail->img_url ?? null,
        ];

        try {
            FirebaseLogHelper::logPromptSent($db, $ticketId);
            $isHijab = $isHijabSelected;

            \Illuminate\Support\Facades\Log::info("[BUILD PROMPT HELPER] Payload to BytePlusService", [
                // 'prompt' => $prompt,
                'images_url' => $imagesUrl,
                'generate_images' => 3,
                'is_hijab' => $isHijab,
            ]);

            $result = ByteplusService::run($prompt, $imagesUrl, 3, $isHijab);

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
            \Illuminate\Support\Facades\Log::error("Error in BuildPromptHelper: " . $e->getMessage(), [
                'stack' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
