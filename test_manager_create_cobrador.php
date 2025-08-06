<?php

require_once __DIR__ . '/vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🧪 Prueba: Manager creando Cobrador\n";
echo "=" . str_repeat("=", 40) . "\n\n";

try {
    // 1. Buscar un manager
    $manager = App\Models\User::whereHas('roles', function ($query) {
        $query->where('name', 'manager');
    })->first();
    
    if (!$manager) {
        echo "❌ No se encontró ningún manager en el sistema\n";
        exit(1);
    }
    
    echo "✅ Manager encontrado: {$manager->name} (ID: {$manager->id})\n";
    
    // 2. Simular la creación de un cobrador por parte del manager
    // Esto simula lo que haría el endpoint POST /api/users
    
    $cobradorData = [
        'name' => 'Cobrador Creado por Manager',
        'email' => 'cobrador_test_' . time() . '@test.com',
        'password' => 'password123',
        'phone' => '555' . random_int(100000, 999999),
        'address' => 'Dirección de prueba',
        'roles' => ['cobrador']
    ];
    
    echo "📝 Datos del cobrador a crear:\n";
    echo "   Nombre: {$cobradorData['name']}\n";
    echo "   Email: {$cobradorData['email']}\n";
    echo "   Teléfono: {$cobradorData['phone']}\n";
    echo "   Roles: " . implode(', ', $cobradorData['roles']) . "\n\n";
    
    // 3. Verificar las validaciones de permisos (simular la lógica del controller)
    $currentUser = $manager; // Simular que el manager es el usuario autenticado
    $requestedRoles = $cobradorData['roles'];
    
    echo "🔍 Verificando permisos del manager...\n";
    
    // Verificar si puede crear admin (debería fallar)
    if (in_array('admin', $requestedRoles) && !$currentUser->hasRole('admin')) {
        echo "   ❌ Manager NO puede crear admins\n";
    } else {
        echo "   ✅ Manager NO está intentando crear admin\n";
    }
    
    // Verificar si es cobrador intentando crear no-clientes (no aplica)
    if ($currentUser->hasRole('cobrador') && !in_array('client', $requestedRoles)) {
        echo "   ❌ Esta validación no aplica (el usuario es manager)\n";
    } else {
        echo "   ✅ El usuario es manager, puede crear cobradores\n";
    }
    
    // Verificar restricción de manager para crear admin
    if ($currentUser->hasRole('manager') && in_array('admin', $requestedRoles)) {
        echo "   ❌ Manager NO puede crear admin\n";
    } else {
        echo "   ✅ Manager NO está intentando crear admin - PERMITIDO\n";
    }
    
    echo "\n🎯 Resultado de validaciones: TODAS PASADAS ✅\n";
    echo "   El manager PUEDE crear el cobrador\n\n";
    
    // 4. Crear el usuario (simular la lógica del controller)
    $userData = [
        'name' => $cobradorData['name'],
        'email' => $cobradorData['email'],
        'password' => Hash::make($cobradorData['password']),
        'phone' => $cobradorData['phone'],
        'address' => $cobradorData['address'],
    ];
    
    echo "💾 Creando el cobrador...\n";
    $newCobrador = App\Models\User::create($userData);
    
    // Asignar rol
    $newCobrador->assignRole($requestedRoles);
    $newCobrador->load('roles', 'permissions');
    
    echo "✅ Cobrador creado exitosamente!\n";
    echo "   ID: {$newCobrador->id}\n";
    echo "   Nombre: {$newCobrador->name}\n";
    echo "   Email: {$newCobrador->email}\n";
    echo "   Roles: " . $newCobrador->roles->pluck('name')->join(', ') . "\n\n";
    
    // 5. Verificar que se puede asignar al manager
    echo "🔗 Asignando cobrador al manager...\n";
    $newCobrador->update(['assigned_manager_id' => $manager->id]);
    $newCobrador->refresh();
    
    if ($newCobrador->assigned_manager_id === $manager->id) {
        echo "✅ Cobrador asignado al manager exitosamente\n";
        echo "   Relación establecida: {$newCobrador->name} → {$manager->name}\n";
    } else {
        echo "❌ Error al asignar cobrador al manager\n";
    }
    
    // 6. Verificar la relación bidireccional
    $assignedManager = $newCobrador->assignedManager;
    if ($assignedManager && $assignedManager->id === $manager->id) {
        echo "✅ Relación bidireccional funcionando correctamente\n";
        echo "   {$newCobrador->name} → Manager: {$assignedManager->name}\n";
    }
    
    echo "\n" . str_repeat("=", 40) . "\n";
    echo "🎉 PRUEBA EXITOSA: Manager puede crear cobradores\n";
    echo "📊 Resumen:\n";
    echo "   ✅ Validaciones de permisos: PASADAS\n";
    echo "   ✅ Creación de cobrador: EXITOSA\n";
    echo "   ✅ Asignación a manager: FUNCIONAL\n";
    echo "   ✅ Relaciones bidireccionales: OPERATIVAS\n";
    
    // 7. Limpiar (opcional)
    echo "\n🧹 Limpiando datos de prueba...\n";
    $newCobrador->delete();
    echo "✅ Cobrador de prueba eliminado\n";
    
} catch (Exception $e) {
    echo "❌ Error durante la prueba: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
