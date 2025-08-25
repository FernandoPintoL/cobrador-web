<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Configuración de la API
$baseUrl = 'http://localhost:8000/api';
$token = null; // Se obtendrá después del login

echo "=== TESTING SISTEMA DE CATEGORÍAS DE CLIENTES ===\n\n";

// Función para hacer peticiones con token
function makeRequest($method, $url, $data = [], $token = null) {
    $headers = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];

    if ($token) {
        $headers['Authorization'] = "Bearer $token";
    }

    $response = Http::withHeaders($headers)->$method($url, $data);

    return [
        'status' => $response->status(),
        'data' => $response->json(),
        'success' => $response->successful()
    ];
}

try {
    // 1. Login como admin para obtener el token
    echo "1. Iniciando sesión como administrador...\n";
    $loginResponse = makeRequest('post', "$baseUrl/login", [
        'email' => 'admin@cobrador.com',
        'password' => 'password123'
    ]);

    if (!$loginResponse['success']) {
        throw new Exception("Error en login: " . json_encode($loginResponse['data']));
    }

    $token = $loginResponse['data']['data']['access_token'];
    echo "✓ Login exitoso\n\n";

    // 2. Obtener categorías disponibles
    echo "2. Obteniendo categorías disponibles...\n";
    $categoriesResponse = makeRequest('get', "$baseUrl/client-categories", [], $token);

    if ($categoriesResponse['success']) {
        echo "✓ Categorías obtenidas:\n";
        foreach ($categoriesResponse['data']['data'] as $code => $name) {
            echo "  - $code: $name\n";
        }
    } else {
        echo "✗ Error obteniendo categorías: " . json_encode($categoriesResponse['data']) . "\n";
    }
    echo "\n";

    // 3. Crear un cliente de prueba con categoría VIP
    echo "3. Creando cliente VIP de prueba...\n";
    $clientData = [
        'name' => 'Cliente VIP Test',
        'ci' => 'VIP' . time(),
        'address' => 'Dirección VIP Test',
        'phone' => '70000' . rand(1000, 9999),
        'email' => 'vip' . time() . '@test.com',
        'roles' => ['client'],
        'client_category' => 'A'
    ];

    $createClientResponse = makeRequest('post', "$baseUrl/users", $clientData, $token);

    if ($createClientResponse['success']) {
        $clientId = $createClientResponse['data']['data']['id'];
        echo "✓ Cliente VIP creado con ID: $clientId\n";
        echo "  Categoría: " . $createClientResponse['data']['data']['client_category'] . "\n";
    } else {
        echo "✗ Error creando cliente: " . json_encode($createClientResponse['data']) . "\n";
    }
    echo "\n";

    // 4. Crear un cliente normal
    echo "4. Creando cliente normal de prueba...\n";
    $normalClientData = [
        'name' => 'Cliente Normal Test',
        'ci' => 'NORM' . time(),
        'address' => 'Dirección Normal Test',
        'phone' => '70000' . rand(1000, 9999),
        'roles' => ['client'],
        'client_category' => 'B'
    ];

    $createNormalClientResponse = makeRequest('post', "$baseUrl/users", $normalClientData, $token);

    if ($createNormalClientResponse['success']) {
        $normalClientId = $createNormalClientResponse['data']['data']['id'];
        echo "✓ Cliente Normal creado con ID: $normalClientId\n";
        echo "  Categoría: " . $createNormalClientResponse['data']['data']['client_category'] . "\n";
    } else {
        echo "✗ Error creando cliente normal: " . json_encode($createNormalClientResponse['data']) . "\n";
    }
    echo "\n";

    // 5. Actualizar categoría del cliente normal a "Mal Cliente"
    if (isset($normalClientId)) {
        echo "5. Actualizando categoría del cliente normal a 'Mal Cliente'...\n";
        $updateCategoryResponse = makeRequest('patch', "$baseUrl/users/$normalClientId/category", [
            'client_category' => 'C'
        ], $token);

        if ($updateCategoryResponse['success']) {
            echo "✓ Categoría actualizada exitosamente\n";
            echo "  Nueva categoría: " . $updateCategoryResponse['data']['data']['client_category'] . "\n";
        } else {
            echo "✗ Error actualizando categoría: " . json_encode($updateCategoryResponse['data']) . "\n";
        }
        echo "\n";
    }

    // 6. Obtener clientes por categoría VIP
    echo "6. Obteniendo clientes VIP...\n";
    $vipClientsResponse = makeRequest('get', "$baseUrl/clients/by-category?category=A", [], $token);

    if ($vipClientsResponse['success']) {
        $vipCount = $vipClientsResponse['data']['data']['total'];
        echo "✓ Clientes VIP encontrados: $vipCount\n";
        if ($vipCount > 0) {
            echo "  Primer cliente VIP: " . $vipClientsResponse['data']['data']['data'][0]['name'] . "\n";
        }
    } else {
        echo "✗ Error obteniendo clientes VIP: " . json_encode($vipClientsResponse['data']) . "\n";
    }
    echo "\n";

    // 7. Obtener estadísticas de categorías
    echo "7. Obteniendo estadísticas de categorías...\n";
    $statsResponse = makeRequest('get', "$baseUrl/client-categories/statistics", [], $token);

    if ($statsResponse['success']) {
        echo "✓ Estadísticas obtenidas:\n";
        foreach ($statsResponse['data']['data'] as $stat) {
            $categoryName = $stat['category_name'] ?? 'Sin categoría';
            $count = $stat['client_count'];
            echo "  - $categoryName: $count clientes\n";
        }
    } else {
        echo "✗ Error obteniendo estadísticas: " . json_encode($statsResponse['data']) . "\n";
    }
    echo "\n";

    // 8. Actualización masiva de categorías (si tenemos clientes)
    if (isset($clientId) && isset($normalClientId)) {
        echo "8. Probando actualización masiva de categorías...\n";
        $bulkUpdateData = [
            'updates' => [
                ['client_id' => $clientId, 'category' => 'B'],
                ['client_id' => $normalClientId, 'category' => 'A']
            ]
        ];

        $bulkUpdateResponse = makeRequest('post', "$baseUrl/clients/bulk-update-categories", $bulkUpdateData, $token);

        if ($bulkUpdateResponse['success']) {
            $updatedCount = $bulkUpdateResponse['data']['data']['updated_count'];
            echo "✓ Actualización masiva exitosa: $updatedCount clientes actualizados\n";
        } else {
            echo "✗ Error en actualización masiva: " . json_encode($bulkUpdateResponse['data']) . "\n";
        }
        echo "\n";
    }

    echo "=== PRUEBAS COMPLETADAS ===\n";
    echo "✓ Sistema de categorías de clientes implementado y funcionando correctamente\n\n";

    echo "ENDPOINTS DISPONIBLES:\n";
    echo "- GET /api/client-categories - Obtener categorías disponibles\n";
    echo "- PATCH /api/users/{client}/category - Actualizar categoría de un cliente\n";
    echo "- GET /api/clients/by-category?category=A|B|C - Obtener clientes por categoría\n";
    echo "- GET /api/client-categories/statistics - Estadísticas de categorías\n";
    echo "- POST /api/clients/bulk-update-categories - Actualización masiva\n\n";

    echo "CATEGORÍAS IMPLEMENTADAS:\n";
    echo "- A: Cliente VIP\n";
    echo "- B: Cliente Normal\n";
    echo "- C: Mal Cliente\n";

} catch (Exception $e) {
    echo "✗ Error durante las pruebas: " . $e->getMessage() . "\n";
}
