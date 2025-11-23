<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use App\Models\Credit;
use Illuminate\Support\Facades\DB;

class ConsolidateDuplicatePartialPayments extends Command
{
    protected $signature = 'payments:consolidate-duplicates {--dry-run : Ejecutar sin hacer cambios}';
    protected $description = 'Consolida múltiples pagos de la misma cuota en un solo pago';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 Modo DRY-RUN: No se realizarán cambios en la base de datos');
        }

        $this->info('📊 Buscando cuotas con múltiples pagos...');

        // Obtener cuotas que tienen más de un pago
        $duplicates = DB::table('payments')
            ->select('credit_id', 'installment_number', DB::raw('COUNT(*) as payment_count'), DB::raw('SUM(amount) as total_amount'))
            ->whereNotNull('installment_number')
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->groupBy('credit_id', 'installment_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('✅ No se encontraron cuotas con múltiples pagos');
            return Command::SUCCESS;
        }

        $this->info("Encontradas {$duplicates->count()} cuotas con múltiples pagos");
        $totalConsolidated = 0;
        $totalDeleted = 0;

        foreach ($duplicates as $duplicate) {
            $payments = Payment::where('credit_id', $duplicate->credit_id)
                ->where('installment_number', $duplicate->installment_number)
                ->whereNotIn('status', ['cancelled', 'failed'])
                ->orderBy('created_at', 'asc')
                ->get();

            if ($payments->count() <= 1) {
                continue;
            }

            $credit = Credit::find($duplicate->credit_id);
            $installmentAmount = (float) ($credit->installment_amount ?? 0);

            $this->line(sprintf(
                '  Crédito #%d, Cuota #%d: %d pagos (total: %.2fbs / cuota: %.2fbs)',
                $duplicate->credit_id,
                $duplicate->installment_number,
                $payments->count(),
                $duplicate->total_amount,
                $installmentAmount
            ));

            if (!$dryRun) {
                DB::transaction(function () use ($payments, $installmentAmount, &$totalDeleted) {
                    // Mantener el primer pago y acumular los montos
                    $firstPayment = $payments->first();
                    $totalAmount = 0;
                    $latestDate = $firstPayment->payment_date;
                    $latestReceiver = $firstPayment->received_by;
                    $latestCashBalance = $firstPayment->cash_balance_id;
                    $latestLatitude = $firstPayment->latitude;
                    $latestLongitude = $firstPayment->longitude;

                    foreach ($payments as $payment) {
                        $totalAmount += (float) $payment->amount;

                        // Usar la información del pago más reciente
                        if ($payment->created_at > $firstPayment->created_at) {
                            $latestDate = $payment->payment_date;
                            $latestReceiver = $payment->received_by;
                            $latestCashBalance = $payment->cash_balance_id;
                            $latestLatitude = $payment->latitude;
                            $latestLongitude = $payment->longitude;
                        }
                    }

                    // Actualizar el primer pago con el total consolidado
                    $wasCompleted = $firstPayment->status === 'completed';

                    $firstPayment->amount = $totalAmount;
                    $firstPayment->payment_date = $latestDate;
                    $firstPayment->received_by = $latestReceiver;
                    $firstPayment->cash_balance_id = $latestCashBalance;
                    $firstPayment->latitude = $latestLatitude;
                    $firstPayment->longitude = $latestLongitude;
                    $firstPayment->status = $totalAmount >= $installmentAmount ? 'completed' : 'partial';

                    // Importante: temporalmente deshabilitar eventos para evitar recalcular balance múltiples veces
                    Payment::withoutEvents(function () use ($firstPayment) {
                        $firstPayment->save();
                    });

                    // Eliminar los pagos duplicados (excepto el primero)
                    $paymentsToDelete = $payments->slice(1);
                    foreach ($paymentsToDelete as $payment) {
                        Payment::withoutEvents(function () use ($payment) {
                            $payment->delete();
                        });
                        $totalDeleted++;
                    }

                    // Ahora recalcular manualmente el balance del crédito
                    $credit = $firstPayment->credit;
                    if ($credit) {
                        $credit->balance = $credit->total_amount - $credit->payments()->sum('amount');
                        $credit->total_paid = $credit->payments()
                            ->where('status', 'completed')
                            ->sum('amount');

                        // Contar cuotas pagadas completas
                        $credit->paid_installments = DB::table('payments')
                            ->select('installment_number')
                            ->where('credit_id', $credit->id)
                            ->whereNotNull('installment_number')
                            ->groupBy('installment_number')
                            ->havingRaw('SUM(amount) >= ?', [$installmentAmount])
                            ->count();

                        $credit->save();
                    }
                });

                $totalConsolidated++;
            } else {
                $totalConsolidated++;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("✅ Análisis completado:");
            $this->info("   🔄 Cuotas que se consolidarían: {$totalConsolidated}");
            $this->newLine();
            $this->warn('⚠️  Para aplicar los cambios, ejecuta el comando sin --dry-run');
        } else {
            $this->info("✅ Consolidación completada:");
            $this->info("   ✔️  Cuotas consolidadas: {$totalConsolidated}");
            $this->info("   🗑️  Pagos eliminados: {$totalDeleted}");
        }

        return Command::SUCCESS;
    }
}
