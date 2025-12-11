<?php

/**
 * Script de prueba para verificar los nuevos atributos calculados de Credit
 * Ejecutar: php test-credit-attributes.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Credit;

echo "========================================\n";
echo "PRUEBA DE ATRIBUTOS CALCULADOS - CREDIT\n";
echo "========================================\n\n";

// Obtener un crédito de prueba (el primero activo)
$credit = Credit::where('status', 'active')->with('client')->first();

if (!$credit) {
    echo "❌ No se encontró ningún crédito activo para probar\n";
    echo "💡 Crea un crédito activo en la base de datos primero\n";
    exit(1);
}

echo "✅ Crédito encontrado: ID #{$credit->id}\n";
echo "   Cliente: {$credit->client->name}\n";
echo "   Monto: Bs. {$credit->amount}\n";
echo "   Balance: Bs. {$credit->balance}\n";
echo "   Frecuencia: {$credit->frequency}\n";
echo "   Estado: {$credit->status}\n\n";

echo "-----------------------------------\n";
echo "ATRIBUTOS CALCULADOS (Backend):\n";
echo "-----------------------------------\n";

try {
    echo "days_overdue:         " . $credit->days_overdue . " días\n";
    echo "overdue_severity:     " . $credit->overdue_severity . "\n";
    echo "payment_status:       " . $credit->payment_status . "\n";
    echo "overdue_installments: " . $credit->overdue_installments . " cuotas\n";
    echo "requires_attention:   " . ($credit->requires_attention ? 'Sí' : 'No') . "\n";

    echo "\n-----------------------------------\n";
    echo "OTROS DATOS DE CUOTAS:\n";
    echo "-----------------------------------\n";
    echo "Total cuotas:         " . $credit->calculateTotalInstallments() . "\n";
    echo "Cuotas completadas:   " . $credit->getCompletedInstallmentsCount() . "\n";
    echo "Cuotas esperadas:     " . $credit->getExpectedInstallments() . "\n";
    echo "Está atrasado:        " . ($credit->isOverdue() ? 'Sí' : 'No') . "\n";

    echo "\n-----------------------------------\n";
    echo "JSON RESPONSE (simulado):\n";
    echo "-----------------------------------\n";
    echo json_encode([
        'id' => $credit->id,
        'amount' => $credit->amount,
        'balance' => $credit->balance,
        'status' => $credit->status,
        'days_overdue' => $credit->days_overdue,
        'overdue_severity' => $credit->overdue_severity,
        'payment_status' => $credit->payment_status,
        'overdue_installments' => $credit->overdue_installments,
        'requires_attention' => $credit->requires_attention,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    echo "\n✅ TODOS LOS ATRIBUTOS FUNCIONAN CORRECTAMENTE\n";

} catch (\Exception $e) {
    echo "\n❌ ERROR al calcular atributos:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n========================================\n";
echo "MAPEO DE SEVERIDAD A UI (Frontend):\n";
echo "========================================\n\n";

$severityMap = [
    'none' => ['color' => 'verde', 'icon' => 'check_circle', 'label' => 'Al día'],
    'light' => ['color' => 'amarillo', 'icon' => 'warning_amber', 'label' => 'Alerta leve'],
    'moderate' => ['color' => 'naranja', 'icon' => 'warning', 'label' => 'Alerta moderada'],
    'critical' => ['color' => 'rojo', 'icon' => 'error', 'label' => 'Crítico'],
];

$severity = $credit->overdue_severity;
$ui = $severityMap[$severity] ?? ['color' => 'gris', 'icon' => 'help', 'label' => 'Desconocido'];

echo "Severidad actual: {$severity}\n";
echo "  → Color:  {$ui['color']}\n";
echo "  → Icono:  {$ui['icon']}\n";
echo "  → Label:  {$ui['label']}\n";

echo "\n========================================\n";
echo "PRUEBA COMPLETADA ✅\n";
echo "========================================\n";
