<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "🚀 Creando clientes de prueba con coordenadas...\n\n";

// Coordenadas base (tu ubicación actual o una central)
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

// Limpiar clientes de prueba anteriores
echo "🧹 Limpiando clientes de prueba anteriores...\n";
User::where('email', 'LIKE', 'clientetest%@test.com')->delete();
echo "✅ Limpiado\n\n";

// Datos de clientes con diferentes ubicaciones
$clientesData = [
    ['name' => 'Cliente Test 1', 'lat_offset' => 0.005, 'lng_offset' => 0.005],
    ['name' => 'Cliente Test 2', 'lat_offset' => -0.003, 'lng_offset' => 0.008],
    ['name' => 'Cliente Test 3', 'lat_offset' => 0.010, 'lng_offset' => -0.003],
    ['name' => 'Cliente Test 4', 'lat_offset' => -0.007, 'lng_offset' => -0.005],
    ['name' => 'Cliente Test 5', 'lat_offset' => 0.008, 'lng_offset' => 0.010],
    ['name' => 'Cliente Test 6', 'lat_offset' => -0.010, 'lng_offset' => 0.002],
    ['name' => 'Cliente Test 7', 'lat_offset' => 0.002, 'lng_offset' => -0.008],
];

echo "📍 Creando " . count($clientesData) . " clientes...\n\n";

foreach ($clientesData as $index => $data) {
    $lat = $baseLat + $data['lat_offset'];
    $lng = $baseLng + $data['lng_offset'];

    $cliente = User::create([
        'name' => $data['name'],
        'email' => 'clientetest' . ($index + 1) . '@test.com',
        'password' => Hash::make('password'),
        'ci' => '2000000' . ($index + 1),
        'phone' => '555' . str_pad($index + 2000, 7, '0', STR_PAD_LEFT),
        'address' => 'Calle Prueba ' . ($index + 1) . ', CDMX',
        'client_category' => ($index % 2 == 0) ? 'A' : 'B',
        'assigned_cobrador_id' => $cobrador->id,
        'latitude' => $lat,
        'longitude' => $lng,
    ]);
    $cliente->assignRole('client');

    echo "✓ {$data['name']}: ({$lat}, {$lng})\n";
}

echo "\n✅ ¡" . count($clientesData) . " clientes creados exitosamente!\n";
echo "🎯 Ahora tienes clientes con coordenadas para probar el mapa\n";
echo "📝 Nota: Estos clientes no tienen créditos asignados todavía\n";
