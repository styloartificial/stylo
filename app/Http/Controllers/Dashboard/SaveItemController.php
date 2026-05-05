<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\BaseController;
use App\Http\Requests\StoreSaveItemRequest;
use App\Models\Scan;
use App\Models\ScanSave;
use App\Services\FirebaseService;
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
            foreach ($validated['items'] as $item) {
                ScanSave::create([
                    'scan_id'        => $scan->id,
                    'img_url'        => $item['img_url'],
                    'is_partial'     => $validated['is_partial'],
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
            // STEP 1 — Validasi query param
            if (!$request->has('is_partial') || !in_array($request->query('is_partial'), ['0', '1'])) {
                return $this->clientError('Parameter is_partial wajib diisi (0 atau 1).');
            }

            $isPartial = (int) $request->query('is_partial');
            $userId    = $request->user()->id;

            // STEP 2 — Ambil data
            $scans = Scan::where('user_id', $userId)
                ->whereHas('scanSaves', function ($q) use ($isPartial) {
                    $q->where('is_partial', $isPartial);
                })
                ->with([
                    'scanResult',
                    'scanSaves' => function ($q) use ($isPartial) {
                        $q->where('is_partial', $isPartial);
                    },
                ])
                ->orderByDesc('id')
                ->paginate(10);

            // STEP 3 — Return null kalau kosong
            if ($scans->count() === 0) {
                return $this->success(null);
            }

            // STEP 4 — Return data
            return $this->success($scans);

        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
