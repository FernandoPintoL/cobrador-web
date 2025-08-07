<?php

use App\Models\User;
use Illuminate\Http\Request;

// Script para diagnosticar el problema con la ruta /api/users/14/clients

echo "🔍 Diagnóstico de la ruta /api/users/14/clients\n\n";

// 1. Verificar que el usuario con ID 14 existe
$user14 = User::find(14);
if (!$user14) {
    echo "❌ ERROR: No existe un usuario con ID 14\n";
    echo "💡 Usuarios disponibles:\n";
    User::all()->each(function($user) {
        echo "   - ID: {$user->id}, Nombre: {$user->name}, Roles: " . $user->roles->pluck('name')->implode(', ') . "\n";
    });
    exit;
}

echo "✅ Usuario encontrado: {$user14->name}\n";
echo "📋 Roles: " . $user14->roles->pluck('name')->implode(', ') . "\n\n";

// 2. Verificar si es un manager
$isManager = $user14->hasRole('manager');
echo "👤 ¿Es manager? " . ($isManager ? "✅ SÍ" : "❌ NO") . "\n";

if (!$isManager) {
    echo "⚠️  PROBLEMA IDENTIFICADO: El usuario con ID 14 no tiene el rol 'manager'\n";
    echo "💡 Para usar la ruta /api/users/{manager}/clients, el usuario debe tener el rol 'manager'\n\n";
    echo "🔧 Roles disponibles del usuario:\n";
    $user14->roles->each(function($role) {
        echo "   - {$role->name}\n";
    });
    
    // Sugerir usuarios que sí son managers
    echo "\n🎯 Usuarios que SÍ son managers:\n";
    User::whereHas('roles', function($q) {
        $q->where('name', 'manager');
    })->each(function($manager) {
        echo "   - ID: {$manager->id}, Nombre: {$manager->name}\n";
    });
} else {
    echo "✅ El usuario es manager, continuando diagnóstico...\n\n";
    
    // 3. Verificar cobradores asignados
    $assignedCobradores = $user14->assignedCobradores;
    echo "👥 Cobradores asignados: " . $assignedCobradores->count() . "\n";
    $assignedCobradores->each(function($cobrador) {
        echo "   - {$cobrador->name} (ID: {$cobrador->id})\n";
    });
    
    // 4. Verificar clientes directos
    $directClients = $user14->assignedClientsDirectly;
    echo "\n👤 Clientes directos: " . $directClients->count() . "\n";
    $directClients->each(function($client) {
        echo "   - {$client->name} (ID: {$client->id})\n";
    });
    
    // 5. Verificar clientes a través de cobradores
    $cobradorIds = $assignedCobradores->pluck('id');
    $cobradorClients = User::whereHas('roles', function($q) {
        $q->where('name', 'client');
    })->whereIn('assigned_cobrador_id', $cobradorIds)->get();
    
    echo "\n👥 Clientes a través de cobradores: " . $cobradorClients->count() . "\n";
    $cobradorClients->each(function($client) {
        $cobradorName = $client->assignedCobrador ? $client->assignedCobrador->name : 'Sin cobrador';
        echo "   - {$client->name} (ID: {$client->id}) → Cobrador: {$cobradorName}\n";
    });
    
    $totalClients = $directClients->count() + $cobradorClients->count();
    echo "\n📊 Total de clientes del manager: {$totalClients}\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🔧 Soluciones recomendadas:\n\n";

if (!$isManager) {
    echo "1. Usar un usuario que tenga el rol 'manager'\n";
    echo "2. O asignar el rol 'manager' al usuario 14:\n";
    echo "   \$user = User::find(14);\n";
    echo "   \$user->assignRole('manager');\n\n";
} else {
    echo "1. La ruta debería funcionar correctamente\n";
    echo "2. Verificar autenticación (token válido)\n";
    echo "3. Verificar permisos del usuario autenticado\n\n";
}

echo "📍 Rutas disponibles para managers:\n";
echo "   - GET /api/users/{manager}/clients (todos los clientes)\n";
echo "   - GET /api/users/{manager}/clients-direct (solo clientes directos)\n";
echo "   - GET /api/users/{manager}/cobradores (cobradores asignados)\n";
