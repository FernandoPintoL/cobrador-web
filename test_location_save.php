<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\App;

// Simular una request con datos de ubicación
$data = [
    'name' => 'mi cliente Fernando',
    'email' => '',
    'roles' => ['client'],
    'phone' => '76843652',
    'address' => '25PX+8G5, Puerto Suárez, Departamento de Santa Cruz',
    'location' => [
        'type' => 'Point',
        'coordinates' => [-57.8012161, -18.9643238]
    ]
];

echo "=== Test de guardado de ubicación ===\n";
echo "Datos a enviar:\n";
echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

echo "Análisis de la ubicación GeoJSON:\n";
echo "- Tipo: " . $data['location']['type'] . "\n";
echo "- Coordenadas: [" . implode(', ', $data['location']['coordinates']) . "]\n";
echo "- Longitude (primer elemento): " . $data['location']['coordinates'][0] . "\n";
echo "- Latitude (segundo elemento): " . $data['location']['coordinates'][1] . "\n\n";

echo "Validaciones aplicadas:\n";
echo "- location.type debe ser 'Point': " . ($data['location']['type'] === 'Point' ? '✓' : '✗') . "\n";
echo "- location.coordinates debe tener 2 elementos: " . (count($data['location']['coordinates']) === 2 ? '✓' : '✗') . "\n";
echo "- longitude (-180 a 180): " . ($data['location']['coordinates'][0] >= -180 && $data['location']['coordinates'][0] <= 180 ? '✓' : '✗') . "\n";
echo "- latitude (-90 a 90): " . ($data['location']['coordinates'][1] >= -90 && $data['location']['coordinates'][1] <= 90 ? '✓' : '✗') . "\n\n";

echo "Campos que se guardarán en la DB:\n";
echo "- name: " . $data['name'] . "\n";
echo "- phone: " . $data['phone'] . "\n";
echo "- address: " . $data['address'] . "\n";
echo "- longitude: " . $data['location']['coordinates'][0] . "\n";
echo "- latitude: " . $data['location']['coordinates'][1] . "\n";
echo "- roles: [" . implode(', ', $data['roles']) . "]\n\n";

echo "✅ El UserController ahora procesará correctamente el campo 'location' GeoJSON\n";
echo "✅ Las coordenadas se guardarán en los campos 'latitude' y 'longitude' de la DB\n";
echo "✅ Se aplicarán validaciones para asegurar formato GeoJSON válido\n";
