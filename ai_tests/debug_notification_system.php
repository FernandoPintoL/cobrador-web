<?php
/**
 * Debug de la relación assignedManager y verificación del listener
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Payment;
use App\Models\Notification;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 DEBUG: Verificación de relaciones y listener\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // 1. Buscar el pago
    $payment = Payment::with(['cobrador', 'credit.client'])->first();
    $cobrador = $payment->cobrador;
    
    echo "📋 Datos del pago:\n";
    echo "   - Pago ID: {$payment->id}\n";
    echo "   - Cobrador: {$cobrador->name} (ID: {$cobrador->id})\n";
    echo "   - assigned_manager_id: {$cobrador->assigned_manager_id}\n\n";
    
    // 2. Verificar la relación assignedManager
    echo "🔗 Verificando relación assignedManager:\n";
    
    $manager = $cobrador->assignedManager;
    if ($manager) {
        echo "   ✅ Relación funciona correctamente\n";
        echo "   - Manager: {$manager->name} (ID: {$manager->id})\n";
        echo "   - Email: {$manager->email}\n";
    } else {
        echo "   ❌ La relación assignedManager devuelve NULL\n";
        
        // Buscar manualmente el manager
        $manualManager = User::find($cobrador->assigned_manager_id);
        if ($manualManager) {
            echo "   📋 Manager encontrado manualmente: {$manualManager->name}\n";
        } else {
            echo "   ❌ Manager no existe en la BD\n";
        }
    }
    
    // 3. Intentar crear una notificación manualmente
    echo "\n💾 Intentando crear notificación manualmente:\n";
    
    if ($manager) {
        try {
            $notification = Notification::create([
                'user_id' => $manager->id,
                'payment_id' => $payment->id,
                'type' => 'cobrador_payment_received',
                'message' => "PRUEBA: El cobrador {$cobrador->name} recibió un pago de {$payment->amount} Bs",
                'status' => 'unread'
            ]);
            
            echo "   ✅ Notificación creada exitosamente\n";
            echo "   - ID: {$notification->id}\n";
            echo "   - Para usuario: {$notification->user_id}\n";
            echo "   - Tipo: {$notification->type}\n";
            echo "   - Mensaje: {$notification->message}\n";
            
        } catch (\Exception $e) {
            echo "   ❌ Error al crear notificación: {$e->getMessage()}\n";
        }
    }
    
    // 4. Verificar si el tipo es válido
    echo "\n📝 Verificando tipos de notificación válidos:\n";
    
    // Obtener los tipos válidos del controlador (leyendo directamente el código)
    $controllerPath = __DIR__ . '/app/Http/Controllers/Api/NotificationController.php';
    $controllerContent = file_get_contents($controllerPath);
    
    if (preg_match('/\'type\'\s*=>\s*\'required\|in:([^\']+)\'/', $controllerContent, $matches)) {
        $validTypes = explode(',', $matches[1]);
        echo "   📋 Tipos válidos: " . implode(', ', $validTypes) . "\n";
        
        if (in_array('cobrador_payment_received', $validTypes)) {
            echo "   ✅ El tipo 'cobrador_payment_received' es válido\n";
        } else {
            echo "   ❌ El tipo 'cobrador_payment_received' NO es válido\n";
        }
    }
    
    // 5. Verificar las últimas notificaciones creadas
    echo "\n📋 Últimas 5 notificaciones en el sistema:\n";
    $latestNotifications = Notification::with('user')->orderBy('created_at', 'desc')->limit(5)->get();
    
    if ($latestNotifications->count() > 0) {
        foreach ($latestNotifications as $notif) {
            echo "   - ID: {$notif->id}, Para: {$notif->user->name}, Tipo: {$notif->type}\n";
            echo "     Mensaje: " . substr($notif->message, 0, 50) . "...\n";
            echo "     Fecha: {$notif->created_at}\n";
            echo "     ---\n";
        }
    } else {
        echo "   📝 No hay notificaciones en el sistema\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "📍 En: " . $e->getFile() . " línea " . $e->getLine() . "\n";
}

echo "\n✨ Debug completado\n";
