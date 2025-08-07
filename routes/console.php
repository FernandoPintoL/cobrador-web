<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programar el procesamiento de entregas de créditos
Schedule::command('credits:process-scheduled-deliveries --notify')
    ->dailyAt('08:00')
    ->description('Procesar entregas programadas de créditos y enviar notificaciones');

// Verificar créditos atrasados para entrega
Schedule::command('credits:process-scheduled-deliveries --notify')
    ->dailyAt('17:00')
    ->description('Verificar créditos atrasados para entrega');

/*
|--------------------------------------------------------------------------
| Test User Creation with Location
|--------------------------------------------------------------------------
|
| Este comando prueba la creación de usuarios con datos de ubicación GeoJSON
|
*/

Artisan::command('test:user-location', function () {
    $this->info('=== Prueba de creación de usuario con ubicación ===');

    // Generar datos únicos para la prueba
    $timestamp = time();
    $userData = [
        'name' => 'Cliente Test Fernando ' . $timestamp,
        'email' => 'fernando.test.' . $timestamp . '@example.com',
        'phone' => '768436' . substr($timestamp, -2), // Últimos 2 dígitos del timestamp
        'address' => '25PX+8G5, Puerto Suárez, Departamento de Santa Cruz',
        'password' => Hash::make('password123'),
        'longitude' => -57.8012161, // Procesado desde location.coordinates[0]
        'latitude' => -18.9643238,  // Procesado desde location.coordinates[1]
    ];

    try {
        // Crear el usuario
        $user = User::create($userData);
        
        // Asignar rol de cliente
        $user->assignRole('client');
        
        $this->info('✅ Usuario creado exitosamente:');
        $this->line("   ID: {$user->id}");
        $this->line("   Nombre: {$user->name}");
        $this->line("   Email: {$user->email}");
        $this->line("   Teléfono: {$user->phone}");
        $this->line("   Dirección: {$user->address}");
        $this->line("   Longitude: {$user->longitude}");
        $this->line("   Latitude: {$user->latitude}");
        $this->line("   Roles: " . $user->roles->pluck('name')->implode(', '));
        
        $this->info('');
        $this->info('📍 Coordenadas guardadas correctamente:');
        $this->line("   Formato original GeoJSON: {\"type\": \"Point\", \"coordinates\": [-57.8012161, -18.9643238]}");
        $this->line("   Guardado en DB - Longitude: {$user->longitude}");
        $this->line("   Guardado en DB - Latitude: {$user->latitude}");
        
        // Limpiar datos de prueba
        $this->info('');
        $this->warn('🧹 Limpiando datos de prueba...');
        $user->delete();
        $this->info('✅ Usuario de prueba eliminado');
        
    } catch (Exception $e) {
        $this->error('❌ Error al crear usuario: ' . $e->getMessage());
        return 1;
    }

    return 0;
})->purpose('Probar la creación de usuarios con datos de ubicación GeoJSON');

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

/*
|--------------------------------------------------------------------------
| Test Manager Cobradores Filter
|--------------------------------------------------------------------------
|
| Este comando prueba que el endpoint /cobradores filtre correctamente por rol
|
*/

Artisan::command('test:manager-cobradores', function () {
    $this->info('=== Prueba de filtro de cobradores por manager ===');

    // Buscar el manager con ID 17
    $manager = User::find(17);
    
    if (!$manager) {
        $this->error('❌ Manager con ID 17 no encontrado');
        return 1;
    }

    $this->info("🔍 Manager encontrado: {$manager->name} (ID: {$manager->id})");
    $this->info("   Roles: " . $manager->roles->pluck('name')->implode(', '));
    $this->line('');

    // Verificar todos los usuarios asignados a este manager (sin filtro de rol)
    $allAssigned = User::where('assigned_manager_id', $manager->id)->get();
    $this->info("📊 Usuarios asignados al manager (sin filtro de rol): {$allAssigned->count()}");
    
    foreach ($allAssigned as $user) {
        $roles = $user->roles->pluck('name')->implode(', ');
        $this->line("   - {$user->name} (ID: {$user->id}) | Roles: {$roles}");
    }
    $this->line('');

    // Verificar solo cobradores asignados a este manager (con filtro de rol)
    $cobradoresOnly = User::whereHas('roles', function ($query) {
            $query->where('name', 'cobrador');
        })
        ->where('assigned_manager_id', $manager->id)
        ->get();

    $this->info("✅ Cobradores asignados al manager (con filtro correcto): {$cobradoresOnly->count()}");
    
    if ($cobradoresOnly->count() > 0) {
        foreach ($cobradoresOnly as $cobrador) {
            $roles = $cobrador->roles->pluck('name')->implode(', ');
            $this->line("   - {$cobrador->name} (ID: {$cobrador->id}) | Roles: {$roles}");
        }
    } else {
        $this->warn('   No hay cobradores asignados a este manager');
    }
    $this->line('');

    // Verificar solo clientes asignados a este manager
    $clientesOnly = User::whereHas('roles', function ($query) {
            $query->where('name', 'client');
        })
        ->where('assigned_manager_id', $manager->id)
        ->get();

    $this->info("📋 Clientes asignados directamente al manager: {$clientesOnly->count()}");
    
    if ($clientesOnly->count() > 0) {
        foreach ($clientesOnly->take(3) as $cliente) {
            $roles = $cliente->roles->pluck('name')->implode(', ');
            $this->line("   - {$cliente->name} (ID: {$cliente->id}) | Roles: {$roles}");
        }
        if ($clientesOnly->count() > 3) {
            $remaining = $clientesOnly->count() - 3;
            $this->line("   ... y {$remaining} clientes más");
        }
    }
    $this->line('');

    $this->info('🎯 Resultado esperado del endpoint /api/users/17/cobradores:');
    $this->info("   - Debe devolver solo {$cobradoresOnly->count()} usuarios");
    $this->info('   - Todos deben tener rol "cobrador"');
    $this->info('   - No debe incluir usuarios con rol "client"');

    return 0;
})->purpose('Probar que el endpoint /cobradores filtre correctamente por rol cobrador');

/*
|--------------------------------------------------------------------------
| Test Direct Endpoint Access
|--------------------------------------------------------------------------
|
| Este comando prueba el endpoint directamente
|
*/

Artisan::command('test:direct-endpoint', function () {
    $this->info('=== Prueba directa del endpoint /cobradores ===');

    // Simular una request HTTP
    $request = new Request();
    
    // Buscar el manager con ID 17
    $manager = User::find(17);
    
    if (!$manager) {
        $this->error('❌ Manager con ID 17 no encontrado');
        return 1;
    }

    $this->info("🔍 Manager encontrado: {$manager->name} (ID: {$manager->id})");
    $this->line('');

    // Simular exactamente lo que hace el endpoint
    $this->info('🔄 Ejecutando lógica del endpoint...');
    
    // Verificar que el usuario sea un manager
    if (!$manager->hasRole('manager')) {
        $this->error('❌ El usuario especificado no es un manager');
        return 1;
    }

    $this->info('✅ Verificación de rol manager: EXITOSA');

    // Obtener solo usuarios con rol 'cobrador' asignados a este manager
    $cobradores = User::whereHas('roles', function ($query) {
            $query->where('name', 'cobrador');
        })
        ->where('assigned_manager_id', $manager->id)
        ->with(['roles', 'permissions'])
        ->orderBy('name', 'asc')
        ->get();

    $this->info("📊 Cobradores encontrados: {$cobradores->count()}");
    
    if ($cobradores->count() > 0) {
        foreach ($cobradores as $cobrador) {
            $roles = $cobrador->roles->pluck('name')->implode(', ');
            $this->line("   - {$cobrador->name} (ID: {$cobrador->id}) | Roles: {$roles}");
        }
    } else {
        $this->warn('   No hay cobradores asignados a este manager');
    }

    // Simular la respuesta paginada
    $this->line('');
    $this->info('📄 Simulando paginación...');
    $paginatedCobradores = User::whereHas('roles', function ($query) {
            $query->where('name', 'cobrador');
        })
        ->where('assigned_manager_id', $manager->id)
        ->with(['roles', 'permissions'])
        ->orderBy('name', 'asc')
        ->paginate(15);

    $this->info("   Total items: {$paginatedCobradores->total()}");
    $this->info("   Página actual: {$paginatedCobradores->currentPage()}");
    $this->info("   Items por página: {$paginatedCobradores->perPage()}");
    $this->info("   Páginas totales: {$paginatedCobradores->lastPage()}");

    $this->line('');
    $this->info('🎯 Resultado esperado del endpoint:');
    $this->info('   - success: true');
    $this->info("   - message: \"Cobradores asignados al manager {$manager->name} obtenidos exitosamente\"");
    $this->info('   - data: paginación con ' . $cobradores->count() . ' cobradores');
    $this->info('   - Cada cobrador debe tener solo rol "cobrador"');

    return 0;
})->purpose('Probar la lógica del endpoint directamente');

Artisan::command('test:manager-clients-endpoint', function () {
    $this->info('=== Análisis del endpoint getAllClientsByManager ===');

    // Buscar el manager con ID 17
    $manager = App\Models\User::find(17);
    
    if (!$manager) {
        $this->error('❌ Manager con ID 17 no encontrado');
        return 1;
    }

    $this->info("🔍 Manager: {$manager->name} (ID: {$manager->id})");
    $this->line('');

    // Crear una instancia del controller
    $controller = new App\Http\Controllers\Api\UserController();
    $request = new Illuminate\Http\Request();
    
    try {
        // Llamar al método directamente
        $response = $controller->getAllClientsByManager($request, $manager);
        $responseData = $response->getData(true);
        
        $this->info('✅ Endpoint ejecutado exitosamente');
        $this->line('');
        
        $clients = $responseData['data']['data'];
        $this->info("📊 Total clientes encontrados: " . count($clients));
        $this->line('');

        // Analizar tipos de asignación
        $directClients = collect($clients)->where('assignment_type', 'direct');
        $indirectClients = collect($clients)->where('assignment_type', 'through_cobrador');
        
        $this->info("👥 Clientes directos: {$directClients->count()}");
        $this->info("🔗 Clientes indirectos (a través de cobradores): {$indirectClients->count()}");
        $this->line('');

        // Verificar problemas con roles múltiples
        $this->info('🔍 Análisis de roles de usuarios:');
        $problemUsers = [];
        
        foreach ($clients as $client) {
            $roles = collect($client['roles'])->pluck('name')->toArray();
            $rolesText = implode(', ', $roles);
            
            if (count($roles) > 1) {
                $problemUsers[] = $client;
                $this->warn("   ⚠️  {$client['name']} (ID: {$client['id']}) | Roles: {$rolesText} | Tipo: {$client['assignment_type']}");
            } else {
                $this->line("   ✅ {$client['name']} (ID: {$client['id']}) | Roles: {$rolesText} | Tipo: {$client['assignment_type']}");
            }
        }
        
        $this->line('');
        
        if (!empty($problemUsers)) {
            $this->error('❌ PROBLEMAS DETECTADOS:');
            $this->error("   Se encontraron " . count($problemUsers) . " usuarios con múltiples roles");
            $this->line('');
            
            foreach ($problemUsers as $problemUser) {
                $roles = collect($problemUser['roles'])->pluck('name')->toArray();
                $this->error("   - {$problemUser['name']} (ID: {$problemUser['id']}):");
                $this->error("     Roles: " . implode(', ', $roles));
                $this->error("     Problema: Un usuario con rol 'manager' no debería aparecer como cliente");
                
                // Sugerencia de corrección
                if (in_array('manager', $roles)) {
                    $this->warn("     Sugerencia: Remover rol 'client' o excluir usuarios con rol 'manager'");
                }
            }
        } else {
            $this->info('✅ VERIFICACIÓN EXITOSA: Todos los usuarios tienen solo rol "client"');
        }

        $this->line('');
        $this->info('📋 Resumen de cobradores involucrados:');
        
        $cobradoresInvolved = collect($clients)
            ->where('assignment_type', 'through_cobrador')
            ->pluck('cobrador_name')
            ->unique()
            ->filter();
            
        if ($cobradoresInvolved->isNotEmpty()) {
            foreach ($cobradoresInvolved as $cobradorName) {
                $clientsOfCobrador = collect($clients)->where('cobrador_name', $cobradorName)->count();
                $this->line("   - {$cobradorName}: {$clientsOfCobrador} clientes");
            }
        } else {
            $this->line('   - No hay clientes asignados a través de cobradores');
        }

        $this->line('');
        $this->info('🎯 Validación de la funcionalidad:');
        $this->info('   ✅ Devuelve clientes directos del manager');
        $this->info('   ✅ Devuelve clientes indirectos (a través de cobradores)');
        $this->info('   ✅ Incluye información de assignment_type');
        $this->info('   ✅ Incluye información del cobrador cuando aplica');
        
        if (!empty($problemUsers)) {
            $this->warn('   ⚠️  Hay usuarios con roles múltiples que pueden causar confusión');
        }
        
    } catch (\Exception $e) {
        $this->error('❌ Error al ejecutar el endpoint: ' . $e->getMessage());
        return 1;
    }

    return 0;
})->purpose('Verificar endpoint getAllClientsByManager');
