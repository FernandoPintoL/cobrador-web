<?php

require_once __DIR__ . '/vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🧪 Pruebas de Asignación de Cobradores a Managers\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // 1. Buscar un manager existente
    $manager = App\Models\User::whereHas('roles', function ($query) {
        $query->where('name', 'manager');
    })->first();
    
    if (!$manager) {
        echo "❌ No se encontró ningún manager en el sistema\n";
        echo "   Por favor, crea al menos un usuario con rol 'manager'\n";
        exit(1);
    }
    
    echo "✅ Manager encontrado: {$manager->name} (ID: {$manager->id})\n";
    
    // 2. Buscar cobradores sin asignar
    $availableCobradores = App\Models\User::whereHas('roles', function ($query) {
        $query->where('name', 'cobrador');
    })->whereNull('assigned_manager_id')->get();
    
    echo "📊 Cobradores disponibles para asignar: {$availableCobradores->count()}\n";
    
    // 3. Contar cobradores ya asignados al manager
    $assignedCobradores = $manager->assignedCobradores()->count();
    echo "👥 Cobradores ya asignados al manager: {$assignedCobradores}\n";
    
    // 4. Si hay cobradores disponibles, asignar algunos
    if ($availableCobradores->count() > 0) {
        $cobradoresParaAsignar = $availableCobradores->take(2);
        
        echo "\n🔗 Asignando cobradores al manager...\n";
        foreach ($cobradoresParaAsignar as $cobrador) {
            $cobrador->update(['assigned_manager_id' => $manager->id]);
            echo "   ✓ {$cobrador->name} (ID: {$cobrador->id}) asignado al manager\n";
        }
        
        // 5. Verificar la asignación
        $manager->refresh();
        $totalAsignados = $manager->assignedCobradores()->count();
        echo "\n✅ Total de cobradores asignados al manager: {$totalAsignados}\n";
        
        // 6. Mostrar la jerarquía completa
        echo "\n📋 Jerarquía del Manager {$manager->name}:\n";
        $cobradores = $manager->assignedCobradores()->with('assignedClients')->get();
        
        foreach ($cobradores as $cobrador) {
            $clientsCount = $cobrador->assignedClients()->count();
            echo "   └── Cobrador: {$cobrador->name} ({$clientsCount} clientes)\n";
            
            if ($clientsCount > 0) {
                $clients = $cobrador->assignedClients()->limit(3)->get();
                foreach ($clients as $client) {
                    echo "       └── Cliente: {$client->name}\n";
                }
                if ($clientsCount > 3) {
                    echo "       └── ... y " . ($clientsCount - 3) . " más\n";
                }
            }
        }
        
        // 7. Probar obtener manager desde cobrador
        $primerCobrador = $cobradores->first();
        if ($primerCobrador) {
            $managerAsignado = $primerCobrador->assignedManager;
            if ($managerAsignado && $managerAsignado->id === $manager->id) {
                echo "\n✅ Relación bidireccional funcionando correctamente\n";
                echo "   Cobrador {$primerCobrador->name} → Manager {$managerAsignado->name}\n";
            } else {
                echo "\n❌ Error en la relación bidireccional\n";
            }
        }
        
        // 8. Estadísticas del manager
        $stats = [
            'total_cobradores' => $manager->assignedCobradores()->count(),
            'total_clients' => App\Models\User::whereHas('assignedCobrador.assignedManager', function ($q) use ($manager) {
                $q->where('id', $manager->id);
            })->count(),
            'total_credits' => App\Models\Credit::whereHas('client.assignedCobrador.assignedManager', function ($q) use ($manager) {
                $q->where('id', $manager->id);
            })->count(),
        ];
        
        echo "\n📈 Estadísticas del manager:\n";
        echo "   - Total de cobradores: {$stats['total_cobradores']}\n";
        echo "   - Total de clientes (indirecto): {$stats['total_clients']}\n";
        echo "   - Total de créditos (indirecto): {$stats['total_credits']}\n";
        
    } else {
        echo "⚠️  No hay cobradores disponibles para asignar\n";
        echo "   Todos los cobradores ya están asignados a un manager\n";
        
        // Mostrar cobradores asignados
        if ($assignedCobradores > 0) {
            echo "\n📋 Cobradores ya asignados al manager:\n";
            $cobradores = $manager->assignedCobradores()->get();
            foreach ($cobradores as $cobrador) {
                echo "   - {$cobrador->name} (ID: {$cobrador->id})\n";
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ Pruebas completadas exitosamente\n";
    
    // 9. Información de endpoints disponibles
    echo "\n📡 Endpoints disponibles para testing:\n";
    echo "   GET    /api/users/{$manager->id}/cobradores\n";
    echo "   POST   /api/users/{$manager->id}/assign-cobradores\n";
    echo "   DELETE /api/users/{$manager->id}/cobradores/{cobrador_id}\n";
    echo "   GET    /api/users/{cobrador_id}/manager\n";
    
} catch (Exception $e) {
    echo "❌ Error durante las pruebas: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if (env('APP_DEBUG')) {
        echo "\n🔍 Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
}
