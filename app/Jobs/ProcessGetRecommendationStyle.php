<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Scan;
use App\Services\FirebaseService;
use App\Helpers\FirebaseLogHelper;
use App\Helpers\BuildPromptHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ProcessGetRecommendationStyle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    
    public function __construct(
        public int $scanId,
        public string $ticketId
    ) {
    }

    public function handle(): void
    {
        try {
            Log::info("Run get recommendation style..");
        $scan = Scan::with('user.userDetail.skinTone', 'user.userDetail.bodyShape')
            ->findOrFail($this->scanId);

        $db = FirebaseService::database();

        Log::info("Prepare scrap");
        FirebaseLogHelper::logScrapPrepared($db, $this->ticketId);

        $products = BuildPromptHelper::run($scan);

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

        FirebaseLogHelper::logScrapQueued($db, $this->ticketId);

        $db->getReference('ticket-request')->push([
            'ticket_id'  => $this->ticketId,
            'products'   => $productsFormatted,
            'status'     => 'pending',
            'created_at' => now()->toDateTimeString(),
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'x-secret-key' => config('services.scraper.secret_key'),
                ])
                ->post('https://scraper.styloartificial.my.id/api/add-to-queue-scraper', [
                    'ticket_id' => $this->ticketId,
                    'products'  => $productsFormatted,
                ]);

            if ($response->failed()) {
                Log::error("Failed to add ticket {$this->ticketId} to scraper queue. Status: {$response->status()}, Body: {$response->body()}");
            } else {
                Log::info("Successfully added ticket {$this->ticketId} to scraper queue.");
            }
        } catch (\Throwable $th) {
            Log::error("Error calling scraper-gateway for ticket {$this->ticketId}: {$th->getMessage()}");
        }

        Log::info("Get recommendation style done.");
        } catch (\Throwable $th) {
            Log::error("Error get recommendation style: {$th->getMessage()}");
        }
    }
}