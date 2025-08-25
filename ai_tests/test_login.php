<?php

// Función helper para hacer requests
function makeRequest($method, $url, $data = null, $token = null) {
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "http://127.0.0.1:8000/api" . $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            $token ? "Authorization: Bearer $token" : 'X-Test: true'
        ],
    ]);

    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    return [
        'status' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

echo "🔐 Probando login...\n";

$credentials = [
    ['email_or_phone' => 'admin@test.com', 'password' => 'password123'],
    ['email_or_phone' => 'admin@cobrador.com', 'password' => 'password'],
    ['email_or_phone' => 'manager@test.com', 'password' => 'password123'],
];

foreach ($credentials as $cred) {
    echo "Probando: {$cred['email_or_phone']} / {$cred['password']}\n";
    $response = makeRequest('POST', '/login', $cred);
    echo "Status: {$response['status']}\n";
    echo "Response: " . json_encode($response['body'], JSON_PRETTY_PRINT) . "\n\n";

    if ($response['status'] === 200) {
        echo "✅ Login exitoso!\n";
        break;
    }
}
