<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Models\Credit;

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ACTIVAR CRÉDITO PARA PRUEBAS ===\n\n";

// Buscar el crédito en pending_approval
$credit = Credit::where('status', 'pending_approval')->first();

if (! $credit) {
    echo "❌ No se encontró ningún crédito pendiente de aprobación\n";
    exit;
}

echo "📋 Crédito encontrado:\n";
echo "  - ID: {$credit->id}\n";
echo "  - Cliente: {$credit->client->name}\n";
echo "  - Monto: {$credit->amount}\n";
echo "  - Estado actual: {$credit->status}\n\n";

// Activar el crédito
$credit->update([
    'status' => 'active',
    'approved_by' => $credit->created_by, // Usar el mismo usuario que lo creó
    'approved_at' => now(),
    'delivered_by' => $credit->created_by,
    'delivered_at' => now(),
]);

echo "✅ Crédito activado exitosamente!\n";
echo "  - Nuevo estado: {$credit->fresh()->status}\n";
echo "  - Aprobado por: {$credit->createdBy->name}\n";
echo "  - Fecha de aprobación: {$credit->fresh()->approved_at}\n\n";

echo "🎯 SOLUCIÓN: El crédito ahora debe aparecer en el frontend\n";
echo "   cuando el cobrador liste sus créditos activos.\n\n";

echo "=== COMPLETADO ===\n";
