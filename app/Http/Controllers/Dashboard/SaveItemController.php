<?php

namespace App\Http\Controllers\Dashboard;

use App\Helpers\S3Helper;
use App\Http\Controllers\BaseController;
use App\Http\Requests\StoreSaveItemRequest;
use App\Models\Scan;
use App\Models\ScanSave;
use App\Services\FirebaseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaveItemController extends BaseController
{
    public function store(StoreSaveItemRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $ticketId  = $validated['ticket_id'];

            // STEP 1 — Cek Firebase
            $db = FirebaseService::database();

            $snapshot = $db->getReference('ticket-request')
                ->orderByChild('ticket_id')
                ->equalTo($ticketId)
                ->getSnapshot();

            if (!$snapshot->exists()) {
                return $this->clientError('Ticket tidak ditemukan.');
            }

            $ticketData = collect($snapshot->getValue())->first();

            if (($ticketData['status'] ?? '') !== 'success') {
                return $this->clientError('Ticket belum selesai diproses.');
            }

            // STEP 2 — Cek database
            $scan = Scan::where('ticket_id', $ticketId)->first();

            if (!$scan) {
                return $this->clientError('Scan tidak ditemukan.');
            }

            // STEP 3 — Simpan ke scan_saves per item
            $isPartial = filter_var($request->input('is_partial'), FILTER_VALIDATE_BOOLEAN); // ← harus di sini, sebelum foreach
            dd([
                'is_partial_raw'      => $request->input('is_partial'),
                'is_partial_filtered' => $isPartial,
                'type_filtered'       => gettype($isPartial),
            ]);
            foreach ($validated['items'] as $item) {
                ScanSave::create([
                    'scan_id'        => $scan->id,
                    'img_url'        => $item['img_url'],
                    'is_partial'     => $isPartial,
                    'product_name'   => $item['product_name'],
                    'price'          => $item['price'] ?? null,
                    'rating'         => $item['rating'] ?? null,
                    'count_purchase' => $item['count_purchase'] ?? null,
                    'product_url'    => $item['product_url'],
                ]);
            }

            return $this->success(null);

        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            // STEP 1 — Validasi is_partial
            if (!$request->has('is_partial') || !in_array($request->query('is_partial'), ['0', '1'])) {
                return $this->clientError('Parameter is_partial wajib diisi (0 atau 1).');
            }

            $isPartial = $request->query('is_partial') === '1';
            $userId    = $request->user()->id;

            // STEP 2 — Validasi from_date & to_date
            $fromDate = $request->query('from_date');
            $toDate   = $request->query('to_date');

            if ($fromDate) {
                // Validasi format from_date
                try {
                    $fromDate = Carbon::parse($fromDate)->startOfDay();
                } catch (\Exception $e) {
                    return $this->clientError('Format from_date tidak valid. Gunakan format YYYY-MM-DD.');
                }

                // Kalau from_date diisi, to_date wajib
                if (!$toDate) {
                    return $this->clientError('to_date wajib diisi ketika from_date diisi.');
                }

                // Validasi format to_date
                try {
                    $toDate = Carbon::parse($toDate)->endOfDay();
                } catch (\Exception $e) {
                    return $this->clientError('Format to_date tidak valid. Gunakan format YYYY-MM-DD.');
                }

                // to_date harus >= from_date
                if ($toDate->lt($fromDate)) {
                    return $this->clientError('to_date harus lebih besar atau sama dengan from_date.');
                }

                // to_date tidak boleh lebih dari hari ini
                if ($toDate->gt(Carbon::today()->endOfDay())) {
                    return $this->clientError('to_date tidak boleh lebih dari hari ini.');
                }
            }

            // STEP 3 — Ambil data
            $scans = Scan::where('user_id', $userId)
                ->whereHas('scanSaves', function ($q) use ($isPartial) {
                    $q->where('is_partial', $isPartial ? true : false);
                })
                ->with([
                    'scanResult',
                    'scanSaves' => function ($q) use ($isPartial) {
                        $q->where('is_partial', $isPartial ? true : false);
                    },
                ])
                // ✅ Filter tanggal kalau from_date & to_date diisi
                ->when($fromDate, function ($q) use ($fromDate, $toDate) {
                    $q->whereBetween('created_at', [$fromDate, $toDate]);
                })
                ->orderByDesc('id')
                ->paginate(10);

            // STEP 4 — Return null kalau kosong
            if ($scans->count() === 0) {
                return $this->success(null);
            }

            $scans->getCollection()->transform(function ($scan) {
                if (!$scan->relationLoaded('scanResult') || !$scan->scanResult) {
                    return $scan;
                }

                $imgUrls = $scan->scanResult->img_urls ?? [];

                $scan->scanResult->img_urls = collect($imgUrls)
                    ->map(function ($path) {
                        if (empty($path)) return $path;
                        if (filter_var($path, FILTER_VALIDATE_URL)) return $path;

                        $normalized = str_replace('\\', '/', $path);
                        $folder     = trim(dirname($normalized), '/');
                        $fileName   = basename($normalized);

                        if ($folder === '.' || $folder === '') return $path;

                        return S3Helper::getUrlFileS3($folder, $fileName);
                    })
                    ->values()
                    ->toArray();

                return $scan;
            });

            // STEP 5 — Return data
            return $this->success($scans);

        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}