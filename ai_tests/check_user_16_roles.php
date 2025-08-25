<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;

echo "🔍 Verificando usuario 16 y sus roles...\n";

// Verificar si el usuario existe
$user = User::find(16);
if (!$user) {
    echo "❌ Usuario 16 no encontrado\n";
    exit;
}

echo "✅ Usuario encontrado: {$user->name} ({$user->email})\n";

// Verificar roles del usuario
$roles = $user->roles()->pluck('name')->toArray();
echo "📋 Roles actuales: " . implode(', ', $roles) . "\n";

// Verificar si tiene rol de manager
$hasManagerRole = $user->hasRole('manager');
echo "🏢 ¿Tiene rol de manager?: " . ($hasManagerRole ? 'SÍ' : 'NO') . "\n";

// Mostrar todos los roles disponibles
echo "\n📚 Roles disponibles en el sistema:\n";
$allRoles = Role::all();
foreach ($allRoles as $role) {
    echo "  - {$role->name}\n";
}

// Si no tiene el rol de manager, proponer solución
if (!$hasManagerRole) {
    echo "\n💡 SOLUCIÓN: El usuario 16 necesita tener el rol de 'manager' asignado.\n";
    echo "Ejecuta uno de estos comandos para asignarlo:\n";
    echo "1. Via Tinker: User::find(16)->assignRole('manager');\n";
    echo "2. Via comando personalizado si existe\n";

    // Intentar asignar el rol automáticamente
    echo "\n🔧 Intentando asignar rol de manager al usuario...\n";
    try {
        $user->assignRole('manager');
        echo "✅ Rol de manager asignado exitosamente!\n";

        // Verificar que se asignó correctamente
        $user->refresh();
        $newRoles = $user->roles()->pluck('name')->toArray();
        echo "📋 Nuevos roles: " . implode(', ', $newRoles) . "\n";

    } catch (Exception $e) {
        echo "❌ Error al asignar rol: " . $e->getMessage() . "\n";
    }
}

echo "\n🏁 Verificación completada.\n";
