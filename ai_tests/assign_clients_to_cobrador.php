<?php

require_once __DIR__ . '/vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔗 Asignando clientes a cobrador...\n";

// Encontrar un cobrador
$cobrador = App\Models\User::whereHas('roles', function ($q) {
    $q->where('name', 'cobrador');
})->first();

if (!$cobrador) {
    echo "❌ No se encontró ningún cobrador\n";
    exit(1);
}

echo "✅ Cobrador encontrado: {$cobrador->name} (ID: {$cobrador->id})\n";

// Encontrar algunos clientes
$clients = App\Models\User::whereHas('roles', function ($q) {
    $q->where('name', 'client');
})->whereNull('assigned_cobrador_id')->limit(2)->get();

echo "📋 Clientes disponibles: {$clients->count()}\n";

if ($clients->isEmpty()) {
    echo "⚠️  No hay clientes disponibles para asignar\n";
    exit(0);
}

// Asignar clientes al cobrador
foreach ($clients as $client) {
    $client->update(['assigned_cobrador_id' => $cobrador->id]);
    echo "✅ Cliente asignado: {$client->name} (ID: {$client->id})\n";
}

echo "\n🎉 Asignación completada! Ahora ejecuta de nuevo el test de créditos.\n";
