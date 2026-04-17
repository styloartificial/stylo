<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                return response()->json([
                    'code'    => 400,
                    'message' => 'No pending ticket requests.',
                    'data'    => null,
                ], 400);
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

            $id         = $request->input('ticket_request_id');
            $storedData = $request->input('storedData'); 

            $db  = FirebaseService::database();
            $ref = $db->getReference("ticket-request/{$id}");

            $snapshot = $ref->getSnapshot();

            if (!$snapshot->exists()) {
                return response()->json([
                    'code'    => 400,
                    'message' => 'Ticket request not found.',
                    'data'    => null,
                ], 400);
            }

            $ref->update(['data' => $storedData]);
            $ref->update(['status' => 'success']);

            return $this->success(null);

        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}