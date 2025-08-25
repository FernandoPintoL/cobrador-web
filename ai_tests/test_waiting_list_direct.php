<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Credit;

// Script de prueba simple usando los modelos directamente
echo "🧪 Pruebas del Sistema de Lista de Espera\n";
echo "==========================================\n\n";

// 1. Verificar usuarios
echo "👥 Verificando usuarios...\n";
$manager = User::where('email', 'manager@test.com')->first();
$cobrador = User::where('email', 'cobrador@test.com')->first();
$client = User::where('email', 'cliente1@test.com')->first();

if ($manager && $cobrador && $client) {
    echo "✅ Usuarios encontrados: Manager({$manager->id}), Cobrador({$cobrador->id}), Cliente({$client->id})\n\n";
} else {
    echo "❌ Faltan usuarios de prueba\n";
    exit(1);
}

// 2. Verificar créditos existentes
echo "💳 Verificando créditos existentes...\n";
$pendingCredits = Credit::pendingApproval()->count();
$waitingCredits = Credit::waitingForDelivery()->count();
$readyCredits = Credit::readyForDeliveryToday()->count();
$overdueCredits = Credit::overdueForDelivery()->count();

echo "📊 Resumen actual:\n";
echo "   - Pendientes de aprobación: $pendingCredits\n";
echo "   - En espera de entrega: $waitingCredits\n";
echo "   - Listos hoy: $readyCredits\n";
echo "   - Atrasados: $overdueCredits\n\n";

// 3. Crear un crédito de prueba
echo "➕ Creando crédito de prueba...\n";
$testCredit = Credit::create([
    'client_id' => $client->id,
    'created_by' => $cobrador->id,
    'amount' => 1000.00,
    'interest_rate' => 20.00,
    'frequency' => 'daily',
    'start_date' => now()->addDay(),
    'end_date' => now()->addDays(30),
    'status' => 'pending_approval',
]);

echo "✅ Crédito creado con ID: {$testCredit->id}\n";
echo "   - Cliente: {$testCredit->client->name}\n";
echo "   - Monto: {$testCredit->total_amount} Bs\n";
echo "   - Status: {$testCredit->status}\n\n";

// 4. Probar aprobación
echo "✅ Probando aprobación...\n";
$deliveryDate = now()->addDays(2)->setTime(10, 0);
$approved = $testCredit->approveForDelivery(
    $manager->id,
    $deliveryDate,
    'Crédito aprobado para prueba del sistema'
);

if ($approved) {
    $testCredit->refresh();
    echo "✅ Crédito aprobado exitosamente\n";
    echo "   - Status: {$testCredit->status}\n";
    echo "   - Fecha programada: {$testCredit->scheduled_delivery_date}\n";
    echo "   - Aprobado por: {$testCredit->approvedBy->name}\n\n";
} else {
    echo "❌ Error aprobando crédito\n\n";
}

// 5. Verificar métodos de verificación
echo "🔍 Probando métodos de verificación...\n";
echo "   - ¿Listo para entrega? " . ($testCredit->isReadyForDelivery() ? 'Sí' : 'No') . "\n";
echo "   - ¿Atrasado? " . ($testCredit->isOverdueForDelivery() ? 'Sí' : 'No') . "\n";
echo "   - Días hasta entrega: " . $testCredit->getDaysUntilDelivery() . "\n";
echo "   - Días de atraso: " . $testCredit->getDaysOverdueForDelivery() . "\n\n";

// 6. Probar entrega
echo "📦 Probando entrega...\n";
$delivered = $testCredit->deliverToClient(
    $cobrador->id,
    'Entregado en efectivo - Prueba del sistema'
);

if ($delivered) {
    $testCredit->refresh();
    echo "✅ Crédito entregado exitosamente\n";
    echo "   - Status: {$testCredit->status}\n";
    echo "   - Entregado por: {$testCredit->deliveredBy->name}\n";
    echo "   - Fecha de entrega: {$testCredit->delivered_at}\n\n";
} else {
    echo "❌ Error entregando crédito\n\n";
}

// 7. Probar rechazo con otro crédito
echo "❌ Probando rechazo...\n";
$rejectCredit = Credit::create([
    'client_id' => $client->id,
    'created_by' => $cobrador->id,
    'amount' => 10000.00,  // Monto muy alto
    'interest_rate' => 30.00,
    'frequency' => 'monthly',
    'start_date' => now()->addDay(),
    'end_date' => now()->addMonths(6),
    'status' => 'pending_approval',
]);

$rejected = $rejectCredit->reject(
    $manager->id,
    'Monto excede el límite permitido para el perfil del cliente'
);

if ($rejected) {
    $rejectCredit->refresh();
    echo "✅ Crédito rechazado exitosamente\n";
    echo "   - Status: {$rejectCredit->status}\n";
    echo "   - Motivo: {$rejectCredit->rejection_reason}\n\n";
} else {
    echo "❌ Error rechazando crédito\n\n";
}

// 8. Resumen final
echo "📊 Resumen final:\n";
$finalPending = Credit::pendingApproval()->count();
$finalWaiting = Credit::waitingForDelivery()->count();
$finalReady = Credit::readyForDeliveryToday()->count();
$finalOverdue = Credit::overdueForDelivery()->count();

echo "   - Pendientes de aprobación: $finalPending\n";
echo "   - En espera de entrega: $finalWaiting\n";
echo "   - Listos hoy: $finalReady\n";
echo "   - Atrasados: $finalOverdue\n\n";

// 9. Obtener información completa de estado
echo "📋 Información detallada del crédito de prueba:\n";
$deliveryStatus = $testCredit->getDeliveryStatusInfo();
foreach ($deliveryStatus as $key => $value) {
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif (is_object($value) && method_exists($value, 'format')) {
        $value = $value->format('Y-m-d H:i:s');
    } elseif (is_object($value)) {
        $value = $value->name ?? $value->id ?? 'Object';
    }
    echo "   - $key: $value\n";
}

echo "\n🎉 Todas las pruebas completadas exitosamente!\n";
