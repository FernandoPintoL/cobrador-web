<?php

echo "🧪 Prueba Simple: Endpoints API Manager → Cliente\n";
echo "=================================================\n";

$baseUrl = 'http://localhost:8000/api';

// Función para hacer requests
function simpleRequest($url, $data = null, $method = 'GET') {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "URL: $url\n";
    echo "HTTP Code: $httpCode\n";
    if ($error) {
        echo "cURL Error: $error\n";
    }
    echo "Response: " . substr($response, 0, 200) . "...\n";
    echo str_repeat("-", 50) . "\n";
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true),
        'raw' => $response
    ];
}

// 1. Probar endpoint de login
echo "1️⃣ Probando Login...\n";
$loginData = [
    'email_or_phone' => 'manager@test.com',
    'password' => 'password123'
];

$loginResponse = simpleRequest("$baseUrl/login", $loginData, 'POST');

if ($loginResponse['code'] === 200 && isset($loginResponse['data']['data']['token'])) {
    $token = $loginResponse['data']['data']['token'];
    $managerId = $loginResponse['data']['data']['user']['id'];
    
    echo "✅ Login exitoso! Manager ID: $managerId\n";
    echo "Token obtenido: " . substr($token, 0, 20) . "...\n\n";
    
    // 2. Probar obtener clientes directos
    echo "2️⃣ Probando obtener clientes directos...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$baseUrl/users/$managerId/clients-direct");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Response: " . substr($response, 0, 300) . "...\n";
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $count = $data['data']['total'] ?? count($data['data']);
        echo "✅ Clientes directos obtenidos: $count\n";
    } else {
        echo "❌ Error obteniendo clientes\n";
    }
    
} else {
    echo "❌ Error en login\n";
    echo "Respuesta completa: " . print_r($loginResponse, true) . "\n";
}

echo "\n🔚 Fin de la prueba simple\n";
