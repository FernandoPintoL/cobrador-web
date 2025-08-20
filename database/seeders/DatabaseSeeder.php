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
            'ci' => '123456789',
            'password' => bcrypt('password'),
        ]);

        // Asignar rol de admin
        $admin->assignRole('admin');

        // Crear una tasa de interés por defecto
        \App\Models\InterestRate::create([
            'name' => 'Tasa por defecto',
            'rate' => 20.00,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        // Crear usuarios de ejemplo con CI únicos
        User::factory(5)->sequence(fn ($sequence) => ['ci' => '10000000' . $sequence->index])->create()->each(function ($user) {
            $user->assignRole('cobrador');
        });

        User::factory(10)->sequence(fn ($sequence) => ['ci' => '20000000' . $sequence->index])->create()->each(function ($user) {
            $user->assignRole('client');
        });
    }
}
