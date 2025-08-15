<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Credit;
use App\Models\Payment;
use App\Services\WebSocketNotificationService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

// Inicializar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PRUEBA COMPLETA DEL SISTEMA WEBSOCKET MEJORADO ===\n";

try {
    $webSocketService = App::make(WebSocketNotificationService::class);
    
    // 1. Probar conectividad básica
    echo "\n1. Probando conectividad con WebSocket server...\n";
    $health = $webSocketService->testConnection();
    echo "Conexión: " . ($health['connected'] ? 'EXITOSA' : 'FALLIDA') . "\n";
    echo "URL: " . $health['url'] . "\n";
    
    if (!$health['connected']) {
        echo "Error: " . ($health['error'] ?? 'Desconocido') . "\n";
        echo "NOTA: Asegúrate de que el servidor WebSocket esté ejecutándose en puerto 3001\n";
        echo "Comando: cd websocket-server && node server.js\n";
        return;
    }
    
    // 2. Buscar usuarios de prueba
    echo "\n2. Buscando usuarios de prueba...\n";
    
    $manager = User::role('manager')->first();
    $cobrador = User::role('cobrador')->first();
    $client = User::role('client')->first();
    
    if (!$manager || !$cobrador || !$client) {
        echo "ERROR: No se encontraron usuarios con los roles necesarios\n";
        echo "Manager: " . ($manager ? $manager->name : 'NO ENCONTRADO') . "\n";
        echo "Cobrador: " . ($cobrador ? $cobrador->name : 'NO ENCONTRADO') . "\n";
        echo "Cliente: " . ($client ? $client->name : 'NO ENCONTRADO') . "\n";
        return;
    }
    
    echo "Manager: {$manager->name} (ID: {$manager->id})\n";
    echo "Cobrador: {$cobrador->name} (ID: {$cobrador->id})\n";
    echo "Cliente: {$client->name} (ID: {$client->id})\n";
    
    // Verificar que el cobrador esté asignado al manager
    if (!$cobrador->assignedManager || $cobrador->assignedManager->id !== $manager->id) {
        echo "Asignando cobrador al manager...\n";
        $cobrador->update(['assigned_manager_id' => $manager->id]);
        $cobrador->refresh();
    }
    
    // 3. Buscar o crear un crédito de prueba
    echo "\n3. Preparando crédito de prueba...\n";
    
    // Buscar un crédito que tenga el cliente correcto
    $credit = Credit::where('client_id', $client->id)->first();
    
    if (!$credit) {
        echo "No se encontró crédito existente, creando uno nuevo...\n";
        $credit = Credit::create([
            'client_id' => $client->id,
            'created_by' => $cobrador->id, // El cobrador que crea el crédito
            'amount' => 1000.00,
            'interest_rate' => 15.0,
            'frequency' => 'weekly',
            'start_date' => now(),
            'end_date' => now()->addWeeks(4),
            'status' => 'pending',
            'installment_amount' => 287.50,
            'total_amount' => 1150.00,
            'balance' => 1150.00
        ]);
    }
    
    // Asegurar que el cliente esté asignado al cobrador
    if (!$client->assignedCobrador || $client->assignedCobrador->id !== $cobrador->id) {
        echo "Asignando cliente al cobrador...\n";
        $client->update(['assigned_cobrador_id' => $cobrador->id]);
        $client->refresh(); // Refrescar el modelo para obtener la relación actualizada
    }
    
    echo "Crédito de prueba: ID {$credit->id}, Monto: {$credit->amount} Bs\n";
    echo "Cliente: {$client->name} (ID: {$client->id})\n";
    echo "Creado por: {$credit->createdBy->name} (ID: {$credit->created_by})\n";
    echo "Cobrador asignado: {$client->assignedCobrador->name} (ID: {$client->assignedCobrador->id})\n";
    
    // 4. Probar notificación de crédito
    echo "\n4. Probando notificación de ciclo de vida del crédito...\n";
    
    $creditActions = ['created', 'approved', 'delivered', 'requires_attention'];
    
    foreach ($creditActions as $action) {
        echo "Enviando notificación de crédito: $action\n";
        $result = $webSocketService->sendCreditNotification(
            $credit,
            $action,
            $manager,
            $manager,
            $cobrador
        );
        echo "Resultado: " . ($result ? 'EXITOSO' : 'FALLIDO') . "\n";
        usleep(500000); // Esperar 0.5 segundos entre notificaciones
    }
    
    // 5. Buscar o crear un pago de prueba
    echo "\n5. Preparando pago de prueba...\n";
    
    $payment = Payment::where('credit_id', $credit->id)->first();
    
    if (!$payment) {
        echo "No se encontró pago existente, creando uno nuevo...\n";
        $payment = Payment::create([
            'credit_id' => $credit->id,
            'client_id' => $client->id, // Agregar client_id requerido
            'cobrador_id' => $cobrador->id, // Agregar cobrador_id requerido
            'installment_number' => 1, // Agregar installment_number requerido
            'amount' => 287.50,
            'payment_date' => now(),
            'payment_method' => 'cash',
            'status' => 'pending',
            'notes' => 'Pago de prueba WebSocket'
        ]);
    }
    
    echo "Pago de prueba: ID {$payment->id}, Monto: {$payment->amount} Bs\n";
    
    // 6. Probar notificación de pago
    echo "\n6. Probando notificación de pago...\n";
    
    $result = $webSocketService->sendPaymentNotification(
        $payment,
        $cobrador,
        $manager
    );
    echo "Resultado notificación de pago: " . ($result ? 'EXITOSO' : 'FALLIDO') . "\n";
    
    // 7. Probar método legacy de compatibilidad
    echo "\n7. Probando método legacy de pago (compatibilidad)...\n";
    
    $result = $webSocketService->sendPaymentReceived($payment, $cobrador);
    echo "Resultado método legacy: " . ($result ? 'EXITOSO' : 'FALLIDO') . "\n";
    
    // 8. Probar notificación de atención de crédito
    echo "\n8. Probando notificación de atención de crédito...\n";
    
    $result = $webSocketService->sendCreditAttention($credit, $cobrador);
    echo "Resultado atención de crédito: " . ($result ? 'EXITOSO' : 'FALLIDO') . "\n";
    
    // 9. Probar notificación de ubicación
    echo "\n9. Probando notificación de ubicación...\n";
    
    $result = $webSocketService->sendLocationUpdate($cobrador->id, -17.7833, -63.1821);
    echo "Resultado ubicación: " . ($result ? 'EXITOSO' : 'FALLIDO') . "\n";
    
    // 10. Probar mensaje directo
    echo "\n10. Probando mensaje directo...\n";
    
    $result = $webSocketService->sendMessage(
        $manager->id,
        $cobrador->id,
        "Mensaje de prueba del sistema WebSocket mejorado"
    );
    echo "Resultado mensaje: " . ($result ? 'EXITOSO' : 'FALLIDO') . "\n";
    
    echo "\n=== PRUEBA COMPLETADA ===\n";
    echo "Todos los componentes del sistema WebSocket han sido probados.\n";
    echo "Revisa los logs del servidor WebSocket para ver las notificaciones en tiempo real.\n";
    
    // Mostrar resumen de endpoints utilizados
    echo "\n=== RESUMEN DE ENDPOINTS PROBADOS ===\n";
    echo "- /health (conectividad)\n";
    echo "- /credit-notification (ciclo de vida de créditos)\n";
    echo "- /payment-notification (notificaciones de pago)\n";
    echo "- /notify (notificaciones generales: atención, ubicación, mensajes)\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}
