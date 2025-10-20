<?php

namespace App\Console\Commands;

use App\Events\CreditCreated;
use App\Models\Credit;
use App\Models\User;
use App\Services\WebSocketNotificationService;
use Illuminate\Console\Command;

class TestWebSocketConnection extends Command
{
    protected $signature = 'websocket:test';

    protected $description = 'Test WebSocket connection and notifications';

    public function handle(WebSocketNotificationService $wsService): int
    {
        $this->info('🔍 Testing WebSocket Connection...');
        $this->newLine();

        // 1. Verificar configuración
        $this->info('📋 Step 1: Checking Configuration');
        $this->line('  WebSocket URL: '.config('websocket.url'));
        $this->line('  WebSocket Enabled: '.(config('websocket.enabled') ? 'Yes' : 'No'));
        $this->line('  WebSocket Secret: '.(config('websocket.secret') ? 'Set' : 'NOT SET ❌'));
        $this->line('  Queue Enabled: '.(config('websocket.queue.enabled') ? 'Yes' : 'No'));
        $this->line('  Queue Default: '.config('queue.default'));
        $this->newLine();

        if (! config('websocket.secret')) {
            $this->error('❌ WS_SECRET is not configured in .env');
            $this->line('   Add: WS_SECRET=your-secret-key-here');

            return self::FAILURE;
        }

        // 2. Test de salud del WebSocket
        $this->info('📋 Step 2: Testing WebSocket Health');
        $testResult = $wsService->testConnection();

        if ($testResult['success']) {
            $this->info('  ✅ WebSocket server is reachable');
            $this->line('     URL: '.$testResult['url']);
        } else {
            $this->error('  ❌ Failed to connect to WebSocket server');
            $this->line('     Error: '.$testResult['error']);
            $this->newLine();
            $this->warn('💡 Make sure the WebSocket server is running:');
            $this->line('     cd websocket-server');
            $this->line('     npm start');

            return self::FAILURE;
        }
        $this->newLine();

        // 3. Test de notificación directa
        $this->info('📋 Step 3: Testing Direct Notification');

        try {
            $success = $wsService->notifyUserType('manager', 'test_notification', [
                'title' => 'Test desde Laravel',
                'message' => 'Esta es una notificación de prueba',
                'timestamp' => now()->toISOString(),
            ]);

            if ($success) {
                $this->info('  ✅ Direct notification sent successfully');
                $this->line('     Check the WebSocket server console for confirmation');
            } else {
                $this->error('  ❌ Failed to send direct notification');
                $this->line('     Check Laravel logs: storage/logs/laravel.log');
            }
        } catch (\Exception $e) {
            $this->error('  ❌ Exception: '.$e->getMessage());
        }
        $this->newLine();

        // 4. Test de evento (si hay datos)
        $this->info('📋 Step 4: Testing Event-based Notification');

        $cobrador = User::whereHas('roles', fn ($q) => $q->where('name', 'cobrador'))->first();
        $manager = User::whereHas('roles', fn ($q) => $q->where('name', 'manager'))->first();
        $credit = Credit::latest()->first();

        if (! $cobrador || ! $manager) {
            $this->warn('  ⚠️  Skipped: Need cobrador and manager users in database');
            $this->line('     Create users with these roles to test events');
        } elseif (! $credit) {
            $this->warn('  ⚠️  Skipped: Need at least one credit in database');
        } else {
            $this->line('  Found users and credit for testing');
            $this->line('  Cobrador: '.$cobrador->name);
            $this->line('  Manager: '.$manager->name);
            $this->line('  Credit ID: '.$credit->id);

            try {
                // Disparar evento
                $this->line('  Dispatching CreditCreated event...');
                event(new CreditCreated($credit, $manager, $cobrador));

                $this->info('  ✅ Event dispatched successfully');
                $this->line('     Check WebSocket server console to see if notification arrived');
                $this->line('     Check Laravel logs if nothing appears in WebSocket console');
            } catch (\Exception $e) {
                $this->error('  ❌ Exception: '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info('✅ Test Complete!');
        $this->newLine();
        $this->line('📊 Next Steps:');
        $this->line('1. Check WebSocket server console (should show received notifications)');
        $this->line('2. Check Laravel logs: tail -f storage/logs/laravel.log');
        $this->line('3. If manager is connected to WebSocket, they should see notifications');

        return self::SUCCESS;
    }
}
