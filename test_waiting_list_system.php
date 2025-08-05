<?php

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Script de prueba para el sistema de lista de espera de créditos
 */

$baseUrl = 'http://127.0.0.1:8000/api';
$token = null;

// Función para hacer requests HTTP
function makeRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

// 1. Login como manager
echo "🔐 Autenticando como manager...\n";
$loginResponse = makeRequest('POST', $baseUrl . '/login', [
    'email' => 'manager@test.com',
    'password' => 'password123'
]);

if ($loginResponse['status'] === 200 && isset($loginResponse['body']['token'])) {
    $token = $loginResponse['body']['token'];
    echo "✅ Autenticación exitosa\n\n";
} else {
    echo "❌ Error en autenticación: " . json_encode($loginResponse['body']) . "\n";
    exit(1);
}

// 2. Obtener resumen de lista de espera
echo "📊 Obteniendo resumen de lista de espera...\n";
$summaryResponse = makeRequest('GET', $baseUrl . '/credits/waiting-list/summary', null, $token);
if ($summaryResponse['status'] === 200) {
    echo "✅ Resumen obtenido:\n";
    echo "   - Pendientes de aprobación: " . $summaryResponse['body']['data']['pending_approval'] . "\n";
    echo "   - En espera de entrega: " . $summaryResponse['body']['data']['waiting_delivery'] . "\n";
    echo "   - Listos hoy: " . $summaryResponse['body']['data']['ready_today'] . "\n";
    echo "   - Atrasados: " . $summaryResponse['body']['data']['overdue_delivery'] . "\n\n";
} else {
    echo "❌ Error obteniendo resumen: " . json_encode($summaryResponse['body']) . "\n\n";
}

// 3. Obtener créditos pendientes de aprobación
echo "📋 Obteniendo créditos pendientes de aprobación...\n";
$pendingResponse = makeRequest('GET', $baseUrl . '/credits/waiting-list/pending-approval', null, $token);
if ($pendingResponse['status'] === 200) {
    $pendingCredits = $pendingResponse['body']['data'];
    echo "✅ Encontrados " . count($pendingCredits) . " créditos pendientes:\n";
    
    foreach ($pendingCredits as $credit) {
        echo "   - ID: {$credit['id']} | Cliente: {$credit['client']['name']} | Monto: {$credit['total_amount']} Bs\n";
    }
    
    // Aprobar el primer crédito si existe
    if (count($pendingCredits) > 0) {
        $creditId = $pendingCredits[0]['id'];
        echo "\n🎯 Aprobando crédito ID: $creditId...\n";
        
        $approveResponse = makeRequest('POST', $baseUrl . "/credits/$creditId/waiting-list/approve", [
            'scheduled_delivery_date' => date('Y-m-d H:i:s', strtotime('+2 days 10:00')),
            'notes' => 'Aprobado para entrega en 2 días'
        ], $token);
        
        if ($approveResponse['status'] === 200) {
            echo "✅ Crédito aprobado exitosamente\n";
            echo "   - Fecha programada: " . $approveResponse['body']['data']['delivery_status']['scheduled_delivery_date'] . "\n";
        } else {
            echo "❌ Error aprobando crédito: " . json_encode($approveResponse['body']) . "\n";
        }
    }
} else {
    echo "❌ Error obteniendo créditos pendientes: " . json_encode($pendingResponse['body']) . "\n";
}

echo "\n";

// 4. Obtener créditos listos para entrega hoy
echo "🚀 Obteniendo créditos listos para entrega hoy...\n";
$readyResponse = makeRequest('GET', $baseUrl . '/credits/waiting-list/ready-today', null, $token);
if ($readyResponse['status'] === 200) {
    $readyCredits = $readyResponse['body']['data'];
    echo "✅ Encontrados " . count($readyCredits) . " créditos listos:\n";
    
    foreach ($readyCredits as $credit) {
        echo "   - ID: {$credit['id']} | Cliente: {$credit['client']['name']} | Hora: " . 
             date('H:i', strtotime($credit['scheduled_delivery_date'])) . "\n";
    }
} else {
    echo "❌ Error obteniendo créditos listos: " . json_encode($readyResponse['body']) . "\n";
}

echo "\n";

// 5. Login como cobrador para probar entrega
echo "🔐 Autenticando como cobrador...\n";
$cobradorLoginResponse = makeRequest('POST', $baseUrl . '/login', [
    'email' => 'cobrador@test.com',
    'password' => 'password123'
]);

if ($cobradorLoginResponse['status'] === 200 && isset($cobradorLoginResponse['body']['token'])) {
    $cobradorToken = $cobradorLoginResponse['body']['token'];
    echo "✅ Autenticación de cobrador exitosa\n\n";
    
    // Entregar un crédito si está listo
    if (isset($readyCredits) && count($readyCredits) > 0) {
        $creditId = $readyCredits[0]['id'];
        echo "📦 Entregando crédito ID: $creditId...\n";
        
        $deliverResponse = makeRequest('POST', $baseUrl . "/credits/$creditId/waiting-list/deliver", [
            'notes' => 'Entregado en efectivo en domicilio del cliente'
        ], $cobradorToken);
        
        if ($deliverResponse['status'] === 200) {
            echo "✅ Crédito entregado exitosamente\n";
            echo "   - Status actual: " . $deliverResponse['body']['data']['delivery_status']['status'] . "\n";
        } else {
            echo "❌ Error entregando crédito: " . json_encode($deliverResponse['body']) . "\n";
        }
    }
} else {
    echo "❌ Error en autenticación de cobrador\n";
}

echo "\n";

// 6. Verificar resumen final
echo "📊 Resumen final de lista de espera...\n";
$finalSummaryResponse = makeRequest('GET', $baseUrl . '/credits/waiting-list/summary', null, $token);
if ($finalSummaryResponse['status'] === 200) {
    echo "✅ Resumen final:\n";
    echo "   - Pendientes de aprobación: " . $finalSummaryResponse['body']['data']['pending_approval'] . "\n";
    echo "   - En espera de entrega: " . $finalSummaryResponse['body']['data']['waiting_delivery'] . "\n";
    echo "   - Listos hoy: " . $finalSummaryResponse['body']['data']['ready_today'] . "\n";
    echo "   - Atrasados: " . $finalSummaryResponse['body']['data']['overdue_delivery'] . "\n";
} else {
    echo "❌ Error obteniendo resumen final\n";
}

echo "\n🎉 Pruebas del sistema de lista de espera completadas!\n";
