<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "🔧 Actualizando password del manager...\n";

$manager = User::where('email', 'manager@test.com')->first();

if ($manager) {
    echo "✅ Manager encontrado: {$manager->name}\n";
    
    // Actualizar password
    $manager->password = Hash::make('password123');
    $manager->save();
    
    echo "✅ Password actualizado a: password123\n";
    
    // Verificar que el password funcione
    if (Hash::check('password123', $manager->password)) {
        echo "✅ Verificación de password exitosa\n";
    } else {
        echo "❌ Error en verificación de password\n";
    }
    
} else {
    echo "❌ Manager no encontrado\n";
}

echo "\n🚀 Listo para probar la API\n";
