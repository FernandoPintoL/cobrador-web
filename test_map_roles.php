<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== PRUEBA DE FUNCIONALIDAD DE ROLES EN MAPCONTROLLER ===\n\n";

// Verificar que el método hasRole funciona
$admin = User::whereHas('roles', function ($q) {
    $q->where('name', 'admin');
})->first();

if ($admin) {
    echo "✅ Usuario admin encontrado: {$admin->name}\n";

    // Verificar que hasRole funciona
    try {
        $hasAdminRole = $admin->hasRole('admin');
        echo "✅ hasRole('admin') funciona: " . ($hasAdminRole ? 'true' : 'false') . "\n";
    } catch (Exception $e) {
        echo "❌ Error al usar hasRole: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️  No se encontró usuario admin\n";
}

// Verificar cobrador
$cobrador = User::whereHas('roles', function ($q) {
    $q->where('name', 'cobrador');
})->first();

if ($cobrador) {
    echo "✅ Usuario cobrador encontrado: {$cobrador->name}\n";

    try {
        $hasCobradorRole = $cobrador->hasRole('cobrador');
        echo "✅ hasRole('cobrador') funciona: " . ($hasCobradorRole ? 'true' : 'false') . "\n";
    } catch (Exception $e) {
        echo "❌ Error al usar hasRole: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️  No se encontró usuario cobrador\n";
}

echo "\n=== PRUEBA COMPLETADA ===\n";
