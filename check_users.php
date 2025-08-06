<?php

require_once __DIR__ . '/vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "👥 Usuarios en el sistema:\n";
echo "=" . str_repeat("=", 30) . "\n";

$users = App\Models\User::with('roles')->get();

foreach ($users as $user) {
    $roles = $user->roles->pluck('name')->join(', ');
    echo "📧 {$user->email} - Roles: {$roles}\n";
}

echo "\n📊 Resumen:\n";
echo "- Total usuarios: " . $users->count() . "\n";
echo "- Admins: " . $users->filter(function($u) { return $u->hasRole('admin'); })->count() . "\n";
echo "- Managers: " . $users->filter(function($u) { return $u->hasRole('manager'); })->count() . "\n";
echo "- Cobradores: " . $users->filter(function($u) { return $u->hasRole('cobrador'); })->count() . "\n";
echo "- Clientes: " . $users->filter(function($u) { return $u->hasRole('client'); })->count() . "\n";
