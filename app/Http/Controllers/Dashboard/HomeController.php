<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\BaseController;
use App\Models\Scan;
use App\Models\ScanSave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends BaseController
{
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
                        $q->where('is_partial', false);
                    })
                    ->with([
                        'scanResult',
                        'scanSaves' => function ($q) {
                            $q->where('is_partial', false);
                        },
                    ])
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get();

                return $this->success($scans);
            }

            // STEP 3 — Single items
            $scanSaves = ScanSave::whereHas('scan', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->where('is_partial', true)
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            return $this->success($scanSaves);

        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}