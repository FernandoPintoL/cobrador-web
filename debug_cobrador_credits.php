<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Models\Credit;
use App\Models\User;

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUG: Problema listado créditos por cobrador ===\n\n";

// Buscar un cobrador para probar
$cobrador = User::whereHas('roles', function ($query) {
    $query->where('name', 'cobrador');
})->first();

if (! $cobrador) {
    echo "❌ No se encontró ningún cobrador en la base de datos\n";
    exit;
}

echo "👤 Probando con cobrador: {$cobrador->name} (ID: {$cobrador->id})\n";
echo '📧 Email: '.($cobrador->email ?? 'Sin email')."\n";
echo '📱 Teléfono: '.($cobrador->phone ?? 'Sin teléfono')."\n\n";

// Verificar créditos creados directamente por el cobrador
echo "=== 1. Créditos creados DIRECTAMENTE por el cobrador ===\n";
$creditosDirectos = Credit::where('created_by', $cobrador->id)
    ->with(['client'])
    ->get();

if ($creditosDirectos->isEmpty()) {
    echo "❌ No hay créditos creados directamente por este cobrador\n";
} else {
    echo "✅ Encontrados {$creditosDirectos->count()} créditos creados por el cobrador:\n";
    foreach ($creditosDirectos as $credit) {
        echo "  - Crédito ID: {$credit->id}, Cliente: {$credit->client->name}, Monto: {$credit->amount}\n";
    }
}

echo "\n=== 2. Clientes asignados al cobrador ===\n";
$clientesAsignados = $cobrador->assignedClients;

if ($clientesAsignados->isEmpty()) {
    echo "❌ No hay clientes asignados a este cobrador\n";
} else {
    echo "✅ Encontrados {$clientesAsignados->count()} clientes asignados:\n";
    foreach ($clientesAsignados as $client) {
        echo "  - Cliente: {$client->name} (ID: {$client->id})\n";
    }
}

echo "\n=== 3. Créditos de clientes asignados al cobrador ===\n";
$creditosClientes = Credit::whereHas('client', function ($q) use ($cobrador) {
    $q->where('assigned_cobrador_id', $cobrador->id);
})->with(['client'])->get();

if ($creditosClientes->isEmpty()) {
    echo "❌ No hay créditos de clientes asignados a este cobrador\n";
} else {
    echo "✅ Encontrados {$creditosClientes->count()} créditos de clientes asignados:\n";
    foreach ($creditosClientes as $credit) {
        echo "  - Crédito ID: {$credit->id}, Cliente: {$credit->client->name}, Creado por: {$credit->created_by}\n";
    }
}

echo "\n=== 4. CONSULTA COMPLETA (como en el controlador) ===\n";
$queryCompleta = Credit::with(['client', 'payments', 'createdBy'])
    ->where(function ($q) use ($cobrador) {
        $q->where('created_by', $cobrador->id)
            ->orWhereHas('client', function ($q2) use ($cobrador) {
                $q2->where('assigned_cobrador_id', $cobrador->id);
            });
    })->get();

echo "📊 Total de créditos encontrados con la consulta del controlador: {$queryCompleta->count()}\n";

if ($queryCompleta->isEmpty()) {
    echo "❌ La consulta del controlador NO devuelve créditos\n";

    // Diagnóstico adicional
    echo "\n=== DIAGNÓSTICO ADICIONAL ===\n";

    // Verificar si existe algún crédito en la base de datos
    $totalCreditos = Credit::count();
    echo "Total de créditos en la base de datos: {$totalCreditos}\n";

    if ($totalCreditos > 0) {
        $unCreditoCualquiera = Credit::with('client')->first();
        echo "Ejemplo de crédito (ID: {$unCreditoCualquiera->id}):\n";
        echo "  - Cliente: {$unCreditoCualquiera->client->name}\n";
        echo "  - Creado por: {$unCreditoCualquiera->created_by}\n";
        echo '  - Cliente assigned_cobrador_id: '.($unCreditoCualquiera->client->assigned_cobrador_id ?? 'NULL')."\n";
    }
} else {
    echo "✅ La consulta del controlador SÍ devuelve créditos:\n";
    foreach ($queryCompleta as $credit) {
        $razon = '';
        if ($credit->created_by == $cobrador->id) {
            $razon .= '[Creado por cobrador] ';
        }
        if ($credit->client->assigned_cobrador_id == $cobrador->id) {
            $razon .= '[Cliente asignado] ';
        }

        echo "  - Crédito ID: {$credit->id}, Cliente: {$credit->client->name}, {$razon}\n";
    }
}

echo "\n=== 5. VERIFICACIÓN DE SQL GENERADO ===\n";
$sqlQuery = Credit::with(['client', 'payments', 'createdBy'])
    ->where(function ($q) use ($cobrador) {
        $q->where('created_by', $cobrador->id)
            ->orWhereHas('client', function ($q2) use ($cobrador) {
                $q2->where('assigned_cobrador_id', $cobrador->id);
            });
    })->toSql();

echo "SQL generado:\n{$sqlQuery}\n";

echo "\n=== DIAGNÓSTICO COMPLETADO ===\n";
