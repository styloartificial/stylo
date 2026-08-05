<?php

namespace App\Jobs;

use App\Helpers\S3Helper;
use App\Models\Scan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoDeleteSavedItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    public function __construct(
        public int $scanId
    ) {
    }

    public function handle(): void
    {
        try {
            $scan = Scan::with(['scanResult', 'scanSaves', 'scanItemCategories'])
                ->find($this->scanId);

            if (!$scan) {
                Log::warning("AutoDeleteSavedItem: Scan ID {$this->scanId} not found, skipping.");
                return;
            }

            Log::info("AutoDeleteSavedItem: Deleting scan #{$this->scanId} (ticket: {$scan->ticket_id})");

            // ─── 1. Hapus file dari S3/Supabase ───────────────────────────

            // 1a. Foto user (scan->img_url)
            $scanRelativePath = S3Helper::extractRelativePath($scan->img_url);
            if ($scanRelativePath) {
                S3Helper::deleteFile($scanRelativePath);
                Log::info("AutoDeleteSavedItem: Deleted scan img_url from S3", ['path' => $scanRelativePath]);
            }

            // 1b. Foto hasil generate (scanResult->img_urls)
            if ($scan->scanResult) {
                $imgUrls = $scan->scanResult->img_urls ?? [];
                foreach ($imgUrls as $imgUrl) {
                    $relativePath = S3Helper::extractRelativePath($imgUrl);
                    if ($relativePath) {
                        S3Helper::deleteFile($relativePath);
                        Log::info("AutoDeleteSavedItem: Deleted scanResult img from S3", ['path' => $relativePath]);
                    }
                }
            }

            // 1c. Foto saved-items (scanSaves->img_url yang di-upload ulang ke Supabase)
            foreach ($scan->scanSaves as $scanSave) {
                $relativePath = S3Helper::extractRelativePath($scanSave->img_url);
                if ($relativePath) {
                    S3Helper::deleteFile($relativePath);
                    Log::info("AutoDeleteSavedItem: Deleted ScanSave img from S3", ['path' => $relativePath]);
                }
            }

            // ─── 2. Hapus data dari PostgreSQL ────────────────────────────
            DB::transaction(function () use ($scan) {
                // Hapus ScanItemCategory
                $scan->scanItemCategories()->delete();

                // Hapus ScanSave
                $scan->scanSaves()->delete();

                // Hapus ScanResult
                $scan->scanResult()->delete();

                // Hapus Scan
                $scan->delete();
            });

            Log::info("AutoDeleteSavedItem: Scan #{$this->scanId} fully deleted.");

        } catch (\Throwable $th) {
            Log::error("AutoDeleteSavedItem: Failed for scan #{$this->scanId}: " . $th->getMessage(), [
                'exception' => $th,
            ]);

            // Re-throw supaya job bisa retry kalau masih di bawah $tries
            throw $th;
        }
    }
}
