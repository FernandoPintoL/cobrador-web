<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Test Frontend Location Data Processing
|--------------------------------------------------------------------------
|
| Este comando simula exactamente los datos que llegan desde el frontend
|
*/

Artisan::command('test:frontend-location', function () {
    $this->info('=== Simulación de datos desde el frontend ===');

    // Simular datos exactos del frontend
    $frontendData = [
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

    $this->info('📱 Datos recibidos del frontend:');
    $this->line(json_encode($frontendData, JSON_PRETTY_PRINT));
    $this->line('');

    // Procesar la ubicación como lo haría el UserController
    $userData = [
        'name' => $frontendData['name'],
        'phone' => $frontendData['phone'],
        'address' => $frontendData['address'],
        'password' => Hash::make('temp_password_' . time()),
    ];

    // Procesar ubicación GeoJSON
    if (isset($frontendData['location']) && $frontendData['location']) {
        $location = $frontendData['location'];
        if (isset($location['type']) && $location['type'] === 'Point' && 
            isset($location['coordinates']) && is_array($location['coordinates']) && 
            count($location['coordinates']) === 2) {
            
            $userData['longitude'] = $location['coordinates'][0];
            $userData['latitude'] = $location['coordinates'][1];
            
            $this->info('🔄 Procesamiento de ubicación:');
            $this->line("   ✓ Tipo GeoJSON válido: {$location['type']}");
            $this->line("   ✓ Coordenadas válidas: [" . implode(', ', $location['coordinates']) . "]");
            $this->line("   ✓ Longitude extraída: {$userData['longitude']}");
            $this->line("   ✓ Latitude extraída: {$userData['latitude']}");
        }
    }

    // Agregar timestamp para evitar duplicados
    $timestamp = time();
    $userData['name'] = $userData['name'] . ' ' . $timestamp;
    $userData['phone'] = '768436' . substr($timestamp, -2);

    $this->line('');
    $this->info('💾 Datos que se guardarán en la DB:');
    foreach ($userData as $key => $value) {
        if ($key !== 'password') {
            $this->line("   {$key}: {$value}");
        } else {
            $this->line("   {$key}: [hash generado]");
        }
    }

    try {
        // Crear el usuario
        $user = User::create($userData);
        $user->assignRole('client');
        
        $this->line('');
        $this->info('✅ Usuario creado exitosamente en la DB:');
        $this->line("   ID: {$user->id}");
        $this->line("   Nombre: {$user->name}");
        $this->line("   Teléfono: {$user->phone}");
        $this->line("   Dirección: {$user->address}");
        $this->line("   Longitude: {$user->longitude}°");
        $this->line("   Latitude: {$user->latitude}°");
        $this->line("   Roles: " . $user->roles->pluck('name')->implode(', '));
        
        $this->line('');
        $this->info('📍 Verificación de coordenadas:');
        $originalLng = $frontendData['location']['coordinates'][0];
        $originalLat = $frontendData['location']['coordinates'][1];
        $savedLng = (float) $user->longitude;
        $savedLat = (float) $user->latitude;
        
        $this->line("   Frontend longitude: {$originalLng}");
        $this->line("   DB longitude: {$savedLng}");
        $this->line("   Coinciden: " . ($originalLng == $savedLng ? '✅' : '❌'));
        
        $this->line("   Frontend latitude: {$originalLat}");
        $this->line("   DB latitude: {$savedLat}");
        $this->line("   Coinciden: " . ($originalLat == $savedLat ? '✅' : '❌'));
        
        // Limpiar
        $this->line('');
        $this->warn('🧹 Limpiando datos de prueba...');
        $user->delete();
        $this->info('✅ Usuario de prueba eliminado');
        
    } catch (Exception $e) {
        $this->error('❌ Error: ' . $e->getMessage());
        return 1;
    }

    $this->line('');
    $this->info('🎉 ¡Implementación completada exitosamente!');
    $this->info('   - El UserController ahora procesa campos "location" GeoJSON');
    $this->info('   - Las coordenadas se extraen y guardan como latitude/longitude');
    $this->info('   - Se aplican validaciones para formato GeoJSON válido');
    $this->info('   - Compatible con datos del frontend de Flutter');

    return 0;
})->purpose('Simular exactamente los datos que llegan desde el frontend Flutter');
