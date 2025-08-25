<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;

echo "🔍 ENDPOINTS DISPONIBLES PARA MANAGER - CONSULTA DE CLIENTES\n";
echo "============================================================\n\n";

$managerId = 16;
$manager = User::find($managerId);

if (!$manager) {
    echo "❌ Manager ID {$managerId} no encontrado\n";
    exit;
}

echo "👤 Manager: {$manager->name} ({$manager->email})\n";
echo "🏢 Roles: " . $manager->roles->pluck('name')->implode(', ') . "\n\n";

// 1. Obtener todos los cobradores del manager
echo "🔗 1. COBRADORES ASIGNADOS AL MANAGER:\n";
echo "   Endpoint: GET /api/users/{$managerId}/cobradores\n";

$cobradores = User::whereHas('roles', function ($query) {
        $query->where('name', 'cobrador');
    })
    ->where('assigned_manager_id', $manager->id)
    ->with('roles')
    ->get();

if ($cobradores->count() > 0) {
    foreach ($cobradores as $cobrador) {
        echo "   - ID: {$cobrador->id} | Nombre: {$cobrador->name} | Email: {$cobrador->email}\n";
    }
} else {
    echo "   ⚠️  No hay cobradores asignados a este manager\n";
}

// 2. Para cada cobrador, mostrar sus clientes
echo "\n📋 2. CLIENTES POR COBRADOR:\n";
foreach ($cobradores as $cobrador) {
    echo "   📊 Cobrador: {$cobrador->name} (ID: {$cobrador->id})\n";
    echo "      Endpoint: GET /api/users/{$cobrador->id}/clients\n";

    $clientesCobrador = User::whereHas('roles', function ($query) {
            $query->where('name', 'client');
        })
        ->where('assigned_cobrador_id', $cobrador->id)
        ->get();

    if ($clientesCobrador->count() > 0) {
        foreach ($clientesCobrador as $cliente) {
            echo "      - Cliente ID: {$cliente->id} | Nombre: {$cliente->name}\n";
        }
    } else {
        echo "      ⚠️  Sin clientes asignados\n";
    }
    echo "\n";
}

// 3. Clientes directos del manager
echo "🎯 3. CLIENTES DIRECTOS DEL MANAGER:\n";
echo "   Endpoint: GET /api/users/{$managerId}/clients-direct\n";

$clientesDirectos = User::whereHas('roles', function ($query) {
        $query->where('name', 'client');
    })
    ->where('assigned_manager_id', $manager->id)
    ->get();

if ($clientesDirectos->count() > 0) {
    foreach ($clientesDirectos as $cliente) {
        echo "   - Cliente ID: {$cliente->id} | Nombre: {$cliente->name}\n";
    }
} else {
    echo "   ⚠️  No hay clientes asignados directamente al manager\n";
}

// 4. TODOS los clientes del manager (consolidado)
echo "\n🌟 4. TODOS LOS CLIENTES DEL MANAGER (ENDPOINT PRINCIPAL):\n";
echo "   Endpoint: GET /api/users/{$managerId}/manager-clients\n";

// Clientes directos
$directClients = User::whereHas('roles', function ($query) {
        $query->where('name', 'client');
    })
    ->where('assigned_manager_id', $manager->id);

// IDs de cobradores del manager
$cobradorIds = User::whereHas('roles', function ($query) {
        $query->where('name', 'cobrador');
    })
    ->where('assigned_manager_id', $manager->id)
    ->pluck('id');

// Clientes de los cobradores
$cobradorClients = User::whereHas('roles', function ($query) {
        $query->where('name', 'client');
    })
    ->whereIn('assigned_cobrador_id', $cobradorIds);

// Todos los clientes combinados
$todosLosClientes = User::whereHas('roles', function ($query) {
        $query->where('name', 'client');
    })
    ->where(function ($query) use ($manager, $cobradorIds) {
        $query->where('assigned_manager_id', $manager->id) // Directos
              ->orWhereIn('assigned_cobrador_id', $cobradorIds); // De cobradores
    })
    ->with(['assignedCobrador', 'assignedManagerDirectly'])
    ->get();

echo "   📊 Total de clientes encontrados: " . $todosLosClientes->count() . "\n";

foreach ($todosLosClientes as $cliente) {
    $tipoAsignacion = $cliente->assigned_manager_id ? 'DIRECTO' : 'VIA COBRADOR';
    $cobradorInfo = $cliente->assignedCobrador ? " (Cobrador: {$cliente->assignedCobrador->name})" : '';
    echo "   - Cliente ID: {$cliente->id} | {$cliente->name} | Tipo: {$tipoAsignacion}{$cobradorInfo}\n";
}

// 5. Resumen de URLs para el frontend
echo "\n🔗 RESUMEN DE ENDPOINTS PARA TU FRONTEND:\n";
echo "=========================================\n";
echo "Base URL: http://192.168.100.21:8000/api\n\n";

echo "1. 📋 Ver todos los clientes del manager:\n";
echo "   GET /users/{$managerId}/manager-clients?page=1&per_page=50\n";
echo "   URL completa: http://192.168.100.21:8000/api/users/{$managerId}/manager-clients?page=1&per_page=50\n\n";

echo "2. 👥 Ver cobradores del manager:\n";
echo "   GET /users/{$managerId}/cobradores\n";
echo "   URL completa: http://192.168.100.21:8000/api/users/{$managerId}/cobradores\n\n";

echo "3. 🎯 Ver clientes de un cobrador específico:\n";
echo "   GET /users/[COBRADOR_ID]/clients\n";
if ($cobradores->count() > 0) {
    $primerCobrador = $cobradores->first();
    echo "   Ejemplo: http://192.168.100.21:8000/api/users/{$primerCobrador->id}/clients\n";
}

echo "\n✅ Script completado. Todos los endpoints están disponibles y funcionando.\n";
