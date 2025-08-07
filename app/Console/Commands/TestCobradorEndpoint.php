<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;

class TestCobradorEndpoint extends Command
{
    protected $signature = 'test:cobrador-endpoint';
    protected $description = 'Probar el endpoint de cobradores usando el UserController';

    public function handle()
    {
        $this->info('=== Prueba del UserController->getCobradoresByManager() ===');

        // Buscar el manager con ID 17
        $manager = User::find(17);
        
        if (!$manager) {
            $this->error('❌ Manager con ID 17 no encontrado');
            return 1;
        }

        $this->info("🔍 Manager encontrado: {$manager->name} (ID: {$manager->id})");
        $this->line('');

        // Crear una instancia del controller
        $controller = new UserController();
        
        // Crear una request vacía
        $request = new Request();
        
        try {
            // Llamar al método directamente
            $response = $controller->getCobradoresByManager($request, $manager);
            
            // Obtener el contenido de la respuesta
            $responseData = $response->getData(true);
            
            $this->info('✅ Endpoint ejecutado exitosamente');
            $this->line('');
            
            $this->info('📊 Respuesta del endpoint:');
            $this->line("   success: " . ($responseData['success'] ? 'true' : 'false'));
            $this->line("   message: " . $responseData['message']);
            
            if (isset($responseData['data']['data'])) {
                $items = $responseData['data']['data'];
                $this->line("   total_items: " . count($items));
                $this->line("   total_pages: " . $responseData['data']['last_page']);
                $this->line("   current_page: " . $responseData['data']['current_page']);
                
                $this->line('');
                $this->info('👥 Usuarios devueltos:');
                
                foreach ($items as $item) {
                    $roles = collect($item['roles'])->pluck('name')->implode(', ');
                    $this->line("   - {$item['name']} (ID: {$item['id']}) | Roles: {$roles}");
                }
                
                // Verificar que todos sean cobradores
                $allCobradores = collect($items)->every(function ($item) {
                    return collect($item['roles'])->contains('name', 'cobrador');
                });
                
                $this->line('');
                if ($allCobradores) {
                    $this->info('✅ CORRECCIÓN EXITOSA: Todos los usuarios tienen rol "cobrador"');
                } else {
                    $this->error('❌ PROBLEMA DETECTADO: Hay usuarios sin rol "cobrador"');
                }
                
            } else {
                $this->warn('⚠️  No se encontraron datos en la respuesta');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error al ejecutar el endpoint: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
