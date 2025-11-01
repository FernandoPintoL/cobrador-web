<?php

namespace App\Listeners;

use App\Events\CreditCreated;
use App\Services\RealtimeStatsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UpdateRealtimeStatsOnCreditCreated implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';
    public int $tries = 3;
    public int $backoff = 5;

    public function handle(CreditCreated $event): void
    {
        try {
            $credit = $event->credit;

            if ($credit->created_by) {
                RealtimeStatsService::updateCobradorStats($credit->created_by);
            }

            RealtimeStatsService::updateGlobalStats();

            Log::info('Real-time stats updated for credit created', [
                'credit_id' => $credit->id,
                'cobrador_id' => $credit->created_by,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating stats on credit created', [
                'error' => $e->getMessage(),
                'credit_id' => $event->credit->id,
            ]);
            throw $e;
        }
    }
}
