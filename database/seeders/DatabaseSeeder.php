<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ejecutar seeder de roles y permisos
        $this->call([
            RolePermissionSeeder::class,
        ]);

        // Crear usuario admin por defecto
        $admin = User::factory()->create([
            'name' => 'Administrador',
            'email' => 'admin@cobrador.com',
            'password' => bcrypt('password'),
        ]);

        // Asignar rol de admin
        $admin->assignRole('admin');

        // Crear usuarios de ejemplo
        User::factory(5)->create()->each(function ($user) {
            $user->assignRole('cobrador');
        });

        User::factory(10)->create()->each(function ($user) {
            $user->assignRole('client');
        });
    }
}
