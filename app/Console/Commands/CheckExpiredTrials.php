<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class CheckExpiredTrials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:check-expired-trials {--dry-run : Mostrar qué se haría sin ejecutar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar y suspender tenants con período de prueba expirado';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando períodos de prueba expirados...');
        $this->newLine();

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  Modo DRY-RUN: No se realizarán cambios');
            $this->newLine();
        }

        // Buscar tenants en trial con fecha expirada
        $expiredTrials = Tenant::where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->get();

        if ($expiredTrials->isEmpty()) {
            $this->info('✅ No hay trials expirados');
            return Command::SUCCESS;
        }

        $this->warn("⚠️  Trials expirados encontrados: {$expiredTrials->count()}");
        $this->newLine();

        $suspended = 0;

        foreach ($expiredTrials as $tenant) {
            $daysExpired = now()->diffInDays($tenant->trial_ends_at);

            if ($dryRun) {
                $this->line("🔸 {$tenant->name}:");
                $this->line("   - Trial expiró hace {$daysExpired} días ({$tenant->trial_ends_at->format('Y-m-d')})");
                $this->line("   - Acción: Se suspendería automáticamente");
            } else {
                $tenant->update(['status' => 'suspended']);

                $this->error("❌ {$tenant->name}:");
                $this->error("   - Suspendido por trial expirado hace {$daysExpired} días");

                $suspended++;

                // Aquí podrías enviar notificación por email
                // event(new TrialExpired($tenant));
            }

            $this->newLine();
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($dryRun) {
            $this->info("📊 Se suspenderían: {$expiredTrials->count()} tenants");
        } else {
            $this->info("✅ Tenants suspendidos: {$suspended}");
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        return Command::SUCCESS;
    }
}
