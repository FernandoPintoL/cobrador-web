<?php
/**
 * Consulta para encontrar las restricciones CHECK de la tabla notifications
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Buscando restricciones CHECK de notifications...\n\n";

try {
    // Consultar las restricciones CHECK
    $checkConstraints = DB::select("
        SELECT 
            conname as constraint_name,
            pg_get_constraintdef(oid) as constraint_definition
        FROM pg_constraint 
        WHERE conrelid = 'notifications'::regclass 
        AND contype = 'c'
        ORDER BY conname
    ");
    
    echo "📋 Restricciones CHECK encontradas:\n";
    foreach ($checkConstraints as $constraint) {
        echo "   - Nombre: {$constraint->constraint_name}\n";
        echo "     Definición: {$constraint->constraint_definition}\n";
        echo "     ---\n";
    }
    
    if (empty($checkConstraints)) {
        echo "   📝 No se encontraron restricciones CHECK\n";
    }
    
    // También verificar todos los constraints de la tabla
    echo "\n📊 Todas las restricciones de la tabla notifications:\n";
    $allConstraints = DB::select("
        SELECT 
            conname as constraint_name,
            contype as constraint_type,
            pg_get_constraintdef(oid) as constraint_definition
        FROM pg_constraint 
        WHERE conrelid = 'notifications'::regclass 
        ORDER BY contype, conname
    ");
    
    foreach ($allConstraints as $constraint) {
        $type_description = match($constraint->constraint_type) {
            'c' => 'CHECK',
            'f' => 'FOREIGN KEY',
            'p' => 'PRIMARY KEY',
            'u' => 'UNIQUE',
            default => $constraint->constraint_type
        };
        
        echo "   - {$constraint->constraint_name} ({$type_description})\n";
        echo "     {$constraint->constraint_definition}\n";
        echo "     ---\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n✨ Consulta completada\n";
