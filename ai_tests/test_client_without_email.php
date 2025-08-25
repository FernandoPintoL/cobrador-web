<?php

require_once __DIR__ . '/vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Crear un usuario cliente sin email
$user = new App\Models\User();
$user->name = 'Cliente Test Sin Email';
$user->phone = '123456789';
$user->address = 'Dirección test';
$user->password = bcrypt('password123');
$user->save();

echo 'Usuario creado con ID: ' . $user->id . PHP_EOL;
echo 'Nombre: ' . $user->name . PHP_EOL;
echo 'Email: ' . ($user->email ?? 'NULL') . PHP_EOL;
echo 'Teléfono: ' . $user->phone . PHP_EOL;
echo 'Dirección: ' . $user->address . PHP_EOL;

// Verificar en la base de datos
$foundUser = App\Models\User::find($user->id);
echo PHP_EOL . 'Verificación desde BD:' . PHP_EOL;
echo 'ID: ' . $foundUser->id . PHP_EOL;
echo 'Nombre: ' . $foundUser->name . PHP_EOL;
echo 'Email: ' . ($foundUser->email ?? 'NULL') . PHP_EOL;

echo PHP_EOL . '✅ Test completado exitosamente!' . PHP_EOL;
