<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class KeepSupabaseAlive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supabase:keep-alive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Keep Supabase database alive';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Execute supabase keep alive scheduler');
        User::first();
        Log::info('Supabase keep alive scheduler successfuly executed');
        return 0;
    }
}
