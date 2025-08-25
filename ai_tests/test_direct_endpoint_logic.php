<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
