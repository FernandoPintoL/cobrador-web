<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\Controllers\Api\ReportController;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

// Crear aplicación Laravel
$app    = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "=== PRUEBA DE REPORTES CON SOPORTE PARA EXCEL ===\n\n";

// Crear instancia del controlador
$controller = new ReportController();

// Probar tipos de reportes
echo "1. Probando tipos de reportes disponibles...\n";
$request  = new Request();
$response = $controller->getReportTypes();
$data     = json_decode($response->getContent(), true);

if ($data['success']) {
    echo "✅ Tipos de reportes obtenidos correctamente\n";
    foreach ($data['data'] as $key => $report) {
        echo "   - {$report['name']}: " . implode(', ', $report['formats']) . " formatos\n";
    }
} else {
    echo "❌ Error al obtener tipos de reportes\n";
}

echo "\n2. Probando exportación Excel de pagos...\n";
// Simular request con formato Excel
$request = new Request([
    'format'     => 'excel',
    'start_date' => '2024-01-01',
    'end_date'   => '2024-12-31',
]);

try {
    $response = $controller->paymentsReport($request);
    echo "✅ Reporte de pagos con Excel generado correctamente\n";
} catch (Exception $e) {
    echo "❌ Error en reporte de pagos: " . $e->getMessage() . "\n";
}

echo "\n3. Probando exportación Excel de créditos...\n";
$request = new Request([
    'format' => 'excel',
    'status' => 'active',
]);

try {
    $response = $controller->creditsReport($request);
    echo "✅ Reporte de créditos con Excel generado correctamente\n";
} catch (Exception $e) {
    echo "❌ Error en reporte de créditos: " . $e->getMessage() . "\n";
}

echo "\n4. Probando exportación Excel de usuarios...\n";
$request = new Request([
    'format' => 'excel',
]);

try {
    $response = $controller->usersReport($request);
    echo "✅ Reporte de usuarios con Excel generado correctamente\n";
} catch (Exception $e) {
    echo "❌ Error en reporte de usuarios: " . $e->getMessage() . "\n";
}

echo "\n5. Probando exportación Excel de balances...\n";
$request = new Request([
    'format' => 'excel',
]);

try {
    $response = $controller->balancesReport($request);
    echo "✅ Reporte de balances con Excel generado correctamente\n";
} catch (Exception $e) {
    echo "❌ Error en reporte de balances: " . $e->getMessage() . "\n";
}

echo "\n=== PRUEBA COMPLETADA ===\n";
echo "Si no hay errores arriba, el soporte para Excel está funcionando correctamente.\n";
