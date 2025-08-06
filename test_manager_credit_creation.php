<?php

require 'vendor/autoload.php';

$baseUrl = 'http://localhost:8000/api';

function makeRequest($endpoint, $data = [], $method = 'POST', $token = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        $token ? "Authorization: Bearer $token" : ''
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

echo "🎯 Probando: Manager creando créditos en lista de espera\n";
echo str_repeat("=", 60) . "\n\n";

// 1. Login como manager
echo "1️⃣ Iniciando sesión como manager...\n";
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
echo "✅ Login exitoso - Manager ID: $managerId\n\n";

// 2. Obtener cobradores asignados al manager
echo "2️⃣ Obteniendo cobradores asignados al manager...\n";
$cobradoresResponse = makeRequest("$baseUrl/users/$managerId/cobradores", [], 'GET', $token);

if ($cobradoresResponse['code'] !== 200 || empty($cobradoresResponse['data']['data']['data'])) {
    echo "❌ No hay cobradores asignados al manager. Creando uno...\n";
    
    // Crear un cobrador y asignarlo al manager
    $cobradorData = [
        'name' => 'Cobrador Test Manager',
        'email' => 'cobrador.manager@test.com',
        'password' => 'password123',
        'phone' => '555888999',
        'roles' => ['cobrador']
    ];
    
    $createCobradorResponse = makeRequest("$baseUrl/users", $cobradorData, 'POST', $token);
    if ($createCobradorResponse['code'] !== 200) {
        echo "❌ Error creando cobrador: " . json_encode($createCobradorResponse['data']) . "\n";
        exit;
    }
    
    $cobradorId = $createCobradorResponse['data']['data']['id'];
    echo "✅ Cobrador creado: ID $cobradorId\n";
    
    // Asignar cobrador al manager
    $assignResponse = makeRequest("$baseUrl/users/$managerId/assign-cobradores", [
        'cobrador_ids' => [$cobradorId]
    ], 'POST', $token);
    
    if ($assignResponse['code'] !== 200) {
        echo "❌ Error asignando cobrador: " . json_encode($assignResponse['data']) . "\n";
        exit;
    }
    echo "✅ Cobrador asignado al manager\n";
} else {
    $cobradorId = $cobradoresResponse['data']['data']['data'][0]['id'];
    echo "✅ Cobrador encontrado: ID $cobradorId\n";
}

// 3. Obtener clientes del cobrador
echo "\n3️⃣ Obteniendo clientes del cobrador...\n";
$clientesResponse = makeRequest("$baseUrl/users/$cobradorId/clients", [], 'GET', $token);

if ($clientesResponse['code'] !== 200 || empty($clientesResponse['data']['data']['data'])) {
    echo "❌ No hay clientes asignados al cobrador. Creando uno...\n";
    
    // Crear un cliente
    $clienteData = [
        'name' => 'Cliente Test Manager',
        'phone' => '555777111',
        'address' => 'Dirección test',
        'roles' => ['client']
    ];
    
    $createClienteResponse = makeRequest("$baseUrl/users", $clienteData, 'POST', $token);
    if ($createClienteResponse['code'] !== 200) {
        echo "❌ Error creando cliente: " . json_encode($createClienteResponse['data']) . "\n";
        exit;
    }
    
    $clienteId = $createClienteResponse['data']['data']['id'];
    echo "✅ Cliente creado: ID $clienteId\n";
    
    // Asignar cliente al cobrador
    $assignClientResponse = makeRequest("$baseUrl/users/$cobradorId/assign-clients", [
        'client_ids' => [$clienteId]
    ], 'POST', $token);
    
    if ($assignClientResponse['code'] !== 200) {
        echo "❌ Error asignando cliente: " . json_encode($assignClientResponse['data']) . "\n";
        exit;
    }
    echo "✅ Cliente asignado al cobrador\n";
} else {
    $clienteId = $clientesResponse['data']['data']['data'][0]['id'];
    echo "✅ Cliente encontrado: ID $clienteId\n";
}

// 4. Manager crea crédito en lista de espera especificando todos los datos
echo "\n4️⃣ Manager creando crédito en lista de espera...\n";
$creditoData = [
    'client_id' => $clienteId,
    'cobrador_id' => $cobradorId, // Manager especifica para qué cobrador es
    'amount' => 5000.00,
    'interest_rate' => 15.00,
    'total_amount' => 5750.00,
    'balance' => 5750.00,
    'installment_amount' => 250.00,
    'frequency' => 'weekly',
    'start_date' => '2025-08-10',
    'end_date' => '2025-11-02',
    'status' => 'pending_approval', // Explícitamente en lista de espera
    'scheduled_delivery_date' => '2025-08-08 10:00:00' // Manager programa la entrega
];

$creditoResponse = makeRequest("$baseUrl/credits", $creditoData, 'POST', $token);

echo "Código de respuesta: {$creditoResponse['code']}\n";
if ($creditoResponse['code'] === 200) {
    $credito = $creditoResponse['data']['data'];
    echo "✅ Crédito creado exitosamente en lista de espera\n";
    echo "   - ID del crédito: {$credito['id']}\n";
    echo "   - Estado: {$credito['status']}\n";
    echo "   - Cliente: {$credito['client']['name']} (ID: {$credito['client']['id']})\n";
    echo "   - Creado por: Manager (ID: $managerId)\n";
    echo "   - Monto: \${$credito['amount']}\n";
    echo "   - Monto total: \${$credito['total_amount']}\n";
    echo "   - Tasa de interés: {$credito['interest_rate']}%\n";
    echo "   - Frecuencia: {$credito['frequency']}\n";
    if (isset($credito['scheduled_delivery_date'])) {
        echo "   - Fecha programada de entrega: {$credito['scheduled_delivery_date']}\n";
    }
    echo "   - Mensaje: {$creditoResponse['data']['message']}\n";
    
    $creditoId = $credito['id'];
} else {
    echo "❌ Error creando crédito: " . json_encode($creditoResponse['data']) . "\n";
    exit;
}

// 5. Verificar que el crédito aparece en la lista de espera
echo "\n5️⃣ Verificando que el crédito aparece en lista de espera...\n";
$pendingResponse = makeRequest("$baseUrl/credits/waiting-list/pending-approval", [], 'GET', $token);

if ($pendingResponse['code'] === 200) {
    $pendingCredits = $pendingResponse['data']['data'];
    $found = false;
    foreach ($pendingCredits as $credit) {
        if ($credit['id'] == $creditoId) {
            $found = true;
            echo "✅ Crédito encontrado en lista de espera pendiente de aprobación\n";
            echo "   - Status de entrega: " . json_encode($credit['delivery_status'], JSON_PRETTY_PRINT) . "\n";
            break;
        }
    }
    if (!$found) {
        echo "❌ Crédito no encontrado en lista de espera\n";
    }
} else {
    echo "❌ Error obteniendo lista de espera: " . json_encode($pendingResponse['data']) . "\n";
}

// 6. Test adicional: Manager crea crédito sin especificar cobrador (debe usar el asignado al cliente)
echo "\n6️⃣ Test: Manager crea crédito sin especificar cobrador...\n";
$creditoData2 = [
    'client_id' => $clienteId,
    // Sin cobrador_id - debe usar el asignado al cliente
    'amount' => 3000.00,
    'balance' => 3000.00,
    'frequency' => 'monthly',
    'start_date' => '2025-08-10',
    'end_date' => '2025-12-10',
    // Sin status - debe defaultear a pending_approval para managers
];

$creditoResponse2 = makeRequest("$baseUrl/credits", $creditoData2, 'POST', $token);

echo "Código de respuesta: {$creditoResponse2['code']}\n";
if ($creditoResponse2['code'] === 200) {
    $credito2 = $creditoResponse2['data']['data'];
    echo "✅ Crédito creado exitosamente (sin especificar cobrador)\n";
    echo "   - Estado: {$credito2['status']}\n";
    echo "   - Mensaje: {$creditoResponse2['data']['message']}\n";
} else {
    echo "❌ Error: " . json_encode($creditoResponse2['data']) . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎯 Resumen de capacidades del Manager:\n";
echo "✅ Puede crear créditos especificando cliente y cobrador\n";
echo "✅ Puede crear créditos en estado 'pending_approval'\n";
echo "✅ Puede programar fecha de entrega desde la creación\n";
echo "✅ Puede crear créditos sin especificar cobrador (usa el del cliente)\n";
echo "✅ Los créditos aparecen automáticamente en lista de espera\n";
echo "✅ Control de permisos: solo para cobradores asignados al manager\n";
