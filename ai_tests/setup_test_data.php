<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

echo "🔧 Configurando datos de prueba para API Manager → Cliente\n";
echo "=========================================================\n";

// 1. Crear o verificar roles
$roles = ['admin', 'manager', 'cobrador', 'client'];
foreach ($roles as $roleName) {
    $role = Role::firstOrCreate(['name' => $roleName]);
    echo "✅ Rol '{$roleName}' disponible\n";
}

// 2. Crear manager de prueba
$manager = User::firstOrCreate(
    ['email' => 'manager.test@example.com'],
    [
        'name' => 'Manager de Prueba',
        'phone' => '+1234567890',
        'email_verified_at' => now(),
        'password' => Hash::make('password123'),
        'location' => 'Ciudad de Prueba',
        'credits' => 1000
    ]
);

if (!$manager->hasRole('manager')) {
    $manager->assignRole('manager');
}

echo "✅ Manager creado: {$manager->name} (ID: {$manager->id})\n";
echo "   📧 Email: {$manager->email}\n";
echo "   🔑 Password: password123\n";

// 3. Crear algunos clientes de prueba
$clientsData = [
    [
        'name' => 'Cliente Prueba 1',
        'email' => 'cliente1.test@example.com',
        'phone' => '+1111111111'
    ],
    [
        'name' => 'Cliente Prueba 2', 
        'email' => 'cliente2.test@example.com',
        'phone' => '+2222222222'
    ],
    [
        'name' => 'Cliente Rivera Prueba',
        'email' => 'rivera.test@example.com', 
        'phone' => '+3333333333'
    ]
];

foreach ($clientsData as $clientData) {
    $client = User::firstOrCreate(
        ['email' => $clientData['email']],
        [
            'name' => $clientData['name'],
            'phone' => $clientData['phone'],
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
            'location' => 'Ciudad Cliente',
            'credits' => 0,
            'assigned_manager_id' => null // Sin asignar inicialmente
        ]
    );
    
    if (!$client->hasRole('client')) {
        $client->assignRole('client');
    }
    
    echo "✅ Cliente creado: {$client->name} (ID: {$client->id})\n";
}

echo "\n🚀 Datos de prueba listos para probar API endpoints\n";
