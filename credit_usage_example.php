<?php

/**
 * Ejemplo de uso del sistema de créditos con intereses y cuotas flexibles
 * 
 * Escenario: Crédito de 1000 Bs con 20% de interés, pagadero en 24 días
 * Total a pagar: 1200 Bs (50 Bs diarios)
 */

// Crear un crédito
$credit = Credit::create([
    'client_id' => 1,
    'created_by' => 2, // ID del cobrador
    'amount' => 1000.00, // Monto original
    'interest_rate' => 20.00, // 20% de interés
    'frequency' => 'daily',
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-24', // 24 días
    'status' => 'active',
]);

// El modelo automáticamente calculará:
// - total_amount = 1200.00 (1000 + 20%)
// - installment_amount = 50.00 (1200 / 24 días)

echo "=== INFORMACIÓN DEL CRÉDITO ===\n";
echo "Monto original: {$credit->amount} Bs\n";
echo "Tasa de interés: {$credit->interest_rate}%\n";
echo "Monto total con interés: {$credit->calculateTotalAmount()} Bs\n";
echo "Cuota diaria: {$credit->calculateInstallmentAmount()} Bs\n";
echo "Total de cuotas: {$credit->calculateTotalInstallments()}\n\n";

// Simular pagos
echo "=== SIMULACIÓN DE PAGOS ===\n";

// Día 1-4: Pagos regulares de 50 Bs
for ($day = 1; $day <= 4; $day++) {
    $paymentResult = $credit->processPayment(50.00);
    
    // Crear el pago en la base de datos
    $credit->payments()->create([
        'cobrador_id' => 2,
        'amount' => 50.00,
        'status' => 'completed',
        'payment_date' => "2025-01-0{$day}",
        'payment_type' => 'cash',
    ]);
    
    echo "Día {$day}: Pago de 50 Bs - {$paymentResult['message']}\n";
}

// Actualizar balance después de los pagos
$credit->refresh();
echo "\nBalance después de 4 pagos: {$credit->getCurrentBalance()} Bs\n";
echo "Cuotas pendientes: {$credit->getPendingInstallments()}\n\n";

// Día 5: Pago especial de 190 Bs (casi 4 cuotas)
echo "=== PAGO ESPECIAL DEL DÍA 5 ===\n";
$specialPayment = $credit->processPayment(190.00);

echo "Pago de 190 Bs:\n";
echo "- Tipo: {$specialPayment['type']}\n";
echo "- Cuotas cubiertas: {$specialPayment['installments_covered']}\n";
echo "- Mensaje: {$specialPayment['message']}\n";
echo "- Balance restante: {$specialPayment['remaining_balance']} Bs\n\n";

// Crear el pago especial
$credit->payments()->create([
    'cobrador_id' => 2,
    'amount' => 190.00,
    'status' => 'completed',
    'payment_date' => '2025-01-05',
    'payment_type' => 'cash',
    'notes' => 'Pago adelantado de múltiples cuotas'
]);

// Estado después del pago especial
$credit->refresh();
echo "=== ESTADO DESPUÉS DEL PAGO ESPECIAL ===\n";
echo "Total pagado: {$credit->getTotalPaidAmount()} Bs\n";
echo "Balance restante: {$credit->getCurrentBalance()} Bs\n";
echo "Cuotas completadas: " . $credit->payments()->where('status', 'completed')->count() . "\n";
echo "Cuotas pendientes: {$credit->getPendingInstallments()}\n\n";

// Cronograma de pagos
echo "=== CRONOGRAMA DE PAGOS ===\n";
$schedule = $credit->getPaymentSchedule();

foreach (array_slice($schedule, 0, 10) as $installment) { // Mostrar primeras 10 cuotas
    echo "Cuota {$installment['installment_number']}: {$installment['due_date']} - {$installment['amount']} Bs\n";
}

echo "\n=== VERIFICACIÓN DE ATRASOS ===\n";
// Simular fecha actual del día 6
Carbon::setTestNow('2025-01-06');

echo "¿Está atrasado? " . ($credit->isOverdue() ? 'SÍ' : 'NO') . "\n";
echo "Cuotas esperadas hasta hoy: {$credit->getExpectedInstallments()}\n";
echo "Monto de atraso: {$credit->getOverdueAmount()} Bs\n";

// Ejemplo de diferentes tipos de pago
echo "\n=== EJEMPLOS DE DIFERENTES TIPOS DE PAGO ===\n";

$examples = [
    25.00 => 'Pago parcial',
    50.00 => 'Pago regular',
    100.00 => 'Pago de 2 cuotas',
    500.00 => 'Pago adelantado',
    1500.00 => 'Pago excesivo (más que el balance)',
];

foreach ($examples as $amount => $description) {
    $result = $credit->processPayment($amount);
    echo "{$description} ({$amount} Bs): {$result['message']}\n";
}
