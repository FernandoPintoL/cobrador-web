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

        // Crear usuario manager por defecto
        $manager = User::factory()->create([
            'name' => 'Manager',
            'email' => 'app@manager.com',
            'ci' => '1234567899',
            'password' => bcrypt('password'),
        ]);

        // Asignar rol de manager
        $manager->assignRole('manager');

        // user cobrador
        $cobrador = User::factory()->create([
            'name' => 'App Cobrador',
            'email' => 'app@cobrador.com',
            'ci' => '12345678999',
            'assigned_manager_id' => $manager->id,
            'password' => bcrypt('password'),
        ]);

        $cobrador->assignRole('cobrador');

        // Crear una tasa de interés por defecto
        \App\Models\InterestRate::create([
            'name' => 'Tasa por defecto',
            'rate' => 20.00,
            'is_active' => true,
            'created_by' => $manager->id,
        ]);

        // Ejecutar seeder de créditos y pagos
        $this->call([
            CreditsAndPaymentsSeeder::class,
        ]);

        // Crear usuarios de ejemplo con CI únicos
        /*User::factory(5)->sequence(fn ($sequence) => ['ci' => '10000000' . $sequence->index])->create()->each(function ($user) {
            $user->assignRole('cobrador');
        });

        User::factory(10)->sequence(fn ($sequence) => ['ci' => '20000000' . $sequence->index])->create()->each(function ($user) {
            $user->assignRole('client');
        });*/
    }
}
