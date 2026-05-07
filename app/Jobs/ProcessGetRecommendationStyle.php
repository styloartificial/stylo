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

class ProcessGetRecommendationStyle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $scanId,
        public string $ticketId
    ) {
    }

    public function handle(): void
    {
        $scan = Scan::with('user.userDetail.skinTone', 'user.userDetail.bodyShape')
            ->findOrFail($this->scanId);

        $db = FirebaseService::database();

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
    }
}
