<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear permisos
        $permissions = [
            // Usuarios
            'view users',
            'create users',
            'edit users',
            'delete users',
            
            // Rutas
            'view routes',
            'create routes',
            'edit routes',
            'delete routes',
            'assign clients to routes',
            
            // Créditos
            'view credits',
            'create credits',
            'edit credits',
            'delete credits',
            'approve credits',
            'reject credits',
            
            // Pagos
            'view payments',
            'create payments',
            'edit payments',
            'delete payments',
            'process payments',
            
            // Balances de efectivo
            'view cash balances',
            'create cash balances',
            'edit cash balances',
            'delete cash balances',
            
            // Notificaciones
            'view notifications',
            'create notifications',
            'edit notifications',
            'delete notifications',
            
            // Reportes
            'view reports',
            'generate reports',
            'export data',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Crear roles
        $roles = [
            'admin' => $permissions, // Admin tiene todos los permisos
            'manager' => [
                'view users',
                'create users',
                'edit users',
                'view routes',
                'create routes',
                'edit routes',
                'assign clients to routes',
                'view credits',
                'create credits',
                'edit credits',
                'approve credits',
                'reject credits',
                'view payments',
                'create payments',
                'edit payments',
                'process payments',
                'view cash balances',
                'create cash balances',
                'edit cash balances',
                'view notifications',
                'create notifications',
                'edit notifications',
                'view reports',
                'generate reports',
                'export data',
            ],
            'cobrador' => [
                'view routes',
                'view credits',
                'view payments',
                'create payments',
                'edit payments',
                'process payments',
                'view cash balances',
                'create cash balances',
                'edit cash balances',
                'view notifications',
                'view reports',
            ],
            'client' => [
                'view credits',
                'view payments',
                'view notifications',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::create(['name' => $roleName]);
            $role->givePermissionTo($rolePermissions);
        }
    }
} 