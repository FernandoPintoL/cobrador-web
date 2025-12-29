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

        // Crear o actualizar usuario super_admin
        $this->command->info('👤 Creando o actualizando usuario Super Admin...');

        // Buscar por email o CI
        $user = \App\Models\User::where('email', 'super_admin@cobrador.com')
            ->orWhere('ci', 'ADMIN-001')
            ->first();

        if ($user) {
            // Actualizar usuario existente
            $user->update([
                'name' => 'SUPER ADMINISTRADOR',
                'email' => 'super_admin@cobrador.com',
                'password' => bcrypt('password'),
                'ci' => 'ADMIN-001',
                'tenant_id' => 1,
            ]);
        } else {
            // Crear nuevo usuario
            $user = \App\Models\User::create([
                'name' => 'SUPER ADMINISTRADOR',
                'email' => 'super_admin@cobrador.com',
                'password' => bcrypt('password'),
                'ci' => 'ADMIN-001',
                'tenant_id' => 1,
            ]);
        }

        // Asignar rol solo si no lo tiene
        if (!$user->hasRole('super_admin')) {
            $user->assignRole('super_admin');
        }

        $this->command->info('  ✓ Usuario super_admin@cobrador.com creado/actualizado');
        $this->command->warn('  ⚠️  Password: password (cámbialo en producción)');

        $this->command->newLine();
        $this->command->info('✅ Super Admin configurado exitosamente!');
    }
}
