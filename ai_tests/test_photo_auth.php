<?php

// Script simple para probar la autorización de fotos
require_once __DIR__.'/vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "🔍 DEBUGGING AUTORIZACIÓN DE FOTOS\n";
echo "===================================\n\n";

// Obtener el usuario con ID 20 (Fernando Cliente)
$client = User::with(['roles', 'assignedCobrador'])->find(20);
if (! $client) {
    echo "❌ No se encontró el cliente con ID 20\n";
    exit;
}

echo "📋 CLIENTE (ID: {$client->id})\n";
echo "Nombre: {$client->name}\n";
echo "CI: {$client->ci}\n";
echo 'assigned_cobrador_id: '.($client->assigned_cobrador_id ?? 'NULL')."\n";
echo 'Roles: '.$client->roles->pluck('name')->join(', ')."\n\n";

// Buscar el cobrador que debería tener autorización
$cobrador = null;
if ($client->assigned_cobrador_id) {
    $cobrador = User::with('roles')->find($client->assigned_cobrador_id);
    echo "👨‍💼 COBRADOR ASIGNADO (ID: {$cobrador->id})\n";
    echo "Nombre: {$cobrador->name}\n";
    echo 'Roles: '.$cobrador->roles->pluck('name')->join(', ')."\n\n";
} else {
    echo "❌ El cliente NO tiene cobrador asignado\n\n";
}

// Obtener todos los cobradores para verificar cuál debería ser
echo "🔍 TODOS LOS COBRADORES:\n";
$allCobradores = User::whereHas('roles', function ($q) {
    $q->where('name', 'cobrador');
})->with('roles')->get();

foreach ($allCobradores as $c) {
    echo "- ID: {$c->id}, Nombre: {$c->name}\n";
}
echo "\n";

// Simular la función canManageUserMedia
if ($cobrador) {
    echo "🧪 SIMULANDO canManageUserMedia:\n";
    echo 'current->id === target->id: '.($cobrador->id === $client->id ? 'true' : 'false')."\n";
    echo "current->hasRole('admin'): ".($cobrador->hasRole('admin') ? 'true' : 'false')."\n";
    echo "current->hasRole('manager'): ".($cobrador->hasRole('manager') ? 'true' : 'false')."\n";
    echo "current->hasRole('cobrador'): ".($cobrador->hasRole('cobrador') ? 'true' : 'false')."\n";
    echo "target->hasRole('client'): ".($client->hasRole('client') ? 'true' : 'false')."\n";
    echo 'target->assigned_cobrador_id === current->id: '.($client->assigned_cobrador_id === $cobrador->id ? 'true' : 'false')."\n";

    // Resultado final
    $canManage = false;
    if ($cobrador->id === $client->id) {
        $canManage = true;
        echo "✅ AUTORIZADO: Mismo usuario\n";
    } elseif ($cobrador->hasRole('admin') || $cobrador->hasRole('manager')) {
        $canManage = true;
        echo "✅ AUTORIZADO: Admin o Manager\n";
    } elseif ($cobrador->hasRole('cobrador') && $client->hasRole('client') && $client->assigned_cobrador_id === $cobrador->id) {
        $canManage = true;
        echo "✅ AUTORIZADO: Cobrador con cliente asignado\n";
    } else {
        echo "❌ NO AUTORIZADO\n";
    }
}

echo "\n===================================\n";
