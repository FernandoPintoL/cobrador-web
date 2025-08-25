<?php
/**
 * Test directo de notificación de pagos sin usar colas
 * Este script dispara manualmente el evento PaymentReceived para probar inmediatamente
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\Notification;
use App\Events\PaymentReceived;
use App\Listeners\SendPaymentReceivedNotification;
use App\Services\WebSocketNotificationService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 PRUEBA DIRECTA: Notificaciones Manager por Pagos\n";
echo "=" . str_repeat("=", 55) . "\n\n";

try {
    // 1. Encontrar datos de prueba
    echo "📋 Buscando datos existentes...\n";
    
    $payment = Payment::with(['cobrador.assignedManager', 'credit.client'])->first();
    
    if (!$payment) {
        echo "❌ No se encontró ningún pago en el sistema\n";
        exit(1);
    }
    
    $cobrador = $payment->cobrador;
    $manager = $cobrador ? $cobrador->assignedManager : null;
    $client = $payment->credit->client;
    
    if (!$manager) {
        echo "❌ El cobrador no tiene manager asignado\n";
        echo "   Cobrador: {$cobrador->name} (ID: {$cobrador->id})\n";
        exit(1);
    }
    
    echo "✅ Datos encontrados:\n";
    echo "   - Pago: ID {$payment->id}, Monto: {$payment->amount} Bs\n";
    echo "   - Cliente: {$client->name} (ID: {$client->id})\n";
    echo "   - Cobrador: {$cobrador->name} (ID: {$cobrador->id})\n";
    echo "   - Manager: {$manager->name} (ID: {$manager->id})\n\n";
    
    // 2. Contar notificaciones antes
    $notificationsBefore = Notification::count();
    $managerNotificationsBefore = Notification::where('user_id', $manager->id)->count();
    
    echo "📊 Estado inicial:\n";
    echo "   - Total notificaciones: {$notificationsBefore}\n";
    echo "   - Notificaciones manager: {$managerNotificationsBefore}\n\n";
    
    // 3. Crear manualmente el listener y disparar el evento
    echo "🔥 Disparando evento PaymentReceived...\n";
    
    // Crear instancia del WebSocketService (mock para prueba)
    $webSocketService = app(WebSocketNotificationService::class);
    
    // Crear el listener
    $listener = new SendPaymentReceivedNotification($webSocketService);
    
    // Crear y disparar el evento
    $event = new PaymentReceived($payment, $cobrador);
    
    // Ejecutar el listener directamente (sin cola)
    $listener->handle($event);
    
    echo "✅ Evento procesado\n\n";
    
    // 4. Verificar las notificaciones después
    sleep(1); // Pequeña pausa para asegurar que se guarden los datos
    
    $notificationsAfter = Notification::count();
    $managerNotificationsAfter = Notification::where('user_id', $manager->id)->count();
    
    echo "📊 Estado después:\n";
    echo "   - Total notificaciones: {$notificationsAfter}\n";
    echo "   - Notificaciones manager: {$managerNotificationsAfter}\n\n";
    
    // 5. Verificar notificaciones específicas del manager
    $managerPaymentNotifications = Notification::where('user_id', $manager->id)
        ->where('type', 'cobrador_payment_received')
        ->where('payment_id', $payment->id)
        ->get();
    
    echo "🔍 Notificaciones del manager:\n";
    if ($managerPaymentNotifications->count() > 0) {
        foreach ($managerPaymentNotifications as $notification) {
            echo "   ✅ ÉXITO: Manager notificado\n";
            echo "      Tipo: {$notification->type}\n";
            echo "      Mensaje: {$notification->message}\n";
            echo "      Estado: {$notification->status}\n";
            echo "      Fecha: {$notification->created_at}\n";
        }
    } else {
        echo "   ❌ ERROR: Manager NO fue notificado\n";
        
        // Verificar si hay alguna notificación del payment
        $allPaymentNotifications = Notification::where('payment_id', $payment->id)->get();
        echo "   📋 Notificaciones del pago:\n";
        foreach ($allPaymentNotifications as $notification) {
            $user = User::find($notification->user_id);
            echo "      - Para: {$user->name} ({$user->getRoleNames()->first()})\n";
            echo "        Tipo: {$notification->type}\n";
            echo "        Mensaje: {$notification->message}\n";
        }
    }
    
    // 6. Resumen
    $newNotifications = $notificationsAfter - $notificationsBefore;
    $newManagerNotifications = $managerNotificationsAfter - $managerNotificationsBefore;
    
    echo "\n" . str_repeat("=", 55) . "\n";
    echo "📋 RESUMEN:\n";
    echo "   - Nuevas notificaciones: {$newNotifications}\n";
    echo "   - Nuevas notificaciones manager: {$newManagerNotifications}\n\n";
    
    if ($newManagerNotifications > 0) {
        echo "✅ PRUEBA EXITOSA: Manager recibió notificación del pago\n";
    } else {
        echo "❌ PRUEBA FALLIDA: Manager NO recibió notificación\n";
        
        // Debug adicional
        echo "\n🔍 DEBUG ADICIONAL:\n";
        echo "   - ¿Existe relación cobrador->manager? " . ($cobrador->assignedManager ? "SÍ" : "NO") . "\n";
        echo "   - Manager ID esperado: {$manager->id}\n";
        echo "   - Cobrador->assigned_manager_id: {$cobrador->assigned_manager_id}\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . " línea " . $e->getLine() . "\n";
    echo "📚 Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n✨ Prueba completada\n";
