<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class MakeUserManager extends Command
{
    protected $signature = 'user:make-manager {id}';
    protected $description = 'Make a user a manager';

    public function handle()
    {
        $userId = $this->argument('id');
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("Usuario con ID {$userId} no encontrado");
            return;
        }
        
        $this->info("Usuario: {$user->name}");
        $this->info("Roles actuales: " . $user->roles->pluck('name')->implode(', '));
        
        if ($user->hasRole('manager')) {
            $this->warn("El usuario ya es manager");
            return;
        }
        
        $user->assignRole('manager');
        $user->refresh();
        
        $this->info("✅ Usuario {$user->name} ahora es manager");
        $this->info("Roles actuales: " . $user->roles->pluck('name')->implode(', '));
    }
}
