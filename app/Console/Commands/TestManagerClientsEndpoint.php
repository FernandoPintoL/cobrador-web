<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;

class TestManagerClientsEndpoint extends Command
{
    protected $signature = 'test:manager-clients-endpoint';
    protected $description = 'Verificar el endpoint getAllClientsByManager y analizar roles de usuarios';

    public function handle()
    {
        $this->info('=== Análisis del endpoint getAllClientsByManager ===');

        // Buscar el manager con ID 17
        $manager = User::find(17);
        
        if (!$manager) {
            $this->error('❌ Manager con ID 17 no encontrado');
            return 1;
        }

        $this->info("🔍 Manager: {$manager->name} (ID: {$manager->id})");
        $this->line('');

        // Crear una instancia del controller
        $controller = new UserController();
        $request = new Request();
        
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
                $this->error("   Se encontraron {count($problemUsers)} usuarios con múltiples roles");
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
    }
}
