<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SuperAdminRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔐 Creando rol y permisos de Super Admin...');

        // Crear permisos específicos para gestión multi-tenant
        $permissions = [
            // Gestión de Tenants (Empresas)
            'manage-tenants' => 'Gestionar empresas (crear, editar, eliminar)',
            'view-tenants' => 'Ver lista de empresas',
            'create-tenants' => 'Crear nuevas empresas',
            'edit-tenants' => 'Editar empresas existentes',
            'delete-tenants' => 'Eliminar/suspender empresas',
            'activate-tenants' => 'Activar empresas suspendidas',

            // Gestión de Suscripciones/Facturas
            'manage-subscriptions' => 'Gestionar suscripciones y facturas',
            'view-subscriptions' => 'Ver facturas de todas las empresas',
            'mark-subscriptions-paid' => 'Marcar facturas como pagadas',

            // Dashboard de Facturación
            'view-billing-dashboard' => 'Ver dashboard de facturación global',

            // Configuraciones por Tenant
            'manage-tenant-settings' => 'Configurar settings de cada empresa',
            'view-tenant-settings' => 'Ver configuraciones de empresas',
            'edit-tenant-settings' => 'Editar configuraciones de empresas',
        ];

        foreach ($permissions as $name => $description) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['guard_name' => 'web']
            );

            $this->command->line("  ✓ Permiso creado: {$name}");
        }

        $this->command->newLine();
        $this->command->info('👑 Creando rol Super Admin...');

        // Crear rol super_admin
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['guard_name' => 'web']
        );

        // Asignar todos los permisos multi-tenant al super_admin
        $superAdmin->syncPermissions(array_keys($permissions));

        $this->command->info("  ✓ Rol 'super_admin' creado con {$superAdmin->permissions->count()} permisos");
        $this->command->newLine();

        // Crear un usuario super_admin de ejemplo (opcional)
        $this->command->info('👤 ¿Quieres crear un usuario Super Admin de ejemplo?');

        if ($this->command->confirm('Crear usuario super_admin@example.com', true)) {
            $user = \App\Models\User::firstOrCreate(
                ['email' => 'super_admin@example.com'],
                [
                    'name' => 'SUPER ADMINISTRADOR',
                    'password' => bcrypt('password'),
                    'ci' => 'ADMIN-001',
                    'tenant_id' => 1, // Asignar a Empresa Inicial
                ]
            );

            $user->assignRole('super_admin');

            $this->command->info('  ✓ Usuario super_admin@example.com creado');
            $this->command->warn('  ⚠️  Password: password (cámbialo en producción)');
        }

        $this->command->newLine();
        $this->command->info('✅ Super Admin configurado exitosamente!');
    }
}
