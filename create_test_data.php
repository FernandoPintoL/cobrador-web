<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Credit;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "🚀 Creando datos de prueba para route planner...\n\n";

// Coordenadas base (Ciudad de México - Zona Centro)
$baseLat = 19.4326;
$baseLng = -99.1332;

// Buscar cobrador
$cobrador = User::whereHas('roles', function($q) {
    $q->where('name', 'cobrador');
})->first();

if (!$cobrador) {
    echo "❌ No se encontró un cobrador\n";
    exit(1);
}

echo "📋 Usando cobrador: {$cobrador->name} (ID: {$cobrador->id})\n\n";

// Limpiar datos anteriores
echo "🧹 Limpiando datos de prueba anteriores...\n";
$testClients = User::where('email', 'LIKE', 'cliente%@test.com')->get();
foreach ($testClients as $client) {
    $creditIds = Credit::where('client_id', $client->id)->pluck('id');
    Payment::whereIn('credit_id', $creditIds)->delete();
    Credit::where('client_id', $client->id)->delete();
    $client->delete();
}
echo "✅ Datos anteriores eliminados\n\n";

// Datos de clientes
$clientesData = [
    // URGENTES
    ['name' => 'Juan Pérez - VENCIDO', 'lat_offset' => 0.005, 'lng_offset' => 0.005, 'dias_vencido' => 5, 'monto' => 5000, 'category' => 'A'],
    ['name' => 'María González - VENCIDO', 'lat_offset' => -0.003, 'lng_offset' => 0.008, 'dias_vencido' => 10, 'monto' => 8000, 'category' => 'A'],
    ['name' => 'Carlos Rodríguez - VENCIDO', 'lat_offset' => 0.010, 'lng_offset' => -0.003, 'dias_vencido' => 3, 'monto' => 3000, 'category' => 'B'],
    // HOY
    ['name' => 'Ana Martínez - HOY', 'lat_offset' => -0.007, 'lng_offset' => -0.005, 'dias_vencido' => 0, 'monto' => 6000, 'category' => 'A'],
    ['name' => 'Luis Sánchez - HOY', 'lat_offset' => 0.008, 'lng_offset' => 0.010, 'dias_vencido' => 0, 'monto' => 4500, 'category' => 'B'],
    // PRÓXIMOS
    ['name' => 'Pedro López - PRÓXIMO', 'lat_offset' => -0.010, 'lng_offset' => 0.002, 'dias_vencido' => -2, 'monto' => 7000, 'category' => 'A'],
    ['name' => 'Laura Torres - PRÓXIMO', 'lat_offset' => 0.002, 'lng_offset' => -0.008, 'dias_vencido' => -1, 'monto' => 5500, 'category' => 'B'],
];

echo "📍 Creando " . count($clientesData) . " clientes de prueba...\n\n";

foreach ($clientesData as $index => $data) {
    echo "Cliente " . ($index + 1) . ": {$data['name']}\n";

    $lat = $baseLat + $data['lat_offset'];
    $lng = $baseLng + $data['lng_offset'];

    // Crear cliente
    $cliente = User::create([
        'name' => $data['name'],
        'email' => 'cliente' . ($index + 1) . '@test.com',
        'password' => Hash::make('password'),
        'ci' => '1000000' . ($index + 1),
        'phone' => '555' . str_pad($index + 1000, 7, '0', STR_PAD_LEFT),
        'address' => 'Calle Test ' . ($index + 1) . ', Ciudad de México',
        'client_category' => $data['category'],
        'assigned_cobrador_id' => $cobrador->id,
        'latitude' => $lat,
        'longitude' => $lng,
    ]);
    $cliente->assignRole('client');

    echo "  ✓ Coordenadas: ({$lat}, {$lng})\n";

    // Crear crédito ACTIVO (ya entregado)
    $installments = 12;
    $installmentAmount = $data['monto'] / $installments;
    $startDate = now()->subWeeks(4); // Empezó hace 4 semanas

    $credit = Credit::create([
        'client_id' => $cliente->id,
        'created_by' => $cobrador->id,
        'amount' => $data['monto'],
        'balance' => $data['monto'] - ($installmentAmount * 3), // Ya pagó 3 cuotas
        'total_paid' => $installmentAmount * 3,
        'frequency' => 'weekly',
        'start_date' => $startDate,
        'end_date' => $startDate->copy()->addWeeks($installments),
        'status' => 'active',
        'interest_rate' => 20,
        'total_amount' => $data['monto'],
        'installment_amount' => $installmentAmount,
        'total_installments' => $installments,
        'paid_installments' => 3,
        'delivered_at' => $startDate,
        'delivered_by' => $cobrador->id,
    ]);

    echo "  ✓ Crédito ACTIVO creado: \${$data['monto']}\n";

    // Crear 3 pagos anteriores (pagados)
    for ($i = 1; $i <= 3; $i++) {
        Payment::create([
            'credit_id' => $credit->id,
            'installment_number' => $i,
            'amount_due' => $installmentAmount,
            'amount_paid' => $installmentAmount,
            'due_date' => $startDate->copy()->addWeeks($i - 1),
            'payment_date' => $startDate->copy()->addWeeks($i - 1),
            'status' => 'paid',
        ]);
    }

    // Crear pago actual según días de vencimiento
    $dueDate = now()->addDays($data['dias_vencido']);
    $status = $data['dias_vencido'] > 0 ? 'overdue' : 'pending';

    Payment::create([
        'credit_id' => $credit->id,
        'installment_number' => 4,
        'amount_due' => $installmentAmount,
        'amount_paid' => 0,
        'due_date' => $dueDate,
        'payment_date' => null,
        'status' => $status,
    ]);

    if ($data['dias_vencido'] > 0) {
        echo "  🔴 Estado: VENCIDO ({$data['dias_vencido']} días)\n";
    } elseif ($data['dias_vencido'] == 0) {
        echo "  🟡 Estado: HOY\n";
    } else {
        echo "  🟢 Estado: PRÓXIMO (" . abs($data['dias_vencido']) . " días)\n";
    }

    echo "  ✓ Pago programado para: " . $dueDate->format('Y-m-d') . "\n\n";
}

echo "\n✅ Datos de prueba creados exitosamente!\n\n";
echo "📊 Resumen:\n";
echo "  - 3 clientes URGENTES (vencidos)\n";
echo "  - 2 clientes HOY (pago hoy)\n";
echo "  - 2 clientes PRÓXIMOS (pagan en 1-2 días)\n\n";
echo "🎯 Ahora puedes probar la funcionalidad de planificación de rutas!\n";
