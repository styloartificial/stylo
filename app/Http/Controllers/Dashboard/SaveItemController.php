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
use Illuminate\Support\Facades\DB;

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

            $isPartial = filter_var($request->input('is_partial'), FILTER_VALIDATE_BOOLEAN);

            foreach ($validated['items'] as $item) {
                ScanSave::create([
                    'scan_id'        => $scan->id,
                    'img_url'        => $item['img_url'],
                    'is_partial'     => DB::raw($isPartial ? 'TRUE' : 'FALSE'),
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
                try {
                    $fromDate = Carbon::parse($fromDate)->startOfDay();
                } catch (\Exception $e) {
                    return $this->clientError('Format from_date tidak valid. Gunakan format YYYY-MM-DD.');
                }

                if (!$toDate) {
                    return $this->clientError('to_date wajib diisi ketika from_date diisi.');
                }

                try {
                    $toDate = Carbon::parse($toDate)->endOfDay();
                } catch (\Exception $e) {
                    return $this->clientError('Format to_date tidak valid. Gunakan format YYYY-MM-DD.');
                }

                if ($toDate->lt($fromDate)) {
                    return $this->clientError('to_date harus lebih besar atau sama dengan from_date.');
                }

                if ($toDate->gt(Carbon::today()->endOfDay())) {
                    return $this->clientError('to_date tidak boleh lebih dari hari ini.');
                }
            }

            // STEP 3 — Ambil data
            $scans = Scan::where('user_id', $userId)
                ->whereHas('scanSaves', function ($q) use ($isPartial) {
                    $q->whereRaw('is_partial IS ' . ($isPartial ? 'TRUE' : 'FALSE'));
                })
                ->with([
                    'scanResult',
                    'scanSaves' => function ($q) use ($isPartial) {
                        $q->whereRaw('is_partial IS ' . ($isPartial ? 'TRUE' : 'FALSE'));
                    },
                ])
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

            return $this->success($scans);
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }

    /**
     * Hapus seluruh outfit (scan_saves is_partial = FALSE) milik scan ini.
     * Single items (is_partial = TRUE) TIDAK ikut terhapus.
     */
    public function destroy(int $scanId): JsonResponse
    {
        try {
            $userId = request()->user()->id;

            $scan = Scan::where('id', $scanId)
                        ->where('user_id', $userId)
                        ->first();

            if (!$scan) {
                return $this->clientError('Data tidak ditemukan.', 404);
            }

            // ✅ Hanya hapus is_partial = FALSE (outfit), single items tetap aman
            $deleted = $scan->scanSaves()
                            ->whereRaw('is_partial IS FALSE')
                            ->delete();

            if ($deleted === 0) {
                return $this->clientError('Tidak ada outfit yang ditemukan untuk dihapus.');
            }

            return $this->success(null, 'Outfit berhasil dihapus.');
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }

    /**
     * Hapus satu single item (scan_save is_partial = TRUE) berdasarkan saveId.
     * Outfit (is_partial = FALSE) TIDAK ikut terhapus.
     */
    public function destroySingle(int $scanId, int $saveId): JsonResponse
    {
        try {
            $userId = request()->user()->id;

            // Pastikan scan milik user ini
            $scan = Scan::where('id', $scanId)
                        ->where('user_id', $userId)
                        ->first();

            if (!$scan) {
                return $this->clientError('Data tidak ditemukan.', 404);
            }

            // Cari & hapus single item spesifik yang is_partial = TRUE
            $deleted = $scan->scanSaves()
                            ->whereRaw('is_partial IS TRUE')
                            ->where('id', $saveId)
                            ->delete();

            if ($deleted === 0) {
                return $this->clientError('Single item tidak ditemukan.', 404);
            }

            return $this->success(null, 'Single item berhasil dihapus.');
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
    
    public function show(int $scanId): JsonResponse
    {
        try {
            $userId = request()->user()->id;

            $scan = Scan::where('id', $scanId)
                ->where('user_id', $userId)
                ->with(['scanResult', 'scanSaves'])
                ->first();

            if (!$scan) {
                return $this->clientError('Data tidak ditemukan.', 404);
            }

            // Sama persis dengan transform di index
            if ($scan->relationLoaded('scanResult') && $scan->scanResult) {
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
            }

            return $this->success($scan);
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
