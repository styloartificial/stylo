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
           Kamu adalah AI fashion stylist dan virtual try-on expert.

           ==================================================
           TUGAS
           ==================================================

           Dari foto yang diberikan, ganti HANYA outfit sesuai preferensi user.

           PERTAHANKAN (JANGAN diubah):
           - Wajah & identitas
           - Bentuk & postur tubuh
           - Pose
           - Background & framing foto
           - Pencahayaan

           YANG BOLEH DIUBAH: hanya pakaian dan aksesori.

           ==================================================
           PROFIL USER
           ==================================================

           - Gender      : $userGender
           - Tinggi      : $userHeight cm
           - Berat       : $userWeight kg
           - Warna kulit : $userSkinTone
           - Bentuk tubuh: $userBodyShape

           ==================================================
           PREFERENSI OUTFIT
           ==================================================
           " . (!empty($scanCategoryItems)    ? "- Item     : $scanCategoryItems\n"    : "")
            . (!empty($scanCategoryOccasion)   ? "- Occasion : $scanCategoryOccasion\n" : "")
            . (!empty($scanCategoryStyle)      ? "- Gaya     : $scanCategoryStyle\n"    : "")
            . (!empty($scanCategoryHijab)      ? "- Hijab    : $scanCategoryHijab\n"    : "")
            . ($outfitDetail ? "
           ==================================================
           ⚠️ DETAIL OUTFIT DARI USER — PRIORITAS TINGGI
           ==================================================

           User telah memberikan instruksi spesifik berikut. WAJIB dipatuhi sepenuhnya:

           \"$outfitDetail\"

           Panduan membaca instruksi user:
           ► Jika user menyebut sesuatu yang DIA SUKA     → wajib diterapkan pada outfit
           ► Jika user menyebut sesuatu yang TIDAK DIA SUKA → wajib dihindari pada outfit
           ► Jika user menyebut warna spesifik             → gunakan warna tersebut
           ► Jika user menyebut item/bahan/model spesifik  → terapkan secara akurat
           ► Jangan mengabaikan satu pun detail dari instruksi user di atas

           ==================================================
           " : "") . "

           ==================================================
           ATURAN PER ITEM
           ==================================================

           - Atasan  → hapus & ganti atasan saja
           - Bawahan → hapus &ganti bawahan saja
           - Outer   → tambahkan outer yang sesuai
           - Hijab   → aktifkan MODE HIJAB PENUH (lihat aturan di bawah)

           ==================================================
           ⚠️⚠️⚠️ MODE HIJAB — ATURAN KRITIS — WAJIB DIPATUHI TANPA PENGECUALIAN ⚠️⚠️⚠️
           ==================================================

           Jika 'Hijab' dipilih:

           PENUTUPAN RAMBUT — PRIORITAS TERTINGGI:
           ► SELURUH rambut WAJIB tertutup sempurna — tanpa pengecualian apapun
           ► Jika foto asli BELUM berhijab:
           - Buat hijab yang realistis dan menutupi 100% rambut
           - Tidak boleh ada garis rambut, helai rambut, atau tekstur rambut terlihat
           - Perlakukan rambut yang terlihat seperti aurat yang harus ditutup
           ► Jika foto asli SUDAH berhijab:
           - Pertahankan hijab, upgrade style sesuai preferensi user
           - Pastikan semua rambut tetap tertutup setelah pergantian style

           PENUTUPAN TUBUH (semua wajib tanpa terkecuali):
           ✓ Rambut  → tertutup penuh oleh hijab
           ✓ Leher   → tertutup
           ✓ Dada    → tertutup
           ✓ Lengan  → hanya lengan panjang
           ✓ Kaki    → bawahan panjang hingga mata kaki
           ✓ Siluet  → longgar dan sopan

           SANGAT DILARANG — TIDAK BOLEH ADA DALAM HASIL GAMBAR:
           ✗ Rambut atau garis rambut terlihat
           ✗ Pakaian ketat
           ✗ Crop top / rok pendek / celana pendek
           ✗ Pakaian tanpa lengan
           ✗ Kain transparan atau tembus pandang
           ✗ Kulit terlihat selain wajah dan tangan

           EKSEKUSI HIJAB:
           - Tampak realistis, natural, dan rapi
           - Modis dan sesuai dengan keseluruhan outfit
           - Menyatu dengan warna dan gaya outfit

           ==================================================
           PANDUAN WARNA & FIT
           ==================================================

           - Pilih warna yang cocok dengan warna kulit : $userSkinTone
           - Pilih siluet yang sesuai bentuk tubuh     : $userBodyShape
           - Sesuaikan dengan occasion dan gaya yang dipilih
           - Outfit harus tampak realistis di tubuh — tidak ada distorsi kain,
           tangan atau wajah tidak cacat, tidak ada anggota tubuh yang terpotong

           ==================================================
           OUTPUT — JSON VALID SAJA (tanpa teks tambahan)
           ==================================================

           Untuk setiap produk, berikan data SPESIFIK dan ACTIONABLE untuk keperluan pencarian produk nyata.
           Bayangkan kamu sedang menulis search query untuk marketplace (Tokopedia, Shopee, Zalora).

           Field 'summary' WAJIB :
           -  Analisis singkat profil user (warna kulit, bentuk tubuh, proporsi tinggi-berat)
           -  Alasan kenapa outfit yang dipilih cocok untuk profil tersebut
           -  Tips styling spesifik (mis. warna yang mempercantik kulit)
           -  Rekomendasi produk yang sesuai dengan outfit yang dihasilkan
           - Gunakan sudut pandang orang kedua (kamu, tubuhmu, kulitmu)
           - Jelaskan KENAPA outfit ini cocok untuk profil user, bukan WHAT yang dipakai
           - Hubungkan setiap pilihan outfit dengan warna kulit, bentuk tubuh, atau occasion
           - DILARANG: hanya menyebutkan item pakaian tanpa alasan
           - DILARANG: format seperti → outfit ini terdiri dari...
           - Contoh tone yang BENAR:
               \"Warna kulitmu yang sangat terang sangat cocok dipadukan dengan warna coklat hangat
               seperti ini — warna earth tone terbukti menonjolkan kecerahan kulit tanpa terlihat
               pucat. Potongan mock neck pada blouse ini juga mempertegas bentuk tubuh hourglass-mu
               secara elegan tanpa terlalu mencolok, sangat pas untuk suasana kantor yang profesional.
               Untuk memaksimalkan tampilanmu, coba padukan dengan rok pensil hitam agar lekukan
               pinggangmu makin terdefinisi dengan rapi.\"

           Field 'visual_prompt' WAJIB:
           - Ditulis dalam bahasa Inggris
           - Berisi deskripsi visual pakaian yang sangat detail dan presisi untuk keperluan image generation
           - Sebutkan: warna spesifik, bahan, potongan/cut, panjang, dan detail visual lainnya
           - Jika user mengisi outfit_detail → jadikan itu sebagai dasar utama, jangan tambahkan
           elemen yang tidak disebutkan user
           - Jika outfit_detail kosong → tentukan sendiri berdasarkan item, style, occasion,
           dan profil user
           - Contoh: \"oversized off-white linen shirt with rolled sleeves, wide-leg beige trousers
           with a relaxed fit, minimal clean look, earth tone palette, casual chic style\"

            Field 'products' WAJIB:
            - Semua produk WAJIB sesuai gender user: $userGender
            " . ($userGender == "MALE"
                ? "- DILARANG merekomendasikan produk perempuan (blouse, rok, dress, gamis, hijab, dll)\n            - Gunakan terminologi produk pria: kemeja pria, blazer pria, celana chino pria, dst"
                : "- DILARANG merekomendasikan produk laki-laki\n            - Gunakan terminologi produk wanita: blouse, rok, dress, celana kulot wanita, dst") . "


           {
           \"title\": \"judul outfit\",
           \"summary\": \"satu paragraf rekomendasi mengalir berbasis profil user seperti contoh di atas\",
           \"visual_prompt\": \"deskripsi visual outfit dalam bahasa Inggris untuk image generation\",
           \"products\": [
               {
                \"name\": \"[jenis item] [bahan/material] [model/cut] [warna spesifik] — contoh: kemeja linen oversized lengan panjang putih tulang\",
                \"name\": \"[gender: $userGender] [jenis item] [bahan/material] [model/cut] [warna spesifik] — contoh: " . ($userGender == "MALE" ? "kemeja flannel slim fit lengan panjang navy blue pria" : "blouse linen oversized lengan panjang putih tulang wanita") . "\",
               \"brand\": \"nama brand (isi 'unbranded' jika tidak spesifik)\",
               \"category\": \"kategori produk\"
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
            $isHijab = !empty($scanCategoryHijab);

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
