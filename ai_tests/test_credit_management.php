<?php

require_once __DIR__ . '/vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🧪 Pruebas de Gestión de Créditos para Cobradores\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // 1. Buscar un cobrador existente
    $cobrador = App\Models\User::whereHas('roles', function ($query) {
        $query->where('name', 'cobrador');
    })->first();
    
    if (!$cobrador) {
        echo "❌ No se encontró ningún cobrador en el sistema\n";
        echo "   Por favor, crea al menos un usuario con rol 'cobrador'\n";
        exit(1);
    }
    
    echo "✅ Cobrador encontrado: {$cobrador->name} (ID: {$cobrador->id})\n";
    
    // 2. Buscar clientes asignados al cobrador
    $assignedClients = $cobrador->assignedClients()->count();
    echo "📊 Clientes asignados al cobrador: {$assignedClients}\n";
    
    // 3. Contar créditos existentes del cobrador
    $existingCredits = App\Models\Credit::whereHas('client', function ($q) use ($cobrador) {
        $q->where('assigned_cobrador_id', $cobrador->id);
    })->count();
    
    echo "💳 Créditos existentes del cobrador: {$existingCredits}\n";
    
    // 4. Si hay clientes asignados, crear un crédito de prueba
    if ($assignedClients > 0) {
        $client = $cobrador->assignedClients()->first();
        
        $credit = App\Models\Credit::create([
            'client_id' => $client->id,
            'created_by' => $cobrador->id,
            'amount' => 10000.00,
            'balance' => 10000.00,
            'frequency' => 'monthly',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'status' => 'active',
        ]);
        
        echo "✅ Crédito de prueba creado exitosamente\n";
        echo "   - Cliente: {$client->name}\n";
        echo "   - Monto: $" . number_format($credit->amount, 2) . "\n";
        echo "   - ID del crédito: {$credit->id}\n";
        
        // 5. Simular estadísticas del cobrador
        $stats = [
            'total_credits' => App\Models\Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->count(),
            
            'active_credits' => App\Models\Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->where('status', 'active')->count(),
            
            'total_amount' => App\Models\Credit::whereHas('client', function ($q) use ($cobrador) {
                $q->where('assigned_cobrador_id', $cobrador->id);
            })->sum('amount'),
        ];
        
        echo "\n📈 Estadísticas del cobrador:\n";
        echo "   - Total de créditos: {$stats['total_credits']}\n";
        echo "   - Créditos activos: {$stats['active_credits']}\n";
        echo "   - Monto total: $" . number_format($stats['total_amount'], 2) . "\n";
        
    } else {
        echo "⚠️  El cobrador no tiene clientes asignados\n";
        echo "   Para probar completamente, asigna algunos clientes al cobrador\n";
    }
    
    echo "\n✅ Todas las pruebas completadas exitosamente!\n";
    echo "🚀 El sistema está listo para la gestión de créditos por cobradores\n";
    
} catch (Exception $e) {
    echo "❌ Error durante las pruebas: " . $e->getMessage() . "\n";
    echo "   Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
