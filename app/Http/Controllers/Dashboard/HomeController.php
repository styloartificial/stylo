<?php

namespace App\Http\Controllers\Dashboard;

use App\Helpers\S3Helper;
use App\Http\Controllers\BaseController;
use App\Models\Scan;
use App\Models\ScanSave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends BaseController
{
    // ─── Helper: convert path ke full URL ────────────────────────────────────
    private function convertToUrl(?string $path): ?string
    {
        if (empty($path)) return $path;
        if (filter_var($path, FILTER_VALIDATE_URL)) return $path;

        $normalized = str_replace('\\', '/', $path);
        $folder     = trim(dirname($normalized), '/');
        $fileName   = basename($normalized);

        if ($folder === '.' || $folder === '') return $path;

        return S3Helper::getUrlFileS3($folder, $fileName);
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            // STEP 1 — Validasi query param
            $type = $request->query('type');

            if (!in_array($type, ['outfits', 'single-items'])) {
                return $this->clientError('Parameter type wajib diisi. Nilai yang diterima: outfits, single-items.');
            }

            $userId = $request->user()->id;

            // STEP 2 — Outfits
            if ($type === 'outfits') {
                $scans = Scan::where('user_id', $userId)
                    ->whereHas('scanSaves', function ($q) {
                        $q->whereRaw('is_partial IS FALSE');
                    })
                    ->with([
                        'scanResult',
                        'scanSaves' => function ($q) {
                            $q->whereRaw('is_partial IS FALSE');
                        },
                    ])
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get();

                // ✅ Convert img_url scan dan scanResult->img_urls
                $scans->transform(function ($scan) {
                    $scan->img_url = $this->convertToUrl($scan->img_url);

                    if ($scan->relationLoaded('scanResult') && $scan->scanResult) {
                        $imgUrls = $scan->scanResult->img_urls ?? [];

                        $scan->scanResult->img_urls = collect($imgUrls)
                            ->map(fn($path) => $this->convertToUrl($path))
                            ->values()
                            ->toArray();
                    }

                    return $scan;
                });

                return $this->success($scans);
            }

            // STEP 3 — Single items
            $scanSaves = ScanSave::whereHas('scan', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->whereRaw('is_partial IS TRUE')
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            return $this->success($scanSaves);

        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}