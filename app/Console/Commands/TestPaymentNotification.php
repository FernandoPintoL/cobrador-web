<?php

namespace App\Console\Commands;

use App\Events\PaymentCreated;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use App\Services\WebSocketNotificationService;
use Illuminate\Console\Command;

class TestPaymentNotification extends Command
{
    protected $signature = 'test:payment-notification {payment_id?}';

    protected $description = 'Test payment WebSocket notification';

    protected WebSocketNotificationService $webSocketService;

    public function __construct(WebSocketNotificationService $webSocketService)
    {
        parent::__construct();
        $this->webSocketService = $webSocketService;
    }

    public function handle(): int
    {
        $this->info('🧪 Testing Payment WebSocket Notification...');
        $this->newLine();

        $paymentId = $this->argument('payment_id');

        if ($paymentId) {
            // Usar pago existente
            $payment = Payment::with(['credit.client', 'receivedBy'])->find($paymentId);

            if (! $payment) {
                $this->error("Payment ID {$paymentId} not found");

                return Command::FAILURE;
            }
        } else {
            // Buscar el último pago
            $payment = Payment::with(['credit.client', 'receivedBy'])->latest()->first();

            if (! $payment) {
                $this->error('No payments found in database');

                return Command::FAILURE;
            }
        }

        $this->components->info("Testing with Payment ID: {$payment->id}");
        $this->components->info("Amount: \${$payment->amount}");
        $this->components->info("Received by: {$payment->receivedBy->name} (ID: {$payment->receivedBy->id})");

        $cobrador = $payment->receivedBy;
        $manager = $cobrador->assignedManager;
        $client = $payment->credit->client;

        $this->newLine();
        $this->info('📤 Sending WebSocket notification...');

        // Verificar configuración
        if (! $this->webSocketService->isEnabled()) {
            $this->components->error('WebSocket service is DISABLED');
            $this->warn('Set WEBSOCKET_ENABLED=true in .env');

            return Command::FAILURE;
        }

        $this->components->success('✓ WebSocket service is enabled');

        // Test de conexión
        $this->info('🔌 Testing WebSocket server connection...');
        $connectionTest = $this->webSocketService->testConnection();

        if (! $connectionTest['success']) {
            $this->components->error('✗ Cannot connect to WebSocket server');
            $this->warn("Error: {$connectionTest['message']}");
            $this->warn("URL: {$connectionTest['url']}");

            return Command::FAILURE;
        }

        $this->components->success('✓ Connected to WebSocket server');
        $this->newLine();

        // Enviar notificación directa (sin evento)
        $this->info('📡 Sending direct notification...');

        try {
            $sent = $this->webSocketService->notifyPaymentReceived(
                $payment,
                $cobrador,
                $manager,
                $client
            );

            if ($sent) {
                $this->components->success('✓ Notification sent successfully!');
                $this->newLine();

                $this->info('📊 Notification Details:');
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Payment ID', $payment->id],
                        ['Amount', '$'.$payment->amount],
                        ['Cobrador ID', $cobrador->id],
                        ['Cobrador Name', $cobrador->name],
                        ['Manager ID', $manager?->id ?? 'N/A'],
                        ['Manager Name', $manager?->name ?? 'N/A'],
                        ['Client ID', $client->id],
                        ['Client Name', $client->name],
                    ]
                );

                $this->newLine();
                $this->components->info('Check the WebSocket server console for confirmation');
                $this->components->info('Check Flutter app for notification if cobrador is connected');
            } else {
                $this->components->error('✗ Notification failed to send');
                $this->warn('Check storage/logs/laravel.log for details');
            }
        } catch (\Exception $e) {
            $this->components->error('✗ Exception occurred');
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('🔍 Testing event dispatch...');

        try {
            event(new PaymentCreated($payment, $cobrador, $manager, $client));
            $this->components->success('✓ PaymentCreated event dispatched');
            $this->components->info('Check if listener executed in logs');
        } catch (\Exception $e) {
            $this->components->error('✗ Event dispatch failed');
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
