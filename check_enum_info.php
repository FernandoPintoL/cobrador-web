<?php
/**
 * Consulta para encontrar el nombre del tipo enum de notifications
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Buscando información del enum de notifications...\n\n";

try {
    // Consulta para encontrar el nombre del tipo enum
    $enumInfo = DB::select("
        SELECT 
            t.typname as type_name,
            string_agg(e.enumlabel, ', ' ORDER BY e.enumsortorder) as enum_values
        FROM pg_type t 
        JOIN pg_enum e ON t.oid = e.enumtypid 
        WHERE t.typname LIKE '%notification%' OR t.typname LIKE '%type%'
        GROUP BY t.typname
        ORDER BY t.typname
    ");
    
    echo "📋 Tipos enum encontrados:\n";
    foreach ($enumInfo as $info) {
        echo "   - Tipo: {$info->type_name}\n";
        echo "     Valores: {$info->enum_values}\n";
        echo "     ---\n";
    }
    
    // También verificar la estructura de la tabla notifications
    echo "\n📊 Estructura de la tabla notifications:\n";
    $tableInfo = DB::select("
        SELECT 
            column_name, 
            data_type, 
            udt_name,
            is_nullable,
            column_default
        FROM information_schema.columns 
        WHERE table_name = 'notifications' 
        ORDER BY ordinal_position
    ");
    
    foreach ($tableInfo as $column) {
        echo "   - {$column->column_name}: {$column->data_type}";
        if ($column->udt_name) {
            echo " ({$column->udt_name})";
        }
        echo " - Nullable: {$column->is_nullable}";
        if ($column->column_default) {
            echo " - Default: {$column->column_default}";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n✨ Consulta completada\n";
