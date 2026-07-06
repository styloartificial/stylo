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
            ⚠️ ATURAN PALING PENTING — WAJIB DIPATUHI ⚠️
            ==================================================

            1. PERTAHANKAN (tidak boleh diubah):
               - Wajah & identitas user
               - Bentuk & postur tubuh asli
               - Pose asli
               - Background & framing foto
               - Pencahayaan

            2. YANG BOLEH DIUBAH: hanya pakaian dan aksesori yang dikenakan.

            " . (!empty($scanCategoryHijab) ? "
            3. MODE HIJAB PENUH — PRIORITAS TERTINGGI:
               PASTIKAN seluruh rambut tertutup sempurna oleh hijab — tidak boleh ada helai atau garis rambut terlihat.
               PASTIKAN leher, dada, lengan (lengan panjang), dan kaki (bawahan panjang hingga mata kaki) tertutup.
               PASTIKAN siluet pakaian longgar dan sopan — tidak ketat, tidak transparan.
               PASTIKAN hanya wajah dan tangan yang terlihat.
               PASTIKAN hijab tampak realistis, natural, rapi, dan menyatu dengan gaya outfit.

            " : "
            3. MODE SOPAN:
               PASTIKAN pakaian sopan dan rapi — tidak terlalu ketat, tidak terlalu terbuka.
               PASTIKAN tidak ada area kulit sensitif yang terlihat (paha, perut, punggung).
            ") . "
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

            User telah memberikan instruksi spesifik berikut. WAJIB dipatuhi:

            \"$outfitDetail\"

            Panduan membaca instruksi user:
            ► Yang DISUKAI user → wajib diterapkan pada outfit
            ► Yang TIDAK DISUKAI user → wajib dihindari
            ► Warna spesifik yang disebut → gunakan warna tersebut
            ► Item/bahan/model spesifik → terapkan secara akurat
            ► Jika user menyebut HANYA item tertentu → hanya ubah item itu, PERTAHANKAN item lain yang ada di foto
            ► Jika user tidak menyebut item spesifik → generate outfit lengkap
            ► Jangan abaikan satu pun detail dari user

            ==================================================
            " : "") . "

            ==================================================
            PANDUAN WARNA & SILUET
            ==================================================

            - Pilih warna yang harmonis dengan warna kulit: $userSkinTone
            - Pilih siluet yang sesuai bentuk tubuh: $userBodyShape
            - Sesuaikan dengan occasion dan gaya yang dipilih
            - Outfit harus tampak realistis, tidak ada distorsi kain, tangan/wajah tidak cacat

            ==================================================
            ATURAN PER ITEM
            ==================================================

            - Atasan  → hapus & ganti atasan saja
            - Bawahan → hapus & ganti bawahan saja
            - Outer   → tambahkan outer yang sesuai
            - Hijab   → aktifkan MODE HIJAB PENUH (lihat aturan di atas)

            ==================================================
            LANGKAH SEBELUM OUTPUT (PIKIRKAN DULU)
            ==================================================

            Sebelum menulis JSON output, analisis secara berurutan:
            1. Profil user → warna kulit, bentuk tubuh, proporsi tinggi-berat
            2. Palet warna terbaik untuk profil tersebut
            3. Siluet & potongan yang paling cocok dengan bentuk tubuh
            4. Pastikan SEMUA aturan hijab/sopan di atas terpenuhi (jika berlaku)
            5. Pastikan SEMUA preferensi user terakomodasi
            6. Baru tulis JSON output

            ==================================================
            OUTPUT — HANYA JSON VALID (tanpa teks/tanda lain)
            ==================================================

            Untuk setiap produk, berikan data SPESIFIK dan ACTIONABLE — bayangkan kamu menulis search query untuk marketplace (Tokopedia, Shopee, Zalora).

            Field 'summary' WAJIB:
            - Analisis singkat profil user & alasan outfit cocok
            - Gunakan sudut pandang orang kedua (kamu, tubuhmu, kulitmu)
            - Jelaskan KENAPA outfit ini cocok, bukan WHAT yang dipakai
            - Hubungkan pilihan outfit dengan warna kulit, bentuk tubuh, dan occasion
            - DILARANG menyebutkan item tanpa alasan
            - Contoh tone yang BENAR:
              \"Warna kulitmu yang sangat terang sangat cocok dipadukan dengan warna coklat hangat seperti ini — warna earth tone terbukti menonjolkan kecerahan kulit tanpa terlihat pucat. Potongan mock neck pada blouse ini juga mempertegas bentuk tubuh hourglass-mu secara elegan tanpa terlalu mencolok, sangat pas untuk suasana kantor yang profesional.\"

            Field 'visual_prompt' WAJIB:
            - Ditulis dalam BAHASA INGGRIS
            - Berisi deskripsi VISUAL pakaian yang detail dan presisi untuk image generation
            - Sebutkan: warna spesifik, bahan, potongan/cut, panjang, detail visual
            - Jika user mengisi outfit_detail → jadikan sebagai acuan utama, tapi TETAP sesuaikan warna & siluet dengan profil user
            - Contoh: \"oversized off-white linen shirt with rolled sleeves, wide-leg beige trousers with a relaxed fit, minimal clean look, earth tone palette, casual chic style\"

            Field 'products' WAJIB:
            - Semua produk WAJIB sesuai gender: $userGender
            " . ($userGender == "MALE"
                ? "- GUNAKAN terminologi produk pria: kemeja pria, blazer pria, celana chino pria, dst.\n            - DILARANG produk wanita (blouse, rok, dress, gamis, hijab, dll)"
                : "- GUNAKAN terminologi produk wanita: blouse, rok, dress, celana kulot wanita, dst.\n            - DILARANG produk laki-laki") . "

            {
            \"title\": \"judul outfit yang catchy dan deskriptif\",
            \"summary\": \"satu paragraf rekomendasi mengalir seperti contoh di atas\",
            \"visual_prompt\": \"deskripsi visual outfit dalam BAHASA INGGRIS untuk image generation\",
            \"products\": [
                {
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
