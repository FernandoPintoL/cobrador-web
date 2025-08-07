<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;

class TestManagerRoute extends Command
{
    protected $signature = 'test:manager-route {id}';
    protected $description = 'Test the manager clients route';

    public function handle()
    {
        $managerId = $this->argument('id');
        $manager = User::find($managerId);
        
        if (!$manager) {
            $this->error("Manager con ID {$managerId} no encontrado");
            return;
        }
        
        $this->info("Probando ruta para manager: {$manager->name}");
        
        if (!$manager->hasRole('manager')) {
            $this->error("El usuario no es manager");
            return;
        }
        
        try {
            $controller = new UserController();
            $request = new Request();
            
            // Simular la llamada al método
            $response = $controller->getAllClientsByManager($request, $manager);
            
            $this->info("✅ Ruta funciona correctamente");
            $this->info("Respuesta: " . json_encode($response->getData(), JSON_PRETTY_PRINT));
            
        } catch (\Exception $e) {
            $this->error("❌ Error en la ruta: " . $e->getMessage());
            $this->error("Línea: " . $e->getLine());
            $this->error("Archivo: " . $e->getFile());
        }
    }
}
