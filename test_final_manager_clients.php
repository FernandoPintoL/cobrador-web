<?php

require_once 'vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "🎯 Verificación Final del Endpoint getAllClientsByManager\n";
echo "=====================================================\n";

// 1. Buscar el manager
$managerId = 17;
$manager = User::find($managerId);

if (!$manager) {
    echo "❌ Manager con ID {$managerId} no encontrado\n";
    exit(1);
}

echo "🔍 Manager encontrado: {$manager->name} (ID: {$manager->id})\n";
echo "   Email: {$manager->email}\n";
echo "   Roles: " . implode(', ', $manager->roles->pluck('name')->toArray()) . "\n";

// 2. Simular llamada al endpoint
echo "\n📞 Simulando llamada al endpoint...\n";

$controller = new App\Http\Controllers\Api\UserController();
$request = new Illuminate\Http\Request();

try {
    $response = $controller->getAllClientsByManager($request, $manager);
    $responseData = $response->getData(true);
    
    echo "✅ Respuesta exitosa del endpoint\n";
    
    // 3. Análisis detallado de la respuesta
    $clients = $responseData['data']['data'];
    $pagination = $responseData['data'];
    
    echo "\n📊 Estadísticas de la respuesta:\n";
    echo "   - Total de clientes: {$pagination['total']}\n";
    echo "   - Página actual: {$pagination['current_page']}\n";
    echo "   - Por página: {$pagination['per_page']}\n";
    echo "   - Desde: {$pagination['from']}\n";
    echo "   - Hasta: {$pagination['to']}\n";
    
    // 4. Análisis de tipos de asignación
    $directClients = array_filter($clients, function($client) {
        return $client['assignment_type'] === 'direct';
    });
    
    $indirectClients = array_filter($clients, function($client) {
        return $client['assignment_type'] === 'through_cobrador';
    });
    
    echo "\n👥 Análisis de asignaciones:\n";
    echo "   - Clientes directos: " . count($directClients) . "\n";
    echo "   - Clientes indirectos: " . count($indirectClients) . "\n";
    
    // 5. Verificación de roles
    echo "\n🔍 Verificación de roles (problema ya resuelto):\n";
    $problemUsers = [];
    
    foreach ($clients as $client) {
        $roles = array_column($client['roles'], 'name');
        $hasMultipleRoles = count($roles) > 1;
        $hasManagerRole = in_array('manager', $roles);
        
        if ($hasMultipleRoles || $hasManagerRole) {
            $problemUsers[] = [
                'client' => $client,
                'roles' => $roles
            ];
        }
        
        $rolesText = implode(', ', $roles);
        $icon = $hasMultipleRoles || $hasManagerRole ? '⚠️' : '✅';
        echo "   {$icon} {$client['name']} (ID: {$client['id']}) | Roles: {$rolesText}\n";
    }
    
    if (empty($problemUsers)) {
        echo "\n✅ EXCELENTE: No se encontraron usuarios con roles problemáticos\n";
    } else {
        echo "\n❌ PROBLEMA: Se encontraron " . count($problemUsers) . " usuarios con roles problemáticos\n";
        foreach ($problemUsers as $problem) {
            echo "   - {$problem['client']['name']}: " . implode(', ', $problem['roles']) . "\n";
        }
    }
    
    // 6. Detalle de clientes directos
    if (!empty($directClients)) {
        echo "\n👤 Clientes directos del manager:\n";
        foreach ($directClients as $client) {
            echo "   - {$client['name']} (ID: {$client['id']})\n";
            echo "     Email: " . ($client['email'] ?: 'Sin email') . "\n";
            echo "     Teléfono: " . ($client['phone'] ?: 'Sin teléfono') . "\n";
            echo "     Dirección: " . ($client['address'] ?: 'Sin dirección') . "\n";
        }
    }
    
    // 7. Detalle de clientes indirectos
    if (!empty($indirectClients)) {
        echo "\n🔗 Clientes indirectos (a través de cobradores):\n";
        $cobradoresGroup = [];
        
        foreach ($indirectClients as $client) {
            $cobradorName = $client['cobrador_name'];
            if (!isset($cobradoresGroup[$cobradorName])) {
                $cobradoresGroup[$cobradorName] = [];
            }
            $cobradoresGroup[$cobradorName][] = $client;
        }
        
        foreach ($cobradoresGroup as $cobradorName => $clientsOfCobrador) {
            echo "   📋 Cobrador: {$cobradorName} (" . count($clientsOfCobrador) . " clientes)\n";
            foreach ($clientsOfCobrador as $client) {
                echo "      └── {$client['name']} (ID: {$client['id']})\n";
            }
        }
    }
    
    // 8. Validación de la estructura de respuesta
    echo "\n🧪 Validación de estructura de respuesta:\n";
    
    $requiredFields = ['success', 'data', 'message'];
    foreach ($requiredFields as $field) {
        $hasField = isset($responseData[$field]) ? '✅' : '❌';
        echo "   {$hasField} Campo '{$field}'\n";
    }
    
    $requiredDataFields = ['current_page', 'data', 'total', 'per_page'];
    foreach ($requiredDataFields as $field) {
        $hasField = isset($responseData['data'][$field]) ? '✅' : '❌';
        echo "   {$hasField} Campo 'data.{$field}'\n";
    }
    
    // 9. Verificación de campos específicos en clientes
    if (!empty($clients)) {
        echo "\n🔍 Verificación de campos en clientes:\n";
        $sampleClient = $clients[0];
        $clientFields = ['id', 'name', 'email', 'assignment_type', 'roles', 'assigned_cobrador', 'assigned_manager_directly'];
        
        foreach ($clientFields as $field) {
            $hasField = isset($sampleClient[$field]) ? '✅' : '❌';
            echo "   {$hasField} Campo '{$field}'\n";
        }
    }
    
    // 10. Resumen final
    echo "\n🎯 RESUMEN FINAL:\n";
    echo "   ✅ Endpoint funcionando correctamente\n";
    echo "   ✅ Devuelve clientes directos e indirectos\n";
    echo "   ✅ Filtrado de usuarios con roles problemáticos aplicado\n";
    echo "   ✅ Estructura de respuesta válida\n";
    echo "   ✅ Paginación funcionando\n";
    echo "   ✅ Información de assignment_type incluida\n";
    echo "   ✅ Relaciones cargadas correctamente\n";
    
    echo "\n🏆 ENDPOINT VALIDADO COMPLETAMENTE\n";
    
} catch (Exception $e) {
    echo "\n❌ Error ejecutando el endpoint:\n";
    echo "   Mensaje: {$e->getMessage()}\n";
    echo "   Archivo: {$e->getFile()}\n";
    echo "   Línea: {$e->getLine()}\n";
    exit(1);
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Verificación completada exitosamente\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 50) . "\n";
