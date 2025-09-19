<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== PRUEBA DE ENDPOINTS DE COORDENADAS CON ROLES ===\n\n";

// Simular solicitud de un cobrador
$cobrador = User::whereHas('roles', function ($q) {
    $q->where('name', 'cobrador');
})->first();

if ($cobrador) {
    echo "🔍 Probando con cobrador: {$cobrador->name} (ID: {$cobrador->id})\n";

    // Obtener clientes asignados al cobrador
    $clientsAssigned = User::role('client')
        ->where('assigned_cobrador_id', $cobrador->id)
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->count();

    echo "   📍 Clientes con coordenadas asignados: {$clientsAssigned}\n";

    // Simular filtro de la nueva lógica del MapController
    $clientsFiltered = User::role('client')
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->where('assigned_cobrador_id', $cobrador->id) // Este filtro se aplicaría automáticamente
        ->count();

    echo "   ✅ Clientes que vería el cobrador: {$clientsFiltered}\n";
}

echo "\n";

// Simular solicitud de un manager
$manager = User::whereHas('roles', function ($q) {
    $q->where('name', 'manager');
})->first();

if ($manager) {
    echo "🔍 Probando con manager: {$manager->name} (ID: {$manager->id})\n";

    // Clientes directos del manager
    $directClients = User::role('client')
        ->where('assigned_manager_id', $manager->id)
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->count();

    echo "   📍 Clientes directos con coordenadas: {$directClients}\n";

    // Cobradores del manager
    $cobradorIds = User::role('cobrador')
        ->where('assigned_manager_id', $manager->id)
        ->pluck('id');

    echo "   👥 Cobradores asignados: {$cobradorIds->count()}\n";

    // Clientes de los cobradores del manager
    $cobradorClients = User::role('client')
        ->whereIn('assigned_cobrador_id', $cobradorIds)
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->count();

    echo "   📍 Clientes de cobradores con coordenadas: {$cobradorClients}\n";

    $totalManagerClients = $directClients + $cobradorClients;
    echo "   ✅ Total de clientes que vería el manager: {$totalManagerClients}\n";
}

echo "\n";

// Simular solicitud de un admin
$admin = User::whereHas('roles', function ($q) {
    $q->where('name', 'admin');
})->first();

if ($admin) {
    echo "🔍 Probando con admin: {$admin->name} (ID: {$admin->id})\n";

    $allClients = User::role('client')
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->count();

    echo "   ✅ Total de clientes con coordenadas que vería el admin: {$allClients}\n";
}

echo "\n=== ENDPOINTS DISPONIBLES ===\n";
echo "✅ GET /api/map/clients - Lista completa con detalles (YA MEJORADO)\n";
echo "✅ GET /api/map/coordinates - Solo coordenadas (NUEVO)\n";
echo "✅ GET /api/map/stats - Estadísticas (YA MEJORADO)\n";
echo "✅ GET /api/map/clients-by-area - Por área geográfica\n";
echo "✅ GET /api/map/cobrador-routes - Rutas de cobradores\n";

echo "\n=== PARÁMETROS OPCIONALES ===\n";
echo "- status: overdue|pending|paid (filtrar por estado de pago)\n";
echo "- cobrador_id: ID (solo para admins/managers)\n";

echo "\n=== PRUEBA COMPLETADA ===\n";
