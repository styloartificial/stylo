<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\FirebaseLogHelper;
use App\Models\Scan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScraperController extends BaseController
{
    public function getOldestTicketRequest(): JsonResponse
    {
        try {
            $db = FirebaseService::database();

            $snapshot = $db
                ->getReference('ticket-request')
                ->orderByChild('created_at')
                ->getSnapshot();

            if (!$snapshot->exists()) {
                return response()->json([
                    'code'    => 400,
                    'message' => 'No ticket requests found.',
                    'data'    => null,
                ], 400);
            }

            $oldest = null;

            foreach ($snapshot->getValue() as $id => $data) {
                if (isset($data['status']) && $data['status'] === 'pending') {
                    if ($oldest === null || $data['created_at'] < $oldest['created_at']) {
                        $oldest = array_merge($data, ['id' => $id]);
                    }
                }
            }

            if ($oldest === null) {
                return $this->success(null);
            }

            return $this->success($oldest);
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }


    public function setDoneTicketRequest(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ticket_request_id' => 'required|string',
                'storedData'        => 'required|array',
            ]);

            $ticketId  = $request->input('ticket_request_id');
            $storedData = $request->input('storedData');

            $db = FirebaseService::database();

            // 🔍 Cari data berdasarkan ticket_id
            $query = $db->getReference('ticket-request')
                ->orderByChild('ticket_id')
                ->equalTo($ticketId)
                ->getSnapshot();

            if (!$query->exists()) {
                return response()->json([
                    'code'    => 400,
                    'message' => 'Ticket request not found.',
                    'data'    => null,
                ], 400);
            }

            // 📌 Ambil hasil query
            $result = $query->getValue();

            // Ambil push key pertama
            $key = array_key_first($result);

            if (!$key) {
                return response()->json([
                    'code'    => 400,
                    'message' => 'Invalid ticket data.',
                    'data'    => null,
                ], 400);
            }

            // 🔗 Reference ke node spesifik
            $ref = $db->getReference("ticket-request/{$key}");

            // 🔄 Ambil Scan dari database lokal — dipindah ke atas (sebelum $ref->update)
            // karena sekarang dibutuhin buat cocokin category sebelum ditulis ke Firebase.
            $scan = Scan::where('ticket_id', $ticketId)->first();

            // ← BARU: sisipin `category` ke tiap grup di $storedData, berdasarkan
            // mapping "query → category" yang udah disimpen di $scan->product_categories
            // (diisi di ProcessGetRecommendationStyle.php). Query yang dicocokin adalah
            // field `product` di tiap grup, karena itu teks query yang persis sama
            // yang dikirim ke scraper sebelumnya.
            $categoryMap = $scan->product_categories ?? [];

            $storedDataWithCategory = collect($storedData)
                ->map(function ($group) use ($categoryMap) {
                    $query = $group['product'] ?? null;
                    $group['category'] = $query ? ($categoryMap[$query] ?? null) : null;
                    return $group;
                })
                ->toArray();

            Log::info("=== CATEGORY MATCHING ===", [
                'ticket_id'    => $ticketId,
                'category_map' => $categoryMap,
                'result'       => $storedDataWithCategory,
            ]);

            // 📝 Update data (pakai $storedDataWithCategory, bukan $storedData mentah lagi)
            $ref->update([
                'data'   => $storedDataWithCategory,
                'status' => 'success',
            ]);

            // 🔄 Update status Scan di database lokal
            if ($scan) {
                $scan->status = "COMPLETED";
                $scan->save();
            }

            // 🔁 Ambil ulang data terbaru (optional tapi aman)
            $updatedSnapshot = $ref->getSnapshot();
            $ticketData = $updatedSnapshot->getValue();

            $ticketIdFromDb = $ticketData['ticket_id'] ?? null;

            // 📊 Logging ke Firebase
            if ($ticketIdFromDb) {
                FirebaseLogHelper::logScrapProcess($db, $ticketIdFromDb);
                FirebaseLogHelper::logGenerationCompleted($db, $ticketIdFromDb);
            }

            Log::info("Ticket request with ID {$ticketId} has been marked as done and logged.");
            $response = Http::timeout(10)
                ->withHeaders([
                    'x-secret-key' => config('services.scraper.secret_key'),
                ])
                ->post(
                    'https://scraper.styloartificial.my.id/api/remove-to-queue-scraper',
                    [
                        'ticket_id' => $ticketId,
                    ]
                );

            Log::info([
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'code'    => 200,
                'message' => 'Success',
                'data'    => null,
            ], 200);
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\Log::error("Ada error!");
            \Illuminate\Support\Facades\Log::error($th);
            return response()->json([
                'code'    => 500,
                'message' => $th->getMessage(),
                'data'    => null,
            ], 500);
        }
    }

    public function searchProducts(Request $request)
    {
        try {
            $request->validate([
                'product_name' => 'required|string',
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'x-secret-key' => config('services.scraper.secret_key'),
                ])
                ->get('https://scraper.styloartificial.my.id/api/search-products', [
                    'search_query' => $request->input('product_name')
                ]);

            if ($response->failed()) {
                return $this->clientError(
                    "Failed to search products. Status: {$response->status()}, Body: {$response->body()}"
                );
            }

            return $this->success($response->json());
        } catch (\Throwable $th) {
            Log::error("Ada error!");
            Log::error($th);

            return $this->serverError($th);
        }
    }
}
