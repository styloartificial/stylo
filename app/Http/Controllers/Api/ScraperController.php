<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\FirebaseLogHelper;

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

            // 📝 Update data
            $ref->update([
                'data'   => $storedData,
                'status' => 'success',
            ]);

            // 🔁 Ambil ulang data terbaru (optional tapi aman)
            $updatedSnapshot = $ref->getSnapshot();
            $ticketData = $updatedSnapshot->getValue();

            $ticketIdFromDb = $ticketData['ticket_id'] ?? null;

            // 📊 Logging ke Firebase
            if ($ticketIdFromDb) {
                FirebaseLogHelper::logScrapProcess($db, $ticketIdFromDb);
                FirebaseLogHelper::logGenerationCompleted($db, $ticketIdFromDb);
            }

            return response()->json([
                'code'    => 200,
                'message' => 'Success',
                'data'    => null,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'code'    => 500,
                'message' => $th->getMessage(),
                'data'    => null,
            ], 500);
        }
    }
}