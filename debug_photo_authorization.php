<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use Illuminate\Support\Facades\Log;

// Configurar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 DEBUG: Investigando autorización de fotos\n";
echo "==========================================\n\n";

// Obtener información del cliente con ID 20
$client = User::with(['roles', 'assignedCobrador'])->find(20);

if (!$client) {
    echo "❌ No se encontró el cliente con ID 20\n";
    exit;
}

echo "📋 INFORMACIÓN DEL CLIENTE (ID: 20)\n";
echo "Nombre: {$client->name}\n";
echo "CI: {$client->ci}\n";
echo "Assigned Cobrador ID: " . ($client->assigned_cobrador_id ?? 'NULL') . "\n";
echo "Roles: " . $client->roles->pluck('name')->join(', ') . "\n";

if ($client->assignedCobrador) {
    echo "\n👨‍💼 INFORMACIÓN DEL COBRADOR ASIGNADO\n";
    echo "ID: {$client->assignedCobrador->id}\n";
    echo "Nombre: {$client->assignedCobrador->name}\n";
    echo "Roles: " . $client->assignedCobrador->roles->pluck('name')->join(', ') . "\n";
} else {
    echo "\n❌ No tiene cobrador asignado\n";
}

echo "\n🔍 TODOS LOS COBRADORES EN EL SISTEMA:\n";
$cobradores = User::whereHas('roles', function($q) {
    $q->where('name', 'cobrador');
})->with('roles')->get();

foreach ($cobradores as $cobrador) {
    echo "- ID: {$cobrador->id}, Nombre: {$cobrador->name}\n";
}

echo "\n🔍 TODOS LOS CLIENTES ASIGNADOS A COBRADORES:\n";
$clients = User::whereHas('roles', function($q) {
    $q->where('name', 'client');
})->whereNotNull('assigned_cobrador_id')->with(['roles', 'assignedCobrador'])->get();

foreach ($clients as $clientWithCobrador) {
    $cobradorName = $clientWithCobrador->assignedCobrador ? $clientWithCobrador->assignedCobrador->name : 'No asignado';
    echo "- Cliente ID: {$clientWithCobrador->id}, Nombre: {$clientWithCobrador->name}, Cobrador ID: {$clientWithCobrador->assigned_cobrador_id}, Cobrador: {$cobradorName}\n";
}

// Simular la función de autorización
echo "\n🔍 SIMULANDO AUTORIZACIÓN:\n";

// Obtener el cobrador que podría ser el autenticado
$possibleCobrador = User::whereHas('roles', function($q) {
    $q->where('name', 'cobrador');
})->first();

if ($possibleCobrador) {
    echo "Simulando autorización con cobrador ID: {$possibleCobrador->id} ({$possibleCobrador->name})\n";

    // Simular la lógica de canManageUserMedia
    $canManage = false;

    // 1. ¿Es el mismo usuario?
    if ($possibleCobrador->id === $client->id) {
        $canManage = true;
        echo "✅ Puede gestionar: Es el mismo usuario\n";
    }

    // 2. ¿Es admin o manager?
    if ($possibleCobrador->hasRole('admin') || $possibleCobrador->hasRole('manager')) {
        $canManage = true;
        echo "✅ Puede gestionar: Es admin o manager\n";
    }

    // 3. ¿Es cobrador y el cliente está asignado a él?
    if ($possibleCobrador->hasRole('cobrador') &&
        $client->hasRole('client') &&
        $client->assigned_cobrador_id === $possibleCobrador->id) {
        $canManage = true;
        echo "✅ Puede gestionar: Es cobrador y el cliente está asignado a él\n";
    } else if ($possibleCobrador->hasRole('cobrador')) {
        echo "❌ No puede gestionar: Es cobrador pero el cliente no está asignado a él\n";
        echo "   - Cliente assigned_cobrador_id: " . ($client->assigned_cobrador_id ?? 'NULL') . "\n";
        echo "   - Cobrador ID: {$possibleCobrador->id}\n";
        echo "   - Cliente tiene rol 'client': " . ($client->hasRole('client') ? 'Sí' : 'No') . "\n";
        echo "   - Cobrador tiene rol 'cobrador': " . ($possibleCobrador->hasRole('cobrador') ? 'Sí' : 'No') . "\n";
    }

    echo "Resultado final: " . ($canManage ? "✅ AUTORIZADO" : "❌ NO AUTORIZADO") . "\n";
}

echo "\n===========================================\n";
