<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class DiagnoseUser extends Command
{
    protected $signature = 'diagnose:user {id}';
    protected $description = 'Diagnose user and check roles';

    public function handle()
    {
        $userId = $this->argument('id');
        $user = User::with('roles')->find($userId);
        
        if (!$user) {
            $this->error("Usuario con ID {$userId} no encontrado");
            $this->info("Usuarios disponibles:");
            User::with('roles')->get()->each(function($u) {
                $this->line("ID: {$u->id} - {$u->name} - Roles: " . $u->roles->pluck('name')->implode(', '));
            });
            return;
        }
        
        $this->info("Usuario encontrado: {$user->name}");
        $this->info("Roles: " . $user->roles->pluck('name')->implode(', '));
        
        $isManager = $user->hasRole('manager');
        $this->info("¿Es manager? " . ($isManager ? "SÍ" : "NO"));
        
        if (!$isManager) {
            $this->warn("PROBLEMA: El usuario no tiene el rol 'manager'");
            $this->info("Usuarios que SÍ son managers:");
            User::whereHas('roles', function($q) {
                $q->where('name', 'manager');
            })->each(function($manager) {
                $this->line("- ID: {$manager->id}, Nombre: {$manager->name}");
            });
        } else {
            $this->success("El usuario es manager, la ruta debería funcionar");
        }
    }
}
