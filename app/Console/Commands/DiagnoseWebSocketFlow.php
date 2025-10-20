<?php

namespace App\Console\Commands;

use App\Events\PaymentCreated;
use App\Models\Credit;
use App\Models\Payment;
use App\Services\WebSocketNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;

class DiagnoseWebSocketFlow extends Command
{
    protected $signature = 'websocket:diagnose
                            {--payment-id= : ID del pago específico a probar}
                            {--credit-id= : ID del crédito específico a probar}
                            {--skip-events : Saltar pruebas de eventos}';

    protected $description = 'Diagnóstico completo del flujo de WebSocket: eventos, listeners y notificaciones';

    protected WebSocketNotificationService $wsService;

    protected array $results = [];

    public function __construct(WebSocketNotificationService $wsService)
    {
        parent::__construct();
        $this->wsService = $wsService;
    }

    public function handle(): int
    {
        $this->displayHeader();

        // 1. Verificar configuración
        if (! $this->checkConfiguration()) {
            return Command::FAILURE;
        }

        // 2. Verificar conexión al servidor
        if (! $this->checkWebSocketServer()) {
            return Command::FAILURE;
        }

        // 3. Verificar eventos y listeners
        if (! $this->option('skip-events')) {
            $this->checkEventListeners();
        }

        // 4. Test de pago
        $this->testPaymentFlow();

        // 5. Test de crédito
        $this->testCreditFlow();

        // 6. Verificar usuarios conectados
        $this->checkConnectedUsers();

        // 7. Mostrar resumen
        $this->displaySummary();

        return Command::SUCCESS;
    }

    protected function displayHeader(): void
    {
        $this->newLine();
        $this->line('╔═══════════════════════════════════════════════════════╗');
        $this->line('║   🔍 DIAGNÓSTICO COMPLETO DE WEBSOCKET Y EVENTOS     ║');
        $this->line('╚═══════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    protected function checkConfiguration(): bool
    {
        $this->info('📋 Paso 1: Verificando Configuración');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $checks = [
            'URL' => config('websocket.url'),
            'Habilitado' => config('websocket.enabled') ? '✓ Sí' : '✗ No',
            'Secret Configurado' => config('websocket.secret') ? '✓ Sí' : '✗ No',
            'Timeout' => config('websocket.timeout').'s',
            'Max Reintentos' => config('websocket.retry.max_attempts'),
            'Queue Habilitada' => config('websocket.queue.enabled') ? '✓ Sí' : '✗ No',
            'Queue Connection' => config('queue.default'),
        ];

        $configOk = true;

        foreach ($checks as $key => $value) {
            $status = str_contains((string) $value, '✗') ? '❌' : '✅';
            $this->line("  {$status} {$key}: {$value}");

            if (str_contains((string) $value, '✗')) {
                $configOk = false;
            }
        }

        $this->newLine();

        if (! config('websocket.secret')) {
            $this->error('⚠️  WS_SECRET no está configurado');
            $this->warn('Agrega a tu .env: WS_SECRET=tu-clave-secreta');
            $this->results['configuration'] = '❌ Fallida';

            return false;
        }

        if (! config('websocket.enabled')) {
            $this->error('⚠️  WebSocket está deshabilitado');
            $this->warn('Cambia en tu .env: WEBSOCKET_ENABLED=true');
            $this->results['configuration'] = '❌ Fallida';

            return false;
        }

        $this->results['configuration'] = '✅ Correcta';

        return true;
    }

    protected function checkWebSocketServer(): bool
    {
        $this->info('📋 Paso 2: Verificando Servidor WebSocket');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $testResult = $this->wsService->testConnection();

        if ($testResult['success']) {
            $this->line('  ✅ Servidor alcanzable');
            $this->line('  ✅ URL: '.$testResult['url']);

            if (isset($testResult['data'])) {
                $data = $testResult['data'];
                $this->line('  ✅ Uptime: '.($data['uptime'] ?? 'N/A'));
                $this->line('  ✅ Conexiones: '.($data['connections'] ?? 'N/A'));
            }

            $this->results['server'] = '✅ Online';
            $this->newLine();

            return true;
        }

        $this->line('  ❌ No se puede conectar al servidor');
        $this->line('  ❌ Error: '.$testResult['message']);
        $this->newLine();

        $this->warn('💡 Asegúrate de que el servidor WebSocket esté corriendo:');
        $this->line('     cd websocket-server');
        $this->line('     npm start');
        $this->newLine();

        $this->results['server'] = '❌ Offline';

        return false;
    }

    protected function checkEventListeners(): void
    {
        $this->info('📋 Paso 3: Verificando Eventos y Listeners');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $events = [
            \App\Events\PaymentCreated::class => \App\Listeners\SendPaymentCreatedNotification::class,
            \App\Events\CreditCreated::class => \App\Listeners\SendCreditCreatedNotification::class,
            \App\Events\CreditApproved::class => \App\Listeners\SendCreditApprovedNotification::class,
            \App\Events\CreditRejected::class => \App\Listeners\SendCreditRejectedNotification::class,
            \App\Events\CreditDelivered::class => \App\Listeners\SendCreditDeliveredNotification::class,
        ];

        $allRegistered = true;

        foreach ($events as $event => $listener) {
            $eventName = class_basename($event);
            $hasListeners = count(Event::getListeners($event)) > 0;

            if ($hasListeners) {
                $this->line("  ✅ {$eventName} → Listener registrado");
            } else {
                $this->line("  ❌ {$eventName} → Sin listener");
                $allRegistered = false;
            }
        }

        $this->newLine();
        $this->results['events'] = $allRegistered ? '✅ Registrados' : '⚠️  Algunos faltan';
    }

    protected function testPaymentFlow(): void
    {
        $this->info('📋 Paso 4: Probando Flujo de Pagos');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $paymentId = $this->option('payment-id');

        if ($paymentId) {
            $payment = Payment::with(['credit.client', 'receivedBy'])->find($paymentId);
        } else {
            $payment = Payment::with(['credit.client', 'receivedBy'])->latest()->first();
        }

        if (! $payment) {
            $this->line('  ⚠️  No hay pagos en la base de datos');
            $this->line('  💡 Crea un pago desde la aplicación para probarlo');
            $this->results['payment_test'] = '⏭️  Omitido';
            $this->newLine();

            return;
        }

        $this->line("  📄 Usando Payment ID: {$payment->id}");
        $this->line("  💰 Monto: \${$payment->amount}");
        $this->line("  👤 Recibido por: {$payment->receivedBy->name}");
        $this->newLine();

        // Test 1: Notificación directa
        $this->line('  🧪 Test 1: Notificación Directa (bypass listener)');

        try {
            $cobrador = $payment->receivedBy;
            $manager = $cobrador->assignedManager;
            $client = $payment->credit->client;

            $sent = $this->wsService->notifyPaymentReceived($payment, $cobrador, $manager, $client);

            if ($sent) {
                $this->line('     ✅ Notificación enviada al WebSocket');
                $this->results['payment_direct'] = '✅ Exitosa';
            } else {
                $this->line('     ❌ Falló el envío');
                $this->results['payment_direct'] = '❌ Fallida';
            }
        } catch (\Exception $e) {
            $this->line('     ❌ Excepción: '.$e->getMessage());
            $this->results['payment_direct'] = '❌ Error';
        }

        $this->newLine();

        // Test 2: Evento
        $this->line('  🧪 Test 2: Disparo de Evento PaymentCreated');

        try {
            event(new PaymentCreated($payment, $cobrador, $manager, $client));
            $this->line('     ✅ Evento disparado');
            $this->line('     💡 Verifica logs para confirmar que el listener se ejecutó');
            $this->results['payment_event'] = '✅ Disparado';
        } catch (\Exception $e) {
            $this->line('     ❌ Excepción: '.$e->getMessage());
            $this->results['payment_event'] = '❌ Error';
        }

        $this->newLine();
    }

    protected function testCreditFlow(): void
    {
        $this->info('📋 Paso 5: Probando Flujo de Créditos');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $creditId = $this->option('credit-id');

        if ($creditId) {
            $credit = Credit::with('client')->find($creditId);
        } else {
            $credit = Credit::with('client')->latest()->first();
        }

        if (! $credit) {
            $this->line('  ⚠️  No hay créditos en la base de datos');
            $this->results['credit_test'] = '⏭️  Omitido';
            $this->newLine();

            return;
        }

        $cobrador = $credit->client->assignedCobrador ?? $credit->createdBy;
        $manager = $cobrador?->assignedManager;

        if (! $cobrador || ! $manager) {
            $this->line('  ⚠️  El crédito no tiene cobrador o manager asignado');
            $this->results['credit_test'] = '⏭️  Omitido';
            $this->newLine();

            return;
        }

        $this->line("  📄 Usando Credit ID: {$credit->id}");
        $this->line("  💰 Monto: \${$credit->amount}");
        $this->line("  👤 Cobrador: {$cobrador->name}");
        $this->line("  👔 Manager: {$manager->name}");
        $this->newLine();

        // Test: Notificación de crédito creado
        $this->line('  🧪 Test: Notificación de Crédito Creado');

        try {
            $sent = $this->wsService->notifyCreditCreated($credit, $manager, $cobrador);

            if ($sent) {
                $this->line('     ✅ Notificación enviada');
                $this->results['credit_direct'] = '✅ Exitosa';
            } else {
                $this->line('     ❌ Falló el envío');
                $this->results['credit_direct'] = '❌ Fallida';
            }
        } catch (\Exception $e) {
            $this->line('     ❌ Excepción: '.$e->getMessage());
            $this->results['credit_direct'] = '❌ Error';
        }

        $this->newLine();
    }

    protected function checkConnectedUsers(): void
    {
        $this->info('📋 Paso 6: Verificando Usuarios Conectados');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        try {
            $activeUsers = $this->wsService->getActiveUsers();

            $total = $activeUsers['total'] ?? 0;
            $users = $activeUsers['users'] ?? [];

            $this->line("  👥 Total conectados: {$total}");

            if ($total > 0 && ! empty($users)) {
                $this->newLine();
                $this->line('  📋 Usuarios conectados:');

                foreach ($users as $user) {
                    $userId = $user['userId'] ?? 'N/A';
                    $role = $user['role'] ?? 'N/A';
                    $this->line("     • User ID: {$userId} - Role: {$role}");
                }
            } elseif ($total === 0) {
                $this->line('  ⚠️  No hay usuarios conectados actualmente');
                $this->line('  💡 Abre la aplicación Flutter para conectar un usuario');
            }

            $this->results['connected_users'] = "{$total} usuarios";
        } catch (\Exception $e) {
            $this->line('  ❌ Error al obtener usuarios: '.$e->getMessage());
            $this->results['connected_users'] = '❌ Error';
        }

        $this->newLine();
    }

    protected function displaySummary(): void
    {
        $this->line('╔═══════════════════════════════════════════════════════╗');
        $this->line('║              📊 RESUMEN DEL DIAGNÓSTICO              ║');
        $this->line('╚═══════════════════════════════════════════════════════╝');
        $this->newLine();

        $table = [];
        foreach ($this->results as $test => $result) {
            $table[] = [ucwords(str_replace('_', ' ', $test)), $result];
        }

        $this->table(['Prueba', 'Resultado'], $table);

        $this->newLine();
        $this->line('📝 Notas Importantes:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('1. Revisa los logs de Laravel: storage/logs/laravel.log');
        $this->line('2. Revisa la consola del servidor WebSocket');
        $this->line('3. Si Flutter está conectado, debería recibir las notificaciones');
        $this->newLine();

        $this->line('🔍 Comandos útiles:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('• Ver logs en tiempo real:');
        $this->line('  tail -f storage/logs/laravel.log | grep -E "WebSocket|PaymentCreated|CreditCreated"');
        $this->newLine();
        $this->line('• Probar pago específico:');
        $this->line('  php artisan test:payment-notification {payment_id}');
        $this->newLine();
        $this->line('• Test de conexión básico:');
        $this->line('  php artisan websocket:test');
        $this->newLine();
    }
}
