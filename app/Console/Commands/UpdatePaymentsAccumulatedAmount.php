<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdatePaymentsAccumulatedAmount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:update-accumulated';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los montos acumulados para todos los pagos existentes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Actualizando montos acumulados para pagos existentes...');

        // Importar los modelos necesarios
        $credits      = \App\Models\Credit::all();
        $totalCredits = $credits->count();
        $bar          = $this->output->createProgressBar($totalCredits);
        $bar->start();

        $updatedPayments = 0;

        foreach ($credits as $credit) {
            // Obtener todos los pagos del crédito ordenados por fecha
            $payments = $credit->payments()->orderBy('payment_date')->orderBy('id')->get();

            $accumulatedAmount = 0;

            foreach ($payments as $payment) {
                $accumulatedAmount += $payment->amount;
                $payment->accumulated_amount = $accumulatedAmount;
                $payment->save();
                $updatedPayments++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\n");
        $this->info("¡Completado! Se actualizaron {$updatedPayments} pagos en {$totalCredits} créditos.");
    }
}
