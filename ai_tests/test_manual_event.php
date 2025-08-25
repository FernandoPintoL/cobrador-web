<?php

require_once 'vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Credit;
use App\Events\CreditWaitingListUpdate;
use Illuminate\Support\Facades\Log;

echo "🧪 Probando disparo manual de evento\n";
echo "=====================================\n";

// Obtener un crédito existente
$credit = Credit::with(['client', 'createdBy'])->orderBy('created_at', 'desc')->first();
$user = User::find($credit->created_by);

echo "📋 Crédito de prueba:\n";
echo "  - ID: {$credit->id}\n";
echo "  - Cliente: {$credit->client->name}\n";
echo "  - Creado por: {$user->name}\n";
echo "  - Estado: {$credit->status}\n";

echo "\n🔥 Disparando evento manualmente...\n";

// Disparar el evento manualmente
event(new CreditWaitingListUpdate($credit, 'created', $user));

echo "✅ Evento disparado\n";

// Esperar un poco para que se procese
sleep(1);

// Verificar si se crearon notificaciones
$notificationsCount = \App\Models\Notification::count();
echo "\n📢 Total de notificaciones en sistema: {$notificationsCount}\n";

// Verificar las últimas notificaciones
echo "\n📋 Últimas 3 notificaciones:\n";
$latestNotifications = \App\Models\Notification::with(['user'])
    ->orderBy('created_at', 'desc')
    ->limit(3)
    ->get();

foreach ($latestNotifications as $notification) {
    echo "  - Para: {$notification->user->name}\n";
    echo "    Tipo: {$notification->type}\n";
    echo "    Mensaje: {$notification->message}\n";
    echo "    Fecha: {$notification->created_at}\n";
    echo "    ---\n";
}

echo "\n✨ Prueba completada\n";
