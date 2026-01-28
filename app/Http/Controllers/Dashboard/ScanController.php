<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\BaseController;
use App\Models\MScanCategory;
use App\Models\Scan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Services\FirebaseService;
use App\Helpers\S3Helper;
use App\Helpers\FirebaseLogHelper;
use App\Helpers\BuildPromptHelper;
use App\Http\Requests\OpenTicketRequest;
use Illuminate\Support\Facades\Redis;
use App\Http\Requests\LogScrapProcessRequest;

class ScanController extends BaseController
{
    public function scanCategory(): JsonResponse
    {
        $categories = MScanCategory::select('id', 'title', 'icon')->get();

        return $this->success($categories);
    }

    public function openTicket(OpenTicketRequest $request): JsonResponse
    {
        $db = FirebaseService::database();
        try {

            // STEP 1: Validasi request
            $validated = $request->validated();

            // STEP 2: Generate ticket ID
            $ticketId = (string) Str::uuid();

            // STEP 3: Simpan image ke storage/temp
            $file         = $request->file('img_url');
            $tempFileName = S3Helper::storeFileTemp($file);

            // STEP 4: Upload ke S3
            S3Helper::storeFileToS3("scans/{$ticketId}", $tempFileName);
            $imgUrl = S3Helper::getUrlFileS3("scans/{$ticketId}", $tempFileName);

            $dataScan = [
                'user_id'          => $request->user()->id,
                'ticket_id'        => $ticketId,
                'title'            => $validated['title'],
                'img_url'          => $imgUrl,
                'scan_category_id' => $validated['scan_category_id'],
            ];

            // STEP 5: Simpan data scan ke database
            $scan = Scan::create($dataScan);

            // STEP 6: Log ticket queued (Firebase)
            FirebaseLogHelper::logTicketQueued(
                $db,
                $ticketId
            );

            // STEP 7: Build prompt (generate products)
            $products = BuildPromptHelper::run($scan);

            // STEP 8: Log scrap prepared
            FirebaseLogHelper::logScrapPrepared(
                $db,
                $ticketId
            );

            // STEP 9–10: Format products
            $productsFormatted = collect($products)
                ->map(function ($item) {
                    return trim(
                        ($item['brand'] ?? '') . ' ' .
                        ($item['name'] ?? '') . ' ' .
                        ($item['color'] ?? '')
                    );
                })
                ->filter()
                ->values()
                ->toArray();

            // STEP 11: Prepare Redis payload
            $sendToRedis = [
                'ticket_id'  => $ticketId,
                'products'   => $productsFormatted,
                'created_at' => now()->toDateTimeString(),
            ];

            return $this->success($sendToRedis); // --- FOR TESTING PURPOSE ---

            // STEP 12: Log scrap queued
            FirebaseLogHelper::logScrapQueued(
                $db,
                $ticketId
            );

            // STEP 13: Push ke Redis
            Redis::rpush('scrap_queue', json_encode($sendToRedis));

            // STEP 14: Return success
            return $this->success([
                'ticket_id' => $ticketId
            ]);

        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function logScrapProcess(LogScrapProcessRequest $request): JsonResponse
    {
        try {
            // STEP 1: Ambil ticket_id dari request
            $ticketId = $request->input('ticket_id');


            // STEP 2: Ambil database Firebase
            $db = FirebaseService::database();

            // STEP 3: Log scrap process ke Firebase
            FirebaseLogHelper::logScrapProcess(
                $db,
                $ticketId
            );

            // STEP 4: Return success dengan data null
            return $this->success(null);

        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }

}
