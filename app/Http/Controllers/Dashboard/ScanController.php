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
use App\Http\Requests\LogScrapProcessRequest;
use App\Http\Requests\CloseTicketRequest;
use App\Http\Requests\ValidateImageByProfileGenderRequest;
use App\Services\OpenAIService;
use App\Models\ScanItemCategory;

class ScanController extends BaseController
{
    public function scanCategory(): JsonResponse
    {
        $categories = MScanCategory::select('id', 'title', 'icon', 'type')->get();
        return $this->success($categories);
    }

    public function validateImageByProfileGender(ValidateImageByProfileGenderRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $gender = strtolower($user->userDetail->gender);

            $file = $request->file('img_url');

            if (!$file) {
                return response()->json([
                    'code' => 400,
                    'message' => 'Image is required',
                    'data' => null
                ], 400);
            }

            $tempFileName = S3Helper::storeFileTemp($file);

            $prompt = "Look at this image carefully. Is the person in this image $gender? Answer with only the word true or false, nothing else. No explanation.";

            $payload = [
                'prompt' => $prompt,
                'temp_images' => [$tempFileName],
                'generate_images' => 0,
                'plain_text' => true,
            ];

            $result = OpenAIService::run($payload);

            $rawResult = strtolower(trim($result['analysis']['_raw'] ?? ''));
            $isValid = str_contains($rawResult, 'true');

            S3Helper::removeFileTemp($tempFileName);

            return $this->success($isValid);

        } catch (\Throwable $e) {
            if (isset($tempFileName)) {
                S3Helper::removeFileTemp($tempFileName);
            }

            return $this->serverError($e);
        }
    }

    public function openTicket(OpenTicketRequest $request): JsonResponse
    {
        $db = FirebaseService::database();
        try {
            $validated = $request->validated();
            $ticketId = (string) Str::uuid();

            $file         = $request->file('img_url');
            $tempFileName = S3Helper::storeFileTemp($file);

            S3Helper::storeFileToS3("scans/{$ticketId}", $tempFileName);
            $imgUrl = S3Helper::getUrlFileS3("scans/{$ticketId}", $tempFileName);

            $dataScan = [
                'user_id'   => $request->user()->id,
                'ticket_id' => $ticketId,
                'title'     => $validated['title'],
                'img_url'   => $imgUrl,
            ];

            $scan_items     = $validated['scan_category_id']['item']     ?? [];
            $scan_occassions = $validated['scan_category_id']['occasion'] ?? [];
            $scan_styles    = $validated['scan_category_id']['style']    ?? [];
            $scan_hijab     = $validated['scan_category_id']['hijab']    ?? [];

            $scan = Scan::create($dataScan);

            $scan_categories = [
                ...array_map(fn($id) => [
                    'scan_id'          => $scan->id,
                    'item_category_id' => $id,
                    'type'             => 'item'
                ], $scan_items),
                ...array_map(fn($id) => [
                    'scan_id'          => $scan->id,
                    'item_category_id' => $id,
                    'type'             => 'occasion'
                ], $scan_occassions),
                ...array_map(fn($id) => [
                    'scan_id'          => $scan->id,
                    'item_category_id' => $id,
                    'type'             => 'style'
                ], $scan_styles),
                ...array_map(fn($id) => [
                    'scan_id'          => $scan->id,
                    'item_category_id' => $id,
                    'type'             => 'hijab'
                ], $scan_hijab),
            ];

            ScanItemCategory::insert($scan_categories);

            FirebaseLogHelper::logTicketQueued($db, $ticketId);

            $products = BuildPromptHelper::run($scan);
            FirebaseLogHelper::logScrapPrepared($db, $ticketId);

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

            FirebaseLogHelper::logScrapQueued($db, $ticketId);

            $db->getReference("ticket-request")->push([
                'ticket_id'  => $ticketId,
                'products'   => $productsFormatted,
                'status'     => 'pending',
                'created_at' => now()->toDateTimeString(),
            ]);

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

            $validated = $request->validated();
            $ticketId = $validated['ticket_id'];


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

    public function closeTicket(CloseTicketRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $ticketId  = $validated['ticket_id'];

            $db = FirebaseService::database();

            // =========================
            // CEK TICKET (FIX PATH)
            // =========================
            $ticketRef  = $db->getReference("tickets/{$ticketId}");
            $ticketData = $ticketRef->getValue();

            if (empty($ticketData)) {
                return response()->json([
                    'code'    => 400,
                    'message' => 'Ticket not found.',
                    'data'    => null,
                ], 400);
            }

            // =========================
            // CEK DB SCAN
            // =========================
            $scan = Scan::where('ticket_id', $ticketId)->first();

            if (!$scan) {
                throw new \Exception("Scan tidak ditemukan di database");
            }

            // =========================
            // CEK STATUS
            // =========================
            if ($scan->status === 'COMPLETED') {
                return $this->success("Ticket already completed");
            }

            // =========================
            // UPDATE FIREBASE
            // =========================
            $db->getReference("scans/{$ticketId}/status")
                ->set('complete');

            $db->getReference("tickets/{$ticketId}/status")
                ->set('complete');

            // =========================
            // UPDATE DATABASE
            // =========================
            $scan->status = 'COMPLETED';
            $scan->save();

            // =========================
            // LOG
            // =========================
            FirebaseLogHelper::logGenerationCompleted($db, $ticketId);

            return $this->success(null);

        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
