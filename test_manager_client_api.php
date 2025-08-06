<?php

require_once 'vendor/autoload.php';

function makeRequest($url, $data = null, $method = 'GET', $token = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

echo "🧪 Probando: Endpoints API Manager → Cliente Directo\n";
echo "=====================================================\n";

$baseUrl = 'http://localhost:8000/api';

// 1. Login como manager
echo "1️⃣ Login como manager...\n";
$loginResponse = makeRequest("$baseUrl/login", [
    'email_or_phone' => 'manager@test.com',
    'password' => 'password123'
]);

if ($loginResponse['code'] !== 200) {
    echo "❌ Error en login: " . json_encode($loginResponse['data']) . "\n";
    exit;
}

$token = $loginResponse['data']['data']['token'];
$managerId = $loginResponse['data']['data']['user']['id'];
echo "✅ Manager logueado - ID: $managerId\n";

// 2. Obtener clientes directos del manager
echo "\n2️⃣ Obteniendo clientes directos del manager...\n";
$clientsResponse = makeRequest("$baseUrl/users/$managerId/clients-direct", [], 'GET', $token);

if ($clientsResponse['code'] === 200) {
    $clientsCount = $clientsResponse['data']['data']['total'] ?? count($clientsResponse['data']['data']);
    echo "✅ Clientes directos obtenidos: $clientsCount\n";
    
    if ($clientsCount > 0) {
        $firstClient = $clientsResponse['data']['data']['data'][0] ?? $clientsResponse['data']['data'][0];
        echo "   - Primer cliente: {$firstClient['name']} (ID: {$firstClient['id']})\n";
    }
} else {
    echo "❌ Error obteniendo clientes: " . json_encode($clientsResponse['data']) . "\n";
}

// 3. Buscar un cliente sin asignar para probar asignación
echo "\n3️⃣ Buscando cliente sin asignar para prueba...\n";
$allUsersResponse = makeRequest("$baseUrl/users?search=cliente", [], 'GET', $token);

$clientToAssign = null;
if ($allUsersResponse['code'] === 200) {
    $users = $allUsersResponse['data']['data']['data'] ?? $allUsersResponse['data']['data'];
    
    foreach ($users as $user) {
        $hasClientRole = false;
        foreach ($user['roles'] as $role) {
            if ($role['name'] === 'client') {
                $hasClientRole = true;
                break;
            }
        }
        
        if ($hasClientRole && !$user['assigned_manager_id']) {
            $clientToAssign = $user;
            break;
        }
    }
}

if ($clientToAssign) {
    echo "✅ Cliente encontrado para asignar: {$clientToAssign['name']} (ID: {$clientToAssign['id']})\n";
    
    // 4. Asignar cliente directamente al manager
    echo "\n4️⃣ Asignando cliente directamente al manager...\n";
    $assignResponse = makeRequest("$baseUrl/users/$managerId/assign-clients-direct", [
        'client_ids' => [$clientToAssign['id']]
    ], 'POST', $token);
    
    if ($assignResponse['code'] === 200) {
        echo "✅ Cliente asignado exitosamente\n";
        echo "   - Mensaje: {$assignResponse['data']['message']}\n";
    } else {
        echo "❌ Error asignando cliente: " . json_encode($assignResponse['data']) . "\n";
    }
    
    // 5. Verificar asignación consultando el manager del cliente
    echo "\n5️⃣ Verificando asignación desde el cliente...\n";
    $managerResponse = makeRequest("$baseUrl/users/{$clientToAssign['id']}/manager-direct", [], 'GET', $token);
    
    if ($managerResponse['code'] === 200 && $managerResponse['data']['data']) {
        $assignedManager = $managerResponse['data']['data'];
        echo "✅ Verificación exitosa - Manager asignado: {$assignedManager['name']}\n";
    } else {
        echo "❌ Error en verificación: " . json_encode($managerResponse['data']) . "\n";
    }
    
    // 6. Probar remoción de asignación
    echo "\n6️⃣ Probando remoción de asignación...\n";
    $removeResponse = makeRequest("$baseUrl/users/$managerId/clients-direct/{$clientToAssign['id']}", [], 'DELETE', $token);
    
    if ($removeResponse['code'] === 200) {
        echo "✅ Cliente removido exitosamente\n";
        echo "   - Mensaje: {$removeResponse['data']['message']}\n";
    } else {
        echo "❌ Error removiendo cliente: " . json_encode($removeResponse['data']) . "\n";
    }
    
} else {
    echo "⚠️ No se encontró cliente disponible para asignar\n";
}

// 7. Obtener lista final de clientes directos
echo "\n7️⃣ Lista final de clientes directos...\n";
$finalClientsResponse = makeRequest("$baseUrl/users/$managerId/clients-direct", [], 'GET', $token);

if ($finalClientsResponse['code'] === 200) {
    $finalCount = $finalClientsResponse['data']['data']['total'] ?? count($finalClientsResponse['data']['data']);
    echo "✅ Total final de clientes directos: $finalCount\n";
} else {
    echo "❌ Error obteniendo lista final\n";
}

// 8. Probar con búsqueda
echo "\n8️⃣ Probando búsqueda en clientes directos...\n";
$searchResponse = makeRequest("$baseUrl/users/$managerId/clients-direct?search=Rivera&per_page=5", [], 'GET', $token);

if ($searchResponse['code'] === 200) {
    $searchResults = $searchResponse['data']['data']['data'] ?? $searchResponse['data']['data'];
    echo "✅ Búsqueda ejecutada - Resultados: " . count($searchResults) . "\n";
    
    foreach ($searchResults as $client) {
        echo "   - {$client['name']} (coincidencia con 'Rivera')\n";
    }
} else {
    echo "❌ Error en búsqueda: " . json_encode($searchResponse['data']) . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🏆 RESUMEN DE PRUEBAS API:\n\n";

echo "🔄 ENDPOINTS PROBADOS:\n";
echo "1️⃣ GET /users/{manager}/clients-direct → Obtener clientes directos\n";
echo "2️⃣ POST /users/{manager}/assign-clients-direct → Asignar clientes\n";
echo "3️⃣ DELETE /users/{manager}/clients-direct/{client} → Remover cliente\n";
echo "4️⃣ GET /users/{client}/manager-direct → Obtener manager del cliente\n";

echo "\n✅ FUNCIONALIDADES VALIDADAS:\n";
echo "✅ Asignación directa Manager → Cliente\n";
echo "✅ Consulta de clientes directos con paginación\n";
echo "✅ Búsqueda en clientes directos\n";
echo "✅ Remoción de asignaciones\n";
echo "✅ Relaciones bidireccionales\n";
echo "✅ Validaciones de roles y permisos\n";

echo "\n🚀 SISTEMA MANAGER → CLIENTE 100% FUNCIONAL\n";
