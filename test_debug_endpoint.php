<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Models\User;

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PRUEBA DEL ENDPOINT DEBUG COBRADOR ===\n\n";

// Buscar un cobrador
$cobrador = User::whereHas('roles', function ($query) {
    $query->where('name', 'cobrador');
})->first();

if (! $cobrador) {
    echo "❌ No se encontró ningún cobrador\n";
    exit;
}

echo "👤 Probando con cobrador: {$cobrador->name} (ID: {$cobrador->id})\n";

// Crear token de acceso personal para el cobrador
$token = $cobrador->createToken('debug-test')->plainTextToken;

echo '🔑 Token generado: '.substr($token, 0, 20)."...\n\n";

// Hacer solicitud HTTP al endpoint de debug
$baseUrl = 'http://localhost:8000/api';
$url = $baseUrl.'/debug-cobrador-credits';

$headers = [
    'Authorization: Bearer '.$token,
    'Content-Type: application/json',
    'Accept: application/json',
];

echo "🌐 Haciendo solicitud a: {$url}\n";
echo "📋 Headers:\n";
foreach ($headers as $header) {
    if (strpos($header, 'Authorization') !== false) {
        echo "  - Authorization: Bearer [TOKEN]\n";
    } else {
        echo "  - {$header}\n";
    }
}
echo "\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

if ($error) {
    echo "❌ Error de cURL: {$error}\n";
    exit;
}

echo "📊 Código HTTP: {$httpCode}\n";
echo "📄 Respuesta:\n";
echo "----------------------------------------\n";

if ($response) {
    $data = json_decode($response, true);
    if ($data) {
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    } else {
        echo "Respuesta no es JSON válido:\n";
        echo $response."\n";
    }
} else {
    echo "Sin respuesta\n";
}

echo "----------------------------------------\n";

// Limpiar el token después de la prueba
$cobrador->tokens()->delete();
echo "\n✅ Token temporal eliminado\n";

echo "\n=== PRUEBA COMPLETADA ===\n";
