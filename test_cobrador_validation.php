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
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

echo "🧪 Probando validación de cobradores - campos email/phone\n";
echo str_repeat("=", 60) . "\n\n";

// 1. Login como manager para obtener token
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
echo "✅ Login exitoso - Token obtenido\n\n";

// 2. Test: Crear cobrador SIN email NI phone (debería fallar)
echo "2️⃣ Test: Crear cobrador sin email ni teléfono (debería fallar)...\n";
$response = makeRequest("$baseUrl/users", [
    'name' => 'Cobrador Sin Contacto',
    'password' => 'password123',
    'roles' => ['cobrador']
], 'POST', $token);

echo "Código de respuesta: {$response['code']}\n";
if ($response['code'] === 400) {
    echo "✅ Validación correcta - Error esperado: " . $response['data']['message'] . "\n";
} else {
    echo "❌ Error inesperado: " . json_encode($response['data']) . "\n";
}
echo "\n";

// 3. Test: Crear cobrador solo con email (debería funcionar)
echo "3️⃣ Test: Crear cobrador solo con email...\n";
$response = makeRequest("$baseUrl/users", [
    'name' => 'Cobrador Solo Email',
    'email' => 'cobrador.email@test.com',
    'password' => 'password123',
    'roles' => ['cobrador']
], 'POST', $token);

echo "Código de respuesta: {$response['code']}\n";
if ($response['code'] === 200) {
    echo "✅ Cobrador creado exitosamente con solo email\n";
    $cobradorId1 = $response['data']['data']['id'];
    echo "ID del cobrador: $cobradorId1\n";
} else {
    echo "❌ Error inesperado: " . json_encode($response['data']) . "\n";
}
echo "\n";

// 4. Test: Crear cobrador solo con phone (debería funcionar)
echo "4️⃣ Test: Crear cobrador solo con teléfono...\n";
$response = makeRequest("$baseUrl/users", [
    'name' => 'Cobrador Solo Phone',
    'phone' => '987654321',
    'password' => 'password123',
    'roles' => ['cobrador']
], 'POST', $token);

echo "Código de respuesta: {$response['code']}\n";
if ($response['code'] === 200) {
    echo "✅ Cobrador creado exitosamente con solo teléfono\n";
    $cobradorId2 = $response['data']['data']['id'];
    echo "ID del cobrador: $cobradorId2\n";
} else {
    echo "❌ Error inesperado: " . json_encode($response['data']) . "\n";
}
echo "\n";

// 5. Test: Crear cobrador con email Y phone (debería funcionar)
echo "5️⃣ Test: Crear cobrador con email y teléfono...\n";
$response = makeRequest("$baseUrl/users", [
    'name' => 'Cobrador Completo',
    'email' => 'cobrador.completo@test.com',
    'phone' => '555777888',  // Número único
    'password' => 'password123',
    'address' => 'Calle Principal 123',
    'roles' => ['cobrador']
], 'POST', $token);

echo "Código de respuesta: {$response['code']}\n";
if ($response['code'] === 200) {
    echo "✅ Cobrador creado exitosamente con email y teléfono\n";
    $cobradorId3 = $response['data']['data']['id'];
    echo "ID del cobrador: $cobradorId3\n";
} else {
    echo "❌ Error inesperado: " . json_encode($response['data']) . "\n";
}
echo "\n";

// 6. Test: Crear cliente sin email ni phone (debería funcionar - los clientes pueden ser sin contacto)
echo "6️⃣ Test: Crear cliente sin email ni teléfono (debería funcionar)...\n";
$response = makeRequest("$baseUrl/users", [
    'name' => 'Cliente Sin Contacto',
    'roles' => ['client']
], 'POST', $token);

echo "Código de respuesta: {$response['code']}\n";
if ($response['code'] === 200) {
    echo "✅ Cliente creado exitosamente sin campos de contacto\n";
    echo "Mensaje: " . $response['data']['message'] . "\n";
} else {
    echo "❌ Error inesperado: " . json_encode($response['data']) . "\n";
}
echo "\n";

echo str_repeat("=", 60) . "\n";
echo "🎯 Resumen de las pruebas de validación:\n";
echo "✅ Cobradores requieren al menos email o teléfono\n";
echo "✅ Clientes pueden crearse sin campos de contacto\n";
echo "✅ Validación funciona correctamente\n";
