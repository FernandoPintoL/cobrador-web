<?php

require_once 'vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Credit;
use App\Models\Notification;

echo "🔔 Revisando detalles de notificaciones\n";
echo "=======================================\n";

// Obtener las últimas notificaciones
echo "📋 Últimas 5 notificaciones en el sistema:\n";
$latestNotifications = Notification::with(['user'])
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

foreach ($latestNotifications as $notification) {
    echo "  - ID: {$notification->id}\n";
    echo "    Para: {$notification->user->name} (ID: {$notification->user_id})\n";
    echo "    Tipo: {$notification->type}\n";
    echo "    Mensaje: {$notification->message}\n";
    echo "    Estado: {$notification->status}\n";
    echo "    Fecha: {$notification->created_at}\n";
    echo "    ---\n";
}

// Verificar notificaciones específicas para managers
echo "\n📢 Notificaciones pendientes para managers:\n";
$managers = User::whereHas('roles', function ($query) {
    $query->where('name', 'manager');
})->get();

foreach ($managers as $manager) {
    $unreadNotifications = Notification::where('user_id', $manager->id)
        ->where('status', 'unread')
        ->count();
    
    echo "  Manager: {$manager->name} (ID: {$manager->id}) - {$unreadNotifications} notificaciones sin leer\n";
    
    if ($unreadNotifications > 0) {
        $notifications = Notification::where('user_id', $manager->id)
            ->where('status', 'unread')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();
        
        foreach ($notifications as $notification) {
            echo "    📋 {$notification->type}: {$notification->message}\n";
        }
    }
}

// Verificar notificaciones específicas para cobradores
echo "\n💰 Notificaciones pendientes para cobradores:\n";
$cobradores = User::whereHas('roles', function ($query) {
    $query->where('name', 'cobrador');
})->get();

foreach ($cobradores as $cobrador) {
    $unreadNotifications = Notification::where('user_id', $cobrador->id)
        ->where('status', 'unread')
        ->count();
    
    echo "  Cobrador: {$cobrador->name} (ID: {$cobrador->id}) - {$unreadNotifications} notificaciones sin leer\n";
    
    if ($unreadNotifications > 0) {
        $notifications = Notification::where('user_id', $cobrador->id)
            ->where('status', 'unread')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();
        
        foreach ($notifications as $notification) {
            echo "    📋 {$notification->type}: {$notification->message}\n";
        }
    }
}

// Verificar último crédito creado
echo "\n💳 Último crédito creado:\n";
$lastCredit = Credit::with(['client', 'createdBy'])
    ->orderBy('created_at', 'desc')
    ->first();

if ($lastCredit) {
    echo "  - ID: {$lastCredit->id}\n";
    echo "  - Cliente: {$lastCredit->client->name}\n";
    echo "  - Creado por: {$lastCredit->createdBy->name}\n";
    echo "  - Estado: {$lastCredit->status}\n";
    echo "  - Monto: \${$lastCredit->amount}\n";
    echo "  - Fecha: {$lastCredit->created_at}\n";
}

echo "\n✨ Revisión completada\n";
