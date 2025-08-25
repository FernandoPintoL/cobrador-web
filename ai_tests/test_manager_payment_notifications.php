<?php
/**
 * Test script para validar notificaciones a managers cuando cobradores reciben pagos
 * 
 * Este script crea un pago de prueba y verifica que:
 * 1. Se notifica al cobrador que recibe el pago
 * 2. Se notifica al manager del cobrador
 * 3. Ambas notificaciones se envían via WebSocket
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\Notification;
use App\Events\PaymentReceived;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 PRUEBA: Notificaciones a Manager por Pagos de Cobrador\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    // 1. Buscar un manager, un cobrador y un cliente para la prueba
    echo "📋 Configurando datos de prueba...\n";
    
    $manager = User::whereHas('roles', function($query) {
        $query->where('name', 'manager');
    })->first();
    
    if (!$manager) {
        echo "❌ No se encontró un manager en el sistema\n";
        exit(1);
    }
    
    $cobrador = User::whereHas('roles', function($query) {
        $query->where('name', 'cobrador');
    })->where('assigned_manager_id', $manager->id)->first();
    
    if (!$cobrador) {
        echo "❌ No se encontró un cobrador asignado al manager {$manager->name}\n";
        exit(1);
    }
    
    $client = User::whereHas('roles', function($query) {
        $query->where('name', 'client');
    })->where('assigned_cobrador_id', $cobrador->id)->first();
    
    if (!$client) {
        echo "❌ No se encontró un cliente asignado al cobrador {$cobrador->name}\n";
        exit(1);
    }
    
    // Buscar un crédito activo para el cliente
    $credit = Credit::where('client_id', $client->id)
        ->where('status', 'active')
        ->first();
    
    if (!$credit) {
        echo "ℹ️ No hay crédito activo, creando uno de prueba...\n";
        $credit = Credit::create([
            'client_id' => $client->id,
            'created_by' => $cobrador->id,
            'amount' => 1000,
            'balance' => 800,
            'total_amount' => 1200,
            'installment_amount' => 100,
            'interest_rate' => 20,
            'frequency' => 'weekly',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'status' => 'active'
        ]);
    }
    
    echo "✅ Datos configurados:\n";
    echo "   - Manager: {$manager->name} (ID: {$manager->id})\n";
    echo "   - Cobrador: {$cobrador->name} (ID: {$cobrador->id})\n";
    echo "   - Cliente: {$client->name} (ID: {$client->id})\n";
    echo "   - Crédito: ID {$credit->id}, Balance: {$credit->balance} Bs\n\n";
    
    // 2. Contar notificaciones antes de la prueba
    $notificationsBefore = Notification::count();
    $managerNotificationsBefore = Notification::where('user_id', $manager->id)->count();
    $cobradorNotificationsBefore = Notification::where('user_id', $cobrador->id)->count();
    
    echo "📊 Estado inicial:\n";
    echo "   - Total notificaciones: {$notificationsBefore}\n";
    echo "   - Notificaciones manager: {$managerNotificationsBefore}\n";
    echo "   - Notificaciones cobrador: {$cobradorNotificationsBefore}\n\n";
    
    // 3. Crear un pago (esto debería disparar el evento PaymentReceived)
    echo "💰 Creando pago de prueba...\n";
    
    $paymentAmount = 150;
    $payment = Payment::create([
        'client_id' => $client->id,
        'cobrador_id' => $cobrador->id,
        'credit_id' => $credit->id,
        'amount' => $paymentAmount,
        'payment_date' => now(),
        'payment_method' => 'cash',
        'status' => 'completed',
        'installment_number' => 1,
        'latitude' => -17.7839,
        'longitude' => -63.1823
    ]);
    
    echo "✅ Pago creado: ID {$payment->id}, Monto: {$payment->amount} Bs\n\n";
    
    // 4. Esperar un momento para que se procesen los eventos
    echo "⏳ Esperando procesamiento de eventos...\n";
    sleep(2);
    
    // 5. Verificar las notificaciones después del pago
    $notificationsAfter = Notification::count();
    $managerNotificationsAfter = Notification::where('user_id', $manager->id)->count();
    $cobradorNotificationsAfter = Notification::where('user_id', $cobrador->id)->count();
    
    echo "📊 Estado después del pago:\n";
    echo "   - Total notificaciones: {$notificationsAfter}\n";
    echo "   - Notificaciones manager: {$managerNotificationsAfter}\n";
    echo "   - Notificaciones cobrador: {$cobradorNotificationsAfter}\n\n";
    
    // 6. Verificar notificaciones específicas del pago
    $paymentNotifications = Notification::where('payment_id', $payment->id)->get();
    
    echo "🔍 Notificaciones relacionadas al pago:\n";
    foreach ($paymentNotifications as $notification) {
        $user = User::find($notification->user_id);
        echo "   - Para: {$user->name} ({$user->getRoleNames()->first()})\n";
        echo "     Tipo: {$notification->type}\n";
        echo "     Mensaje: {$notification->message}\n";
        echo "     Estado: {$notification->status}\n";
        echo "     Fecha: {$notification->created_at}\n";
        echo "     ---\n";
    }
    
    // 7. Verificar notificaciones del manager sobre el cobrador
    $managerPaymentNotifications = Notification::where('user_id', $manager->id)
        ->where('type', 'cobrador_payment_received')
        ->where('payment_id', $payment->id)
        ->get();
    
    echo "\n📢 Notificaciones específicas al manager:\n";
    if ($managerPaymentNotifications->count() > 0) {
        foreach ($managerPaymentNotifications as $notification) {
            echo "   ✅ ÉXITO: Manager notificado correctamente\n";
            echo "      Mensaje: {$notification->message}\n";
            echo "      Fecha: {$notification->created_at}\n";
        }
    } else {
        echo "   ❌ ERROR: Manager NO fue notificado\n";
    }
    
    // 8. Resumen de la prueba
    $newNotifications = $notificationsAfter - $notificationsBefore;
    $newManagerNotifications = $managerNotificationsAfter - $managerNotificationsBefore;
    $newCobradorNotifications = $cobradorNotificationsAfter - $cobradorNotificationsBefore;
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📋 RESUMEN DE LA PRUEBA:\n";
    echo "   - Nuevas notificaciones totales: {$newNotifications}\n";
    echo "   - Nuevas notificaciones manager: {$newManagerNotifications}\n";
    echo "   - Nuevas notificaciones cobrador: {$newCobradorNotifications}\n\n";
    
    if ($newManagerNotifications > 0 && $newCobradorNotifications > 0) {
        echo "✅ PRUEBA EXITOSA: Tanto el manager como el cobrador fueron notificados\n";
    } elseif ($newCobradorNotifications > 0 && $newManagerNotifications == 0) {
        echo "⚠️ PRUEBA PARCIAL: Solo el cobrador fue notificado, falta notificar al manager\n";
    } else {
        echo "❌ PRUEBA FALLIDA: No se generaron notificaciones correctamente\n";
    }
    
    echo "\n🔗 Pago de prueba creado con ID: {$payment->id}\n";
    echo "💡 Puedes revisar este pago y sus notificaciones en la base de datos\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . " línea " . $e->getLine() . "\n";
    exit(1);
}

echo "\n✨ Prueba completada\n";
