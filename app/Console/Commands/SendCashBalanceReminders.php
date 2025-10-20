<?php

namespace App\Console\Commands;

use App\Models\CashBalance;
use App\Services\WebSocketNotificationService;
use Illuminate\Console\Command;

class SendCashBalanceReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashbalance:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders to cobradores with open cash balances from previous days';

    protected WebSocketNotificationService $webSocketService;

    /**
     * Create a new command instance.
     */
    public function __construct(WebSocketNotificationService $webSocketService)
    {
        parent::__construct();
        $this->webSocketService = $webSocketService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔔 Sending cash balance closure reminders...');
        $this->newLine();

        $today = now()->toDateString();

        // Get all open cash balances from dates before today
        $openBalances = CashBalance::where('status', 'open')
            ->where('date', '<', $today)
            ->with(['cobrador'])
            ->get();

        if ($openBalances->isEmpty()) {
            $this->components->success('No pending cash balances found.');

            return Command::SUCCESS;
        }

        // Group by cobrador
        $groupedByCobradorId = $openBalances->groupBy('cobrador_id');
        $sentCount = 0;

        foreach ($groupedByCobradorId as $cobradorId => $balances) {
            $cobrador = $balances->first()->cobrador;

            if (! $cobrador) {
                $this->components->warn("Cobrador ID {$cobradorId} not found, skipping...");

                continue;
            }

            $dates = $balances->pluck('date')->map(function ($d) {
                return $d instanceof \Carbon\Carbon ? $d->format('d/m/Y') : $d;
            })->toArray();

            $message = count($dates) === 1
                ? 'Recordatorio: Tienes una caja sin cerrar del día '.implode(', ', $dates).'. Por favor, ciérrala antes de iniciar un nuevo día.'
                : 'Recordatorio: Tienes '.count($dates).' cajas sin cerrar de los días: '.implode(', ', $dates).'. Por favor, ciérralas antes de iniciar un nuevo día.';

            $this->line("  → Sending reminder to {$cobrador->name} ({$cobrador->email})");
            $this->line('    Pending dates: '.implode(', ', $dates));

            // Send WebSocket notification
            $sent = $this->webSocketService->notifyUser(
                (string) $cobrador->id,
                'cash_balance_reminder',
                [
                    'title' => 'Recordatorio de Cierre de Caja',
                    'message' => $message,
                    'pending_count' => count($dates),
                    'pending_dates' => $dates,
                    'pending_boxes' => $balances->map(function ($box) {
                        return [
                            'id' => $box->id,
                            'date' => $box->date,
                            'initial_amount' => $box->initial_amount,
                            'collected_amount' => $box->collected_amount,
                            'lent_amount' => $box->lent_amount,
                            'final_amount' => $box->final_amount,
                        ];
                    })->toArray(),
                ]
            );

            if ($sent) {
                $sentCount++;
                $this->components->success("  ✓ Reminder sent to {$cobrador->name}");
            } else {
                $this->components->warn("  ✗ Failed to send reminder to {$cobrador->name}");
            }

            $this->newLine();
        }

        $this->newLine();
        $this->components->success("Reminders sent to {$sentCount} of {$groupedByCobradorId->count()} cobradores.");

        return Command::SUCCESS;
    }
}
