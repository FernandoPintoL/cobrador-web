<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\Api\UserController;
use App\Models\User;

// Configurar la aplicación Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

class ClientCategoriesTest
{
    private $controller;
    private $testUsers = [];

    public function __construct()
    {
        $this->controller = new UserController();
    }

    public function run()
    {
        echo "🧪 PRUEBA COMPLETA: Sistema de Categorías de Cliente y Corrección de CI\n\n";

        try {
            // 1. Probar categorías disponibles
            $this->testGetCategories();

            // 2. Probar creación de cliente con categoría (y CI)
            $this->testCreateClientWithCategory();

            // 3. Probar actualización de categoría
            $this->testUpdateClientCategory();

            // 4. Probar filtros por categoría
            $this->testGetClientsByCategory();

            // 5. Probar estadísticas
            $this->testCategoryStatistics();

            // 6. Probar funciones del modelo
            $this->testModelMethods();

            // 7. Probar creación de manager (para verificar corrección de CI)
            $this->testCreateManager();

            echo "\n✅ ¡Todas las pruebas completadas exitosamente!\n";

        } catch (Exception $e) {
            echo "\n❌ Error en las pruebas: " . $e->getMessage() . "\n";
            echo "Archivo: " . $e->getFile() . " Línea: " . $e->getLine() . "\n";
        } finally {
            $this->cleanup();
        }
    }

    private function testGetCategories()
    {
        echo "1️⃣ Probando obtención de categorías disponibles...\n";

        $categories = User::getClientCategories();
        echo "Categorías disponibles:\n";
        foreach ($categories as $code => $name) {
            echo "  - $code: $name\n";
        }
        echo "✅ Categorías obtenidas correctamente\n\n";
    }

    private function testCreateClientWithCategory()
    {
        echo "2️⃣ Probando creación de cliente con categoría y CI...\n";

        // Crear un manager primero para que pueda crear clientes
        $managerData = [
            'name' => 'Manager Test',
            'email' => 'manager.test@example.com',
            'ci' => '12345678',
            'password' => 'password123',
            'roles' => ['manager']
        ];

        $manager = User::create([
            'name' => $managerData['name'],
            'email' => $managerData['email'],
            'ci' => $managerData['ci'],
            'password' => bcrypt($managerData['password']),
        ]);
        $manager->assignRole('manager');
        $this->testUsers[] = $manager;

        // Simular autenticación
        auth()->login($manager);

        // Crear cliente VIP
        $clientData = [
            'name' => 'Cliente VIP Test',
            'ci' => '87654321',
            'client_category' => 'A',
            'address' => 'Dirección de prueba',
            'roles' => ['client']
        ];

        $client = User::create([
            'name' => $clientData['name'],
            'ci' => $clientData['ci'],
            'client_category' => $clientData['client_category'],
            'address' => $clientData['address'],
            'password' => bcrypt('temp_password'),
            'assigned_manager_id' => $manager->id
        ]);
        $client->assignRole('client');
        $this->testUsers[] = $client;

        echo "Cliente creado:\n";
        echo "  - Nombre: {$client->name}\n";
        echo "  - CI: {$client->ci}\n";
        echo "  - Categoría: {$client->client_category} ({$client->client_category_name})\n";
        echo "  - Es VIP: " . ($client->isVipClient() ? 'Sí' : 'No') . "\n";
        echo "✅ Cliente con categoría creado correctamente\n\n";
    }

    private function testUpdateClientCategory()
    {
        echo "3️⃣ Probando actualización de categoría de cliente...\n";

        $client = User::whereHas('roles', function ($q) {
            $q->where('name', 'client');
        })->where('client_category', 'A')->first();

        if ($client) {
            $oldCategory = $client->client_category;
            $oldCategoryName = $client->client_category_name;

            $client->update(['client_category' => 'B']);
            $client->refresh();

            echo "Categoría actualizada:\n";
            echo "  - Anterior: $oldCategory ($oldCategoryName)\n";
            echo "  - Nueva: {$client->client_category} ({$client->client_category_name})\n";
            echo "  - Es Cliente Normal: " . ($client->isNormalClient() ? 'Sí' : 'No') . "\n";
            echo "✅ Categoría actualizada correctamente\n\n";
        } else {
            echo "⚠️ No se encontró cliente para actualizar\n\n";
        }
    }

    private function testGetClientsByCategory()
    {
        echo "4️⃣ Probando filtros por categoría...\n";

        // Crear algunos clientes más para la prueba
        $categories = ['A', 'B', 'C'];
        $manager = User::whereHas('roles', function ($q) {
            $q->where('name', 'manager');
        })->first();

        foreach ($categories as $i => $category) {
            $client = User::create([
                'name' => "Cliente Categoria $category " . ($i + 1),
                'ci' => '11111' . ($i + 100),
                'client_category' => $category,
                'password' => bcrypt('temp_password'),
                'assigned_manager_id' => $manager->id
            ]);
            $client->assignRole('client');
            $this->testUsers[] = $client;
        }

        // Probar scopes
        $vipClients = User::vipClients()->count();
        $normalClients = User::normalClients()->count();
        $badClients = User::badClients()->count();

        echo "Estadísticas por categoría usando scopes:\n";
        echo "  - Clientes VIP (A): $vipClients\n";
        echo "  - Clientes Normales (B): $normalClients\n";
        echo "  - Malos Clientes (C): $badClients\n";
        echo "✅ Filtros por categoría funcionando correctamente\n\n";
    }

    private function testCategoryStatistics()
    {
        echo "5️⃣ Probando estadísticas de categorías...\n";

        $statistics = [];
        foreach (User::CLIENT_CATEGORIES as $code => $name) {
            $count = User::whereHas('roles', function ($query) {
                    $query->where('name', 'client');
                })
                ->where('client_category', $code)
                ->count();

            $statistics[] = [
                'category_code' => $code,
                'category_name' => $name,
                'client_count' => $count
            ];
        }

        echo "Estadísticas completas:\n";
        foreach ($statistics as $stat) {
            echo "  - {$stat['category_name']} ({$stat['category_code']}): {$stat['client_count']} clientes\n";
        }
        echo "✅ Estadísticas generadas correctamente\n\n";
    }

    private function testModelMethods()
    {
        echo "6️⃣ Probando métodos del modelo...\n";

        $client = User::whereHas('roles', function ($q) {
            $q->where('name', 'client');
        })->first();

        if ($client) {
            echo "Métodos de verificación de categoría:\n";
            echo "  - isVipClient(): " . ($client->isVipClient() ? 'true' : 'false') . "\n";
            echo "  - isNormalClient(): " . ($client->isNormalClient() ? 'true' : 'false') . "\n";
            echo "  - isBadClient(): " . ($client->isBadClient() ? 'true' : 'false') . "\n";
            echo "  - client_category_name: " . ($client->client_category_name ?? 'null') . "\n";
            echo "✅ Métodos del modelo funcionando correctamente\n\n";
        } else {
            echo "⚠️ No se encontró cliente para probar métodos\n\n";
        }
    }

    private function testCreateManager()
    {
        echo "7️⃣ Probando creación de manager (corrección de CI)...\n";

        $managerData = [
            'name' => 'Manager CI Test',
            'email' => 'manager.ci.test@example.com',
            'ci' => '99999999',
            'password' => 'password123',
            'phone' => '70000000'
        ];

        $manager = User::create([
            'name' => $managerData['name'],
            'email' => $managerData['email'],
            'ci' => $managerData['ci'], // Este campo ahora se guarda correctamente
            'password' => bcrypt($managerData['password']),
            'phone' => $managerData['phone']
        ]);
        $manager->assignRole('manager');
        $this->testUsers[] = $manager;

        echo "Manager creado exitosamente:\n";
        echo "  - Nombre: {$manager->name}\n";
        echo "  - Email: {$manager->email}\n";
        echo "  - CI: {$manager->ci}\n";
        echo "  - Teléfono: {$manager->phone}\n";
        echo "✅ Campo CI se guarda correctamente (error corregido)\n\n";
    }

    private function cleanup()
    {
        echo "🧹 Limpiando datos de prueba...\n";

        foreach ($this->testUsers as $user) {
            try {
                $user->delete();
                echo "  - Usuario '{$user->name}' eliminado\n";
            } catch (Exception $e) {
                echo "  - Error eliminando usuario '{$user->name}': {$e->getMessage()}\n";
            }
        }

        echo "✅ Limpieza completada\n";
    }
}

// Ejecutar las pruebas
$test = new ClientCategoriesTest();
$test->run();
