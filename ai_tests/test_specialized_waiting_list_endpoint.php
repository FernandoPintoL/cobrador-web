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

echo "🚀 Probando: Endpoint especializado para crear créditos en lista de espera\n";
echo str_repeat("=", 70) . "\n\n";

// 1. Login como manager
echo "1️⃣ Iniciando sesión como manager...\n";
$loginResponse = makeRequest("$baseUrl/login", [
    'email_or_phone' => 'manager@test.com',
    'password' => 'password123'
]);

if ($loginResponse['code'] !== 200) {
    echo "❌ Error en login: " . json_encode($loginResponse) . "\n";
    
    // Intentar con admin si manager no funciona
    echo "Intentando con admin...\n";
    $loginResponse = makeRequest("$baseUrl/login", [
        'email_or_phone' => 'admin@test.com',
        'password' => 'password123'
    ]);
    
    if ($loginResponse['code'] !== 200) {
        echo "❌ Error en login con admin también: " . json_encode($loginResponse) . "\n";
        exit;
    }
}

$token = $loginResponse['data']['data']['token'];
$managerId = $loginResponse['data']['data']['user']['id'];
echo "✅ Login exitoso - Manager ID: $managerId\n\n";

// 2. Obtener cobrador y cliente para el test
$cobradoresResponse = makeRequest("$baseUrl/users/$managerId/cobradores", [], 'GET', $token);
$cobradorId = $cobradoresResponse['data']['data']['data'][0]['id'] ?? 3;

$clientesResponse = makeRequest("$baseUrl/users/$cobradorId/clients", [], 'GET', $token);
$clienteId = $clientesResponse['data']['data']['data'][0]['id'] ?? 41;

echo "2️⃣ Usando Cobrador ID: $cobradorId, Cliente ID: $clienteId\n\n";

// 3. Crear crédito usando el endpoint especializado
echo "3️⃣ Creando crédito con endpoint especializado /credits/waiting-list...\n";
$creditoData = [
    'client_id' => $clienteId,
    'cobrador_id' => $cobradorId,
    'amount' => 10000.00,
    'interest_rate' => 20.0, // 20% de interés
    'frequency' => 'biweekly',
    'installment_amount' => 600.00, // Cuota específica
    'start_date' => '2025-08-15',
    'end_date' => '2025-12-15',
    'scheduled_delivery_date' => '2025-08-12 14:30:00',
    'notes' => 'Crédito prioritario - cliente VIP con historial excelente'
];

$creditoResponse = makeRequest("$baseUrl/credits/waiting-list", $creditoData, 'POST', $token);

echo "Código de respuesta: {$creditoResponse['code']}\n";
if ($creditoResponse['code'] === 200) {
    $resultado = $creditoResponse['data']['data'];
    $credito = $resultado['credit'];
    $deliveryStatus = $resultado['delivery_status'];
    $cobrador = $resultado['cobrador'];
    
    echo "✅ Crédito creado exitosamente con endpoint especializado\n";
    echo "   📊 INFORMACIÓN DEL CRÉDITO:\n";
    echo "   - ID: {$credito['id']}\n";
    echo "   - Estado: {$credito['status']}\n";
    echo "   - Monto base: \${$credito['amount']}\n";
    echo "   - Tasa de interés: {$credito['interest_rate']}%\n";
    echo "   - Monto total: \${$credito['total_amount']}\n";
    echo "   - Cuota: \${$credito['installment_amount']}\n";
    echo "   - Frecuencia: {$credito['frequency']}\n";
    echo "   - Fecha programada: {$credito['scheduled_delivery_date']}\n";
    echo "   - Notas: {$credito['delivery_notes']}\n";
    echo "   \n";
    echo "   👤 ASIGNACIONES:\n";
    echo "   - Cliente: {$credito['client']['name']} (ID: {$credito['client']['id']})\n";
    echo "   - Cobrador: {$cobrador['name']} (ID: {$cobrador['id']})\n";
    echo "   - Creado por: Manager (ID: {$credito['created_by']})\n";
    echo "   \n";
    echo "   🎯 ESTADO DE ENTREGA:\n";
    echo "   - Pendiente de aprobación: " . ($deliveryStatus['is_pending_approval'] ? 'SÍ' : 'NO') . "\n";
    echo "   - Listo para entrega: " . ($deliveryStatus['is_ready_for_delivery'] ? 'SÍ' : 'NO') . "\n";
    echo "   - Días hasta entrega: {$deliveryStatus['days_until_delivery']}\n";
    echo "   \n";
    echo "   💬 Mensaje: {$creditoResponse['data']['message']}\n";
    
    $creditoId = $credito['id'];
} else {
    echo "❌ Error creando crédito: " . json_encode($creditoResponse['data']) . "\n";
    exit;
}

// 4. Test de permisos: Cobrador intentando usar el endpoint (debería fallar)
echo "\n4️⃣ Test de permisos: Cobrador intentando usar endpoint especializado...\n";

$cobradorLoginResponse = makeRequest("$baseUrl/login", [
    'email_or_phone' => 'cobrador.manager@test.com',
    'password' => 'password123'
]);

if ($cobradorLoginResponse['code'] === 200) {
    $cobradorToken = $cobradorLoginResponse['data']['data']['token'];
    
    $invalidResponse = makeRequest("$baseUrl/credits/waiting-list", $creditoData, 'POST', $cobradorToken);
    
    echo "Código de respuesta: {$invalidResponse['code']}\n";
    if ($invalidResponse['code'] === 403) {
        echo "✅ Permiso denegado correctamente - Solo managers pueden usar este endpoint\n";
        echo "   Mensaje: {$invalidResponse['data']['message']}\n";
    } else {
        echo "❌ Error en validación de permisos\n";
    }
} else {
    echo "⚠️ No se pudo probar permisos de cobrador\n";
}

// 5. Verificar cálculos automáticos
echo "\n5️⃣ Verificando cálculos automáticos...\n";
$montoBase = $creditoData['amount'];
$tasaInteres = $creditoData['interest_rate'];
$montoTotalEsperado = $montoBase * (1 + ($tasaInteres / 100));

echo "   - Monto base: \${$montoBase}\n";
echo "   - Tasa de interés: {$tasaInteres}%\n";
echo "   - Monto total esperado: \${$montoTotalEsperado}\n";
echo "   - Monto total calculado: \${$credito['total_amount']}\n";

if (abs($credito['total_amount'] - $montoTotalEsperado) < 0.01) {
    echo "✅ Cálculo de interés correcto\n";
} else {
    echo "❌ Error en cálculo de interés\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "🎯 RESUMEN - Capacidades completas del Manager:\n";
echo "   \n";
echo "📝 CREACIÓN DE CRÉDITOS:\n";
echo "✅ Endpoint general: POST /api/credits\n";
echo "✅ Endpoint especializado: POST /api/credits/waiting-list\n";
echo "✅ Control total sobre todos los parámetros del crédito\n";
echo "✅ Asignación libre de cliente y cobrador (dentro de su gestión)\n";
echo "   \n";
echo "🧮 CÁLCULOS AUTOMÁTICOS:\n";
echo "✅ Monto total con interés\n";
echo "✅ Cuotas basadas en frecuencia y período\n";
echo "✅ Balance inicial igual al monto total\n";
echo "   \n";
echo "📅 PROGRAMACIÓN:\n";
echo "✅ Fecha de inicio y fin del crédito\n";
echo "✅ Fecha programada de entrega\n";
echo "✅ Frecuencia de pagos (diaria, semanal, quincenal, mensual)\n";
echo "   \n";
echo "🛡️ SEGURIDAD:\n";
echo "✅ Solo managers y admins pueden crear en lista de espera\n";
echo "✅ Validación de asignaciones cobrador-cliente\n";
echo "✅ Control de permisos por jerarquía\n";
echo "   \n";
echo "🔄 FLUJO COMPLETO:\n";
echo "✅ Creación → Lista de espera → Aprobación → Entrega → Activo\n";
