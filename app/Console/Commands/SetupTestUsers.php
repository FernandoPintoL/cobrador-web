<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class SetupTestUsers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'setup:test-users';

    /**
     * The console command description.
     */
    protected $description = 'Create test users for the waiting list system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Configurando usuarios de prueba para el sistema de lista de espera...');

        // Verificar si los roles existen
        $this->ensureRolesExist();

        // Crear usuarios de prueba
        $users = $this->createTestUsers();

        // Asignar roles
        $this->assignRoles($users);

        $this->info('✅ Usuarios de prueba configurados exitosamente!');
        $this->displayUserCredentials($users);
    }

    private function ensureRolesExist()
    {
        $roles = ['admin', 'manager', 'cobrador', 'client'];
        
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
        
        $this->info('Roles verificados/creados: ' . implode(', ', $roles));
    }

    private function createTestUsers()
    {
        $users = [];

        // Admin
        $users['admin'] = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin Sistema',
                'password' => Hash::make('password123'),
                'phone' => '70000001',
                'address' => 'Oficina Central'
            ]
        );

        // Manager
        $users['manager'] = User::firstOrCreate(
            ['email' => 'manager@test.com'],
            [
                'name' => 'Manager López',
                'password' => Hash::make('password123'),
                'phone' => '70000002',
                'address' => 'Sucursal Principal'
            ]
        );

        // Cobrador
        $users['cobrador'] = User::firstOrCreate(
            ['email' => 'cobrador@test.com'],
            [
                'name' => 'Cobrador Pérez',
                'password' => Hash::make('password123'),
                'phone' => '70000003',
                'address' => 'Zona Norte'
            ]
        );

        // Clientes
        for ($i = 1; $i <= 5; $i++) {
            $users["cliente$i"] = User::firstOrCreate(
                ['email' => "cliente$i@test.com"],
                [
                    'name' => "Cliente $i",
                    'password' => Hash::make('password123'),
                    'phone' => "7000000" . (3 + $i),
                    'address' => "Dirección Cliente $i"
                ]
            );
        }

        return $users;
    }

    private function assignRoles($users)
    {
        // Asignar roles
        $users['admin']->assignRole('admin');
        $users['manager']->assignRole('manager');
        $users['cobrador']->assignRole('cobrador');

        for ($i = 1; $i <= 5; $i++) {
            $users["cliente$i"]->assignRole('client');
        }

        $this->info('Roles asignados correctamente');
    }

    private function displayUserCredentials($users)
    {
        $this->line('');
        $this->info('=== CREDENCIALES DE USUARIOS DE PRUEBA ===');
        $this->line('Email: admin@test.com | Password: password123 | Rol: Admin');
        $this->line('Email: manager@test.com | Password: password123 | Rol: Manager');
        $this->line('Email: cobrador@test.com | Password: password123 | Rol: Cobrador');
        $this->line('Email: cliente1@test.com | Password: password123 | Rol: Client');
        $this->line('Email: cliente2@test.com | Password: password123 | Rol: Client');
        $this->line('Email: cliente3@test.com | Password: password123 | Rol: Client');
        $this->line('Email: cliente4@test.com | Password: password123 | Rol: Client');
        $this->line('Email: cliente5@test.com | Password: password123 | Rol: Client');
        $this->line('');
    }
}
