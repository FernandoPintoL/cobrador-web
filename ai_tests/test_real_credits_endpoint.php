<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PRUEBA DEL ENDPOINT REAL CREDITS INDEX ===\n\n";

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
$token = $cobrador->createToken('test-index')->plainTextToken;

echo '🔑 Token generado: '.substr($token, 0, 20)."...\n\n";

// Hacer múltiples solicitudes con diferentes parámetros
$baseUrl = 'http://localhost:8000/api';
$scenarios = [
    'Sin filtros' => '/credits',
    'Solo paginación' => '/credits?per_page=10',
    'Filtro por estado active' => '/credits?status=active',
    'Filtro por estado pending_approval' => '/credits?status=pending_approval',
    'Filtro por status vacío' => '/credits?status=',
];

$headers = [
    'Authorization: Bearer '.$token,
    'Content-Type: application/json',
    'Accept: application/json',
];

foreach ($scenarios as $name => $endpoint) {
    echo "🔍 PROBANDO: {$name}\n";
    echo "📍 URL: {$baseUrl}{$endpoint}\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl.$endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "❌ Error de cURL: {$error}\n\n";

        continue;
    }

    echo "📊 Código HTTP: {$httpCode}\n";

    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['data'])) {
            if (isset($data['data']['data'])) {
                // Estructura paginada
                $credits = $data['data']['data'];
                $total = $data['data']['total'] ?? 0;
                echo "📈 Total encontrado: {$total}\n";
                echo '📋 Créditos en esta página: '.count($credits)."\n";

                foreach ($credits as $credit) {
                    echo "  - Crédito ID: {$credit['id']}, Cliente: {$credit['client']['name']}, Estado: {$credit['status']}\n";
                }
            } else {
                // Respuesta directa
                if (is_array($data['data'])) {
                    echo '📋 Créditos encontrados: '.count($data['data'])."\n";
                    foreach ($data['data'] as $credit) {
                        echo "  - Crédito ID: {$credit['id']}, Cliente: {$credit['client']['name']}, Estado: {$credit['status']}\n";
                    }
                }
            }
        } else {
            echo "📋 Respuesta sin datos o formato inesperado\n";
        }
    } else {
        echo "📋 Sin respuesta\n";
    }

    echo "----------------------------------------\n\n";
}

// Limpiar el token después de la prueba
$cobrador->tokens()->delete();
echo "✅ Token temporal eliminado\n";

echo "\n=== PRUEBA COMPLETADA ===\n";
