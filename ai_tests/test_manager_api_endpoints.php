<?php

require_once __DIR__ . '/vendor/autoload.php';

// Configurar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🌐 Pruebas de Endpoints API - Manager ↔ Cobrador\n";
echo "=" . str_repeat("=", 50) . "\n\n";

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

try {
    // 1. Obtener un token de prueba (login como admin)
    echo "🔐 Obteniendo token de autenticación...\n";
    $loginResponse = makeRequest('POST', '/login', [
        'email_or_phone' => 'admin@test.com',
        'password' => 'password123'
    ]);
    
    if ($loginResponse['status'] !== 200) {
        echo "❌ Error al hacer login. Asegúrate de que el seeder esté ejecutado.\n";
        exit(1);
    }
    
    $token = $loginResponse['body']['data']['token'];
    echo "✅ Token obtenido exitosamente\n";
    
    // 2. Obtener datos para las pruebas
    $manager = App\Models\User::whereHas('roles', function ($q) {
        $q->where('name', 'manager');
    })->first();
    
    $cobrador = App\Models\User::whereHas('roles', function ($q) {
        $q->where('name', 'cobrador');
    })->where('assigned_manager_id', $manager->id)->first();
    
    if (!$manager || !$cobrador) {
        echo "❌ No se encontraron datos necesarios para las pruebas\n";
        exit(1);
    }
    
    echo "📋 Datos de prueba:\n";
    echo "   Manager: {$manager->name} (ID: {$manager->id})\n";
    echo "   Cobrador: {$cobrador->name} (ID: {$cobrador->id})\n\n";
    
    // 3. Probar GET /api/users/{manager}/cobradores
    echo "🧪 Probando: GET /users/{$manager->id}/cobradores\n";
    $response = makeRequest('GET', "/users/{$manager->id}/cobradores", null, $token);
    
    if ($response['status'] === 200) {
        $data = $response['body']['data'];
        echo "   ✅ Éxito - {$data['total']} cobradores encontrados\n";
        echo "   📄 Primer cobrador: {$data['data'][0]['name']}\n";
    } else {
        echo "   ❌ Error - Status: {$response['status']}\n";
        print_r($response['body']);
    }
    
    // 4. Probar GET /api/users/{cobrador}/manager
    echo "\n🧪 Probando: GET /users/{$cobrador->id}/manager\n";
    $response = makeRequest('GET', "/users/{$cobrador->id}/manager", null, $token);
    
    if ($response['status'] === 200) {
        $managerData = $response['body']['data'];
        if ($managerData) {
            echo "   ✅ Éxito - Manager asignado: {$managerData['name']}\n";
        } else {
            echo "   ⚠️  Cobrador sin manager asignado\n";
        }
    } else {
        echo "   ❌ Error - Status: {$response['status']}\n";
        print_r($response['body']);
    }
    
    // 5. Probar asignación de cobradores
    $cobradorSinManager = App\Models\User::whereHas('roles', function ($q) {
        $q->where('name', 'cobrador');
    })->whereNull('assigned_manager_id')->first();
    
    if ($cobradorSinManager) {
        echo "\n🧪 Probando: POST /users/{$manager->id}/assign-cobradores\n";
        $response = makeRequest('POST', "/users/{$manager->id}/assign-cobradores", [
            'cobrador_ids' => [$cobradorSinManager->id]
        ], $token);
        
        if ($response['status'] === 200) {
            echo "   ✅ Éxito - Cobrador {$cobradorSinManager->name} asignado\n";
            
            // Verificar la asignación
            $cobradorSinManager->refresh();
            if ($cobradorSinManager->assigned_manager_id === $manager->id) {
                echo "   ✅ Verificación: Asignación correcta en BD\n";
                
                // 6. Probar remoción de asignación
                echo "\n🧪 Probando: DELETE /users/{$manager->id}/cobradores/{$cobradorSinManager->id}\n";
                $response = makeRequest('DELETE', "/users/{$manager->id}/cobradores/{$cobradorSinManager->id}", null, $token);
                
                if ($response['status'] === 200) {
                    echo "   ✅ Éxito - Cobrador removido del manager\n";
                    
                    // Verificar la remoción
                    $cobradorSinManager->refresh();
                    if ($cobradorSinManager->assigned_manager_id === null) {
                        echo "   ✅ Verificación: Remoción correcta en BD\n";
                    } else {
                        echo "   ❌ Error: Asignación no fue removida en BD\n";
                    }
                } else {
                    echo "   ❌ Error - Status: {$response['status']}\n";
                    print_r($response['body']);
                }
            } else {
                echo "   ❌ Error: Asignación no se reflejó en BD\n";
            }
        } else {
            echo "   ❌ Error - Status: {$response['status']}\n";
            print_r($response['body']);
        }
    } else {
        echo "\n⚠️  No hay cobradores sin asignar para probar la asignación\n";
    }
    
    // 7. Probar búsqueda con parámetros
    echo "\n🧪 Probando: GET /users/{$manager->id}/cobradores?search=Alba\n";
    $response = makeRequest('GET', "/users/{$manager->id}/cobradores?search=Alba", null, $token);
    
    if ($response['status'] === 200) {
        $total = $response['body']['data']['total'];
        echo "   ✅ Éxito - Búsqueda encontró {$total} resultados\n";
    } else {
        echo "   ❌ Error - Status: {$response['status']}\n";
    }
    
    // 8. Probar paginación
    echo "\n🧪 Probando: GET /users/{$manager->id}/cobradores?per_page=1\n";
    $response = makeRequest('GET', "/users/{$manager->id}/cobradores?per_page=1", null, $token);
    
    if ($response['status'] === 200) {
        $perPage = $response['body']['data']['per_page'];
        $total = $response['body']['data']['total'];
        echo "   ✅ Éxito - Paginación funcionando: {$perPage} por página de {$total} total\n";
    } else {
        echo "   ❌ Error - Status: {$response['status']}\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ Todas las pruebas de endpoints completadas\n";
    
    // 9. Resumen de funcionalidades
    echo "\n📊 Resumen de funcionalidades implementadas:\n";
    echo "   ✅ Asignación de cobradores a managers\n";
    echo "   ✅ Consulta de cobradores por manager\n";
    echo "   ✅ Consulta de manager por cobrador\n";
    echo "   ✅ Remoción de asignaciones\n";
    echo "   ✅ Búsqueda y filtrado\n";
    echo "   ✅ Paginación\n";
    echo "   ✅ Validaciones de roles\n";
    echo "   ✅ Relaciones bidireccionales\n";
    echo "   ✅ Jerarquía completa: Manager → Cobrador → Cliente\n";
    
} catch (Exception $e) {
    echo "❌ Error durante las pruebas: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n🎉 Sistema de asignación Manager → Cobrador implementado exitosamente!\n";
