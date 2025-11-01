<?php

namespace App\Listeners;

use App\Events\CreditApproved;
use App\Services\RealtimeStatsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UpdateRealtimeStatsOnCreditApproved implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';
    public int $tries = 3;
    public int $backoff = 5;

    public function handle(CreditApproved $event): void
    {
        try {
            $credit = $event->credit;

            if ($credit->created_by) {
                RealtimeStatsService::updateCobradorStats($credit->created_by);
            }

            RealtimeStatsService::updateGlobalStats();

            Log::info('Real-time stats updated for credit approved', [
                'credit_id' => $credit->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating stats on credit approved', [
                'error' => $e->getMessage(),
                'credit_id' => $event->credit->id,
            ]);
            throw $e;
        }
    }
}
