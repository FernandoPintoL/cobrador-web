<?php

/**
 * Test script para el endpoint de cambio de contraseñas
 *
 * Este script demuestra cómo usar el nuevo endpoint PATCH /api/users/{user}/change-password
 * que permite a managers y admins cambiar contraseñas de usuarios con roles inferiores
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

// Configuración base para las pruebas
$baseUrl = 'http://localhost:8000/api';

/**
 * Función para hacer llamadas HTTP
 */
function makeRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
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

echo "=== TEST ENDPOINT CAMBIO DE CONTRASEÑAS ===\n\n";

// 1. Login como admin
echo "1. Login como admin...\n";
$loginResponse = makeRequest('POST', $baseUrl . '/login', [
    'email' => 'admin@cobrador.com',
    'password' => 'password123'
]);

if ($loginResponse['status'] !== 200) {
    echo "Error en login de admin: " . json_encode($loginResponse) . "\n";
    exit;
}

$adminToken = $loginResponse['body']['data']['token'];
echo "✓ Admin logueado exitosamente\n\n";

// 2. Obtener lista de cobradores para encontrar uno para cambiar contraseña
echo "2. Obteniendo lista de cobradores...\n";
$cobradoresResponse = makeRequest('GET', $baseUrl . '/users/by-roles?roles=cobrador', null, $adminToken);

if ($cobradoresResponse['status'] !== 200 || empty($cobradoresResponse['body']['data']['data'])) {
    echo "No se encontraron cobradores para la prueba\n";
    exit;
}

$cobrador = $cobradoresResponse['body']['data']['data'][0];
echo "✓ Cobrador seleccionado: {$cobrador['name']} (ID: {$cobrador['id']})\n\n";

// 3. Admin cambia contraseña del cobrador
echo "3. Admin cambiando contraseña del cobrador...\n";
$changePasswordResponse = makeRequest('PATCH', $baseUrl . "/users/{$cobrador['id']}/change-password", [
    'new_password' => 'nuevaPassword123',
    'new_password_confirmation' => 'nuevaPassword123'
], $adminToken);

echo "Status Code: {$changePasswordResponse['status']}\n";
echo "Response: " . json_encode($changePasswordResponse['body'], JSON_PRETTY_PRINT) . "\n\n";

if ($changePasswordResponse['status'] === 200) {
    echo "✓ Contraseña cambiada exitosamente por admin\n\n";

    // 4. Verificar que el cobrador puede hacer login con la nueva contraseña
    echo "4. Verificando login del cobrador con nueva contraseña...\n";
    $cobradorLoginResponse = makeRequest('POST', $baseUrl . '/login', [
        'email' => $cobrador['email'] ?? '',
        'phone' => $cobrador['phone'] ?? '',
        'password' => 'nuevaPassword123'
    ]);

    if ($cobradorLoginResponse['status'] === 200) {
        echo "✓ Cobrador puede hacer login con nueva contraseña\n";
        $cobradorToken = $cobradorLoginResponse['body']['data']['token'];
    } else {
        echo "✗ Error en login del cobrador: " . json_encode($cobradorLoginResponse['body']) . "\n";
    }
} else {
    echo "✗ Error cambiando contraseña: " . json_encode($changePasswordResponse['body']) . "\n";
}

// 5. Ahora probar con un manager
echo "\n5. Probando con manager...\n";

// Login como manager
$managerLoginResponse = makeRequest('POST', $baseUrl . '/login', [
    'email' => 'manager@cobrador.com',
    'password' => 'password123'
]);

if ($managerLoginResponse['status'] === 200) {
    $managerToken = $managerLoginResponse['body']['data']['token'];
    $managerId = $managerLoginResponse['body']['data']['user']['id'];
    echo "✓ Manager logueado exitosamente\n";

    // Obtener cobradores asignados al manager
    $cobradoresManagerResponse = makeRequest('GET', $baseUrl . "/users/{$managerId}/cobradores", null, $managerToken);

    if ($cobradoresManagerResponse['status'] === 200 && !empty($cobradoresManagerResponse['body']['data']['data'])) {
        $cobradorAsignado = $cobradoresManagerResponse['body']['data']['data'][0];
        echo "✓ Cobrador asignado encontrado: {$cobradorAsignado['name']} (ID: {$cobradorAsignado['id']})\n";

        // Manager intenta cambiar contraseña de su cobrador asignado
        echo "6. Manager cambiando contraseña de cobrador asignado...\n";
        $managerChangeResponse = makeRequest('PATCH', $baseUrl . "/users/{$cobradorAsignado['id']}/change-password", [
            'new_password' => 'passwordManager123',
            'new_password_confirmation' => 'passwordManager123'
        ], $managerToken);

        echo "Status Code: {$managerChangeResponse['status']}\n";
        echo "Response: " . json_encode($managerChangeResponse['body'], JSON_PRETTY_PRINT) . "\n";

        if ($managerChangeResponse['status'] === 200) {
            echo "✓ Manager cambió contraseña de cobrador asignado exitosamente\n";
        } else {
            echo "✗ Error: Manager no pudo cambiar contraseña de cobrador asignado\n";
        }

    } else {
        echo "✗ Manager no tiene cobradores asignados para la prueba\n";
    }

    // 7. Manager intenta cambiar contraseña de un usuario no asignado (debería fallar)
    echo "\n7. Manager intentando cambiar contraseña de usuario no asignado...\n";
    $unauthorizedChangeResponse = makeRequest('PATCH', $baseUrl . "/users/1/change-password", [
        'new_password' => 'hackIntent123',
        'new_password_confirmation' => 'hackIntent123'
    ], $managerToken);

    echo "Status Code: {$unauthorizedChangeResponse['status']}\n";
    echo "Response: " . json_encode($unauthorizedChangeResponse['body'], JSON_PRETTY_PRINT) . "\n";

    if ($unauthorizedChangeResponse['status'] === 403) {
        echo "✓ Correctamente denegado: Manager no puede cambiar contraseña de usuario no asignado\n";
    } else {
        echo "✗ Error de seguridad: Manager pudo cambiar contraseña no autorizada\n";
    }

} else {
    echo "✗ Error en login de manager\n";
}

// 8. Prueba de validación - contraseña muy corta
echo "\n8. Probando validación - contraseña muy corta...\n";
$shortPasswordResponse = makeRequest('PATCH', $baseUrl . "/users/{$cobrador['id']}/change-password", [
    'new_password' => '123',
    'new_password_confirmation' => '123'
], $adminToken);

echo "Status Code: {$shortPasswordResponse['status']}\n";
echo "Response: " . json_encode($shortPasswordResponse['body'], JSON_PRETTY_PRINT) . "\n";

if ($shortPasswordResponse['status'] === 422) {
    echo "✓ Validación correcta: Contraseña muy corta rechazada\n";
} else {
    echo "✗ Error de validación: Contraseña corta aceptada\n";
}

// 9. Prueba de validación - confirmación no coincide
echo "\n9. Probando validación - confirmación no coincide...\n";
$mismatchResponse = makeRequest('PATCH', $baseUrl . "/users/{$cobrador['id']}/change-password", [
    'new_password' => 'password123',
    'new_password_confirmation' => 'different123'
], $adminToken);

echo "Status Code: {$mismatchResponse['status']}\n";
echo "Response: " . json_encode($mismatchResponse['body'], JSON_PRETTY_PRINT) . "\n";

if ($mismatchResponse['status'] === 422) {
    echo "✓ Validación correcta: Confirmación no coincidente rechazada\n";
} else {
    echo "✗ Error de validación: Confirmación no coincidente aceptada\n";
}

echo "\n=== FIN DE PRUEBAS ===\n";
echo "\nResumen del endpoint:\n";
echo "- URL: PATCH /api/users/{user}/change-password\n";
echo "- Parámetros: new_password, new_password_confirmation\n";
echo "- Autorización: Admin (para managers/cobradores), Manager (para cobradores asignados)\n";
echo "- Validaciones: Mínimo 8 caracteres, confirmación requerida\n";
echo "- Seguridad: Log de auditoría, verificación de permisos granular\n";

?>
