<?php
namespace App\Console\Commands;

use App\Models\Credit;
use Illuminate\Console\Command;

class UpdateCreditsTotalPaid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:update-total-paid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el campo total_paid en todos los créditos basado en la suma de pagos completados';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Actualizando el campo total_paid en créditos...');

        $creditsCount = Credit::count();
        $this->info("Total de créditos a procesar: {$creditsCount}");

        $bar = $this->output->createProgressBar($creditsCount);
        $bar->start();

        // Procesar por lotes para evitar problemas de memoria
        Credit::chunk(100, function ($credits) use ($bar) {
            foreach ($credits as $credit) {
                $totalPaid = $credit->payments()
                    ->where('status', 'completed')
                    ->sum('amount');

                $credit->total_paid = $totalPaid;
                $credit->save();

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('El campo total_paid ha sido actualizado en todos los créditos.');

        return 0;
    }
}
