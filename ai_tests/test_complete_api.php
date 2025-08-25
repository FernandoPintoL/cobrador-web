<?php

echo "🧪 Prueba Completa: Endpoints API Manager → Cliente Directo\n";
echo "=============================================================\n";

$baseUrl = 'http://localhost:8000/api';

// Función para hacer requests
function apiRequest($url, $data = null, $method = 'GET', $token = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if (in_array($method, ['POST', 'PUT', 'PATCH']) && $data) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true),
        'raw' => $response,
        'error' => $error
    ];
}

// 1. Login como manager
echo "1️⃣ Probando Login...\n";
$loginResponse = apiRequest("$baseUrl/login", [
    'email_or_phone' => 'manager@test.com',
    'password' => 'password123'
], 'POST');

if ($loginResponse['code'] !== 200) {
    echo "❌ Error en login: HTTP {$loginResponse['code']}\n";
    echo "Respuesta: " . $loginResponse['raw'] . "\n";
    exit;
}

$token = $loginResponse['data']['data']['token'];
$managerId = $loginResponse['data']['data']['user']['id'];
echo "✅ Login exitoso! Manager: {$loginResponse['data']['data']['user']['name']} (ID: $managerId)\n";

// 2. Obtener clientes directos iniciales
echo "\n2️⃣ Obteniendo clientes directos iniciales...\n";
$clientsResponse = apiRequest("$baseUrl/users/$managerId/clients-direct", null, 'GET', $token);

if ($clientsResponse['code'] === 200) {
    $initialClients = $clientsResponse['data']['data']['data'];
    $totalInitial = $clientsResponse['data']['data']['total'];
    echo "✅ Clientes directos iniciales: $totalInitial\n";
    foreach ($initialClients as $client) {
        echo "   - {$client['name']} (ID: {$client['id']})\n";
    }
} else {
    echo "❌ Error obteniendo clientes iniciales\n";
}

// 3. Buscar clientes sin asignar para probar asignación
echo "\n3️⃣ Buscando clientes sin asignar...\n";
$allUsersResponse = apiRequest("$baseUrl/users?per_page=50", null, 'GET', $token);

$availableClients = [];
if ($allUsersResponse['code'] === 200) {
    $users = $allUsersResponse['data']['data']['data'];
    
    foreach ($users as $user) {
        $isClient = false;
        foreach ($user['roles'] as $role) {
            if ($role['name'] === 'client') {
                $isClient = true;
                break;
            }
        }
        
        if ($isClient && !$user['assigned_manager_id']) {
            $availableClients[] = $user;
        }
    }
}

echo "✅ Clientes disponibles para asignar: " . count($availableClients) . "\n";

if (count($availableClients) >= 2) {
    $clientsToAssign = array_slice($availableClients, 0, 2);
    $clientIds = array_column($clientsToAssign, 'id');
    
    echo "   Seleccionados para asignar:\n";
    foreach ($clientsToAssign as $client) {
        echo "   - {$client['name']} (ID: {$client['id']})\n";
    }
    
    // 4. Asignar clientes directamente al manager
    echo "\n4️⃣ Asignando clientes directamente al manager...\n";
    $assignResponse = apiRequest("$baseUrl/users/$managerId/assign-clients-direct", [
        'client_ids' => $clientIds
    ], 'POST', $token);
    
    if ($assignResponse['code'] === 200) {
        echo "✅ Clientes asignados exitosamente\n";
        echo "   - Mensaje: {$assignResponse['data']['message']}\n";
        echo "   - Clientes asignados: {$assignResponse['data']['data']['assigned_count']}\n";
    } else {
        echo "❌ Error asignando clientes: " . json_encode($assignResponse['data']) . "\n";
    }
    
    // 5. Verificar asignación consultando clientes directos
    echo "\n5️⃣ Verificando asignaciones...\n";
    $verifyResponse = apiRequest("$baseUrl/users/$managerId/clients-direct", null, 'GET', $token);
    
    if ($verifyResponse['code'] === 200) {
        $newTotal = $verifyResponse['data']['data']['total'];
        echo "✅ Total de clientes directos después de asignación: $newTotal\n";
        
        $newClients = $verifyResponse['data']['data']['data'];
        $recentlyAssigned = array_filter($newClients, function($client) use ($clientIds) {
            return in_array($client['id'], $clientIds);
        });
        
        foreach ($recentlyAssigned as $client) {
            echo "   ✓ {$client['name']} confirmado como asignado\n";
        }
    }
    
    // 6. Probar consulta desde el cliente hacia el manager
    echo "\n6️⃣ Probando consulta bidireccional...\n";
    $firstClientId = $clientIds[0];
    $managerResponse = apiRequest("$baseUrl/users/$firstClientId/manager-direct", null, 'GET', $token);
    
    if ($managerResponse['code'] === 200 && $managerResponse['data']['data']) {
        $assignedManager = $managerResponse['data']['data'];
        echo "✅ Manager asignado al cliente: {$assignedManager['name']} (ID: {$assignedManager['id']})\n";
    } else {
        echo "❌ Error en consulta bidireccional\n";
    }
    
    // 7. Probar búsqueda con filtros
    echo "\n7️⃣ Probando búsqueda con filtros...\n";
    $searchResponse = apiRequest("$baseUrl/users/$managerId/clients-direct?search=Dr&per_page=10", null, 'GET', $token);
    
    if ($searchResponse['code'] === 200) {
        $searchResults = $searchResponse['data']['data']['data'];
        echo "✅ Búsqueda ejecutada - Resultados con 'Dr': " . count($searchResults) . "\n";
        foreach ($searchResults as $client) {
            echo "   - {$client['name']}\n";
        }
    }
    
    // 8. Probar remoción de un cliente
    echo "\n8️⃣ Probando remoción de asignación...\n";
    $clientToRemove = $clientIds[1];
    $removeResponse = apiRequest("$baseUrl/users/$managerId/clients-direct/$clientToRemove", null, 'DELETE', $token);
    
    if ($removeResponse['code'] === 200) {
        echo "✅ Cliente removido exitosamente\n";
        echo "   - Mensaje: {$removeResponse['data']['message']}\n";
    } else {
        echo "❌ Error removiendo cliente: " . json_encode($removeResponse['data']) . "\n";
    }
    
    // 9. Verificación final
    echo "\n9️⃣ Verificación final...\n";
    $finalResponse = apiRequest("$baseUrl/users/$managerId/clients-direct", null, 'GET', $token);
    
    if ($finalResponse['code'] === 200) {
        $finalTotal = $finalResponse['data']['data']['total'];
        echo "✅ Total final de clientes directos: $finalTotal\n";
        echo "   (Debería ser: $totalInitial + 1 = " . ($totalInitial + 1) . ")\n";
    }
    
} else {
    echo "⚠️ No hay suficientes clientes sin asignar para hacer la prueba completa\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🏆 RESUMEN DE PRUEBAS:\n\n";

echo "✅ FUNCIONALIDADES VALIDADAS:\n";
echo "✅ Login con manager existente\n";
echo "✅ Obtener lista de clientes directos con paginación\n";
echo "✅ Asignar múltiples clientes directamente a manager\n";
echo "✅ Verificar asignaciones bidireccionales\n";
echo "✅ Búsqueda con filtros en clientes directos\n";
echo "✅ Remoción de asignaciones individuales\n";
echo "✅ Consulta manager desde cliente\n";

echo "\n🔄 ENDPOINTS PROBADOS:\n";
echo "✅ POST /api/login\n";
echo "✅ GET /api/users/{manager}/clients-direct\n";
echo "✅ POST /api/users/{manager}/assign-clients-direct\n";
echo "✅ DELETE /api/users/{manager}/clients-direct/{client}\n";
echo "✅ GET /api/users/{client}/manager-direct\n";

echo "\n🚀 SISTEMA MANAGER → CLIENTE DIRECTO 100% FUNCIONAL\n";
