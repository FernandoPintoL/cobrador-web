# Migración a Laravel Reverb (eliminando Node.js server.js)

Este documento describe cómo la aplicación ha sido migrada para usar exclusivamente Laravel Reverb para comunicaciones en tiempo real, sustituyendo el servidor Node.js (websocket-server/server.js). Incluye pasos de despliegue a producción e integración con Flutter.

## ¿Qué cambió?

- Eliminado el envío de notificaciones al servidor Node.js.
- Se dejó de usar los listeners que reenviaban eventos a Node por HTTP:
  - Eliminado el mapeo de `App\Listeners\WebSocketNotificationListener@*` en el caché de eventos.
  - Los listeners `SendPaymentReceivedNotification` y `SendCreditAttentionNotification` ya no dependen de `WebSocketNotificationService`.
- Los eventos Laravel (`ShouldBroadcast`) son ahora la única fuente de tiempo real a través de Reverb:
  - `App\Events\PaymentReceived` (evento: `payment.received`)
  - `App\Events\CreditRequiresAttention` (evento: `credit.requires.attention`)
  - `App\Events\CreditWaitingListUpdate` (evento: `credit.waiting.list.update`)
  - `App\Events\TestNotification` (evento: `test.notification`)

## Canales que se emiten

- Privados generales:
  - `private-payments` (desde PaymentReceived)
  - `private-credits-attention` (desde CreditRequiresAttention)
  - `private-waiting-list` (desde CreditWaitingListUpdate)
- Privados por usuario:
  - `private-user.{id}` (varios eventos añaden este canal según corresponda)
- Público (solo pruebas):
  - `test.notifications`

Nota: En código se usan `new PrivateChannel('...')` y `new Channel('...')`. Con Laravel Echo, los privados se suscriben con `Echo.private('...')` y requieren autenticación de broadcasting.

## Variables de entorno (Reverb)

Asegúrate de configurar en `.env` (ejemplo):

```
BROADCAST_DRIVER=reverb
REVERB_APP_ID=cobrador-app
REVERB_APP_KEY=reverb-app-key
REVERB_APP_SECRET=reverb-app-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_DEBUG=false

# Laravel Echo/Broadcasting auth
SESSION_DOMAIN=yourdomain.tld
SANCTUM_STATEFUL_DOMAINS=yourdomain.tld
APP_URL=https://yourdomain.tld

QUEUE_CONNECTION=redis   # o database, pero se recomienda redis
CACHE_DRIVER=redis
BROADCAST_CONNECTION=redis
```

Revisa `config/broadcasting.php` para confirmar la conexión `reverb` y claves.

## Puesta en marcha (local y producción)

1. Instalar dependencias Reverb (ya presentes si el proyecto trae laravel/reverb):
   - `composer install --no-dev` en producción
2. Compilar assets del frontend (si aplica): `npm ci && npm run build`
3. Migraciones y cachés:
   - `php artisan migrate --force`
   - `php artisan config:cache && php artisan route:cache && php artisan event:cache`
4. Iniciar Reverb server (systemd/supervisor):
   - `php artisan reverb:start --host=0.0.0.0 --port=8080`
   - Para servicio persistente, configura Supervisor o systemd.
5. Iniciar colas:
   - `php artisan queue:work --tries=3` (o usa Horizon)

Importante: Ya no se debe iniciar `node websocket-server/server.js`.

## Autenticación de canales privados

- Define las reglas en `routes/channels.php`. Ejemplos típicos:

```php
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('payments', function ($user) {
    return $user != null; // usuarios autenticados
});

Broadcast::channel('credits-attention', function ($user) {
    return $user != null;
});

Broadcast::channel('waiting-list', function ($user) {
    return $user->hasAnyRole(['manager','admin','cobrador']);
});
```

Asegúrate de que el login/autenticación esté funcionando (Sanctum o session) para que Echo pueda firmar las suscripciones privadas.

## Integración Flutter (Pusher compatible)

Reverb es compatible con el protocolo Pusher. Un ejemplo usando `pusher_client` o `pusher_channels_flutter`:

```dart
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

final pusher = PusherChannelsFlutter.getInstance();

Future<void> initPusher(String authToken) async {
  await pusher.init(
    apiKey: 'reverb-app-key',
    cluster: 'mt1', // puede ignorarse, pero algunas libs lo requieren
    authEndpoint: 'https://yourdomain.tld/broadcasting/auth',
    onEvent: (event) {
      print('Event: \'${event.eventName}\' - data: ${event.data}');
    },
    onSubscriptionSucceeded: (channelName, data) {
      print('Subscribed to $channelName');
    },
    onConnectionStateChange: (currentState, previousState) {
      print('Pusher state: $previousState -> $currentState');
    },
    onAuthenticationFailure: (message, e) {
      print('Auth failed: $message');
    },
    useTLS: false, // true si usas https/wss
    host: 'yourdomain.tld',
    port: 8080, // puerto de Reverb
    authHeaders: {
      'Authorization': 'Bearer $authToken', // si usas Sanctum API tokens o JWT
      'Accept': 'application/json'
    },
  );

  await pusher.connect();

  // Suscripciones (ejemplos)
  await pusher.subscribe(channelName: 'private-user.123');
  await pusher.subscribe(channelName: 'private-payments');
  await pusher.subscribe(channelName: 'private-waiting-list');

  // Escuchar eventos
  await pusher.bind(
    channelName: 'private-payments',
    eventName: 'payment.received',
    onEvent: (event) { print('Pago recibido: ${event.data}'); },
  );

  await pusher.bind(
    channelName: 'private-credits-attention',
    eventName: 'credit.requires.attention',
    onEvent: (event) { print('Crédito requiere atención: ${event.data}'); },
  );

  await pusher.bind(
    channelName: 'private-waiting-list',
    eventName: 'credit.waiting.list.update',
    onEvent: (event) { print('Lista de espera: ${event.data}'); },
  );
}
```

Notas:
- En canales privados debes anteponer `private-` al suscribir con Pusher libs.
- Si usas WSS en producción, configura certificado TLS y `useTLS: true`, puerto 443 o el que configures para Reverb.

## Flujo de eventos en este proyecto

- `PaymentReceived`
  - Canales: `payments`, `user.{cobrador}`, `user.{manager}`, `user.{client}`
  - Evento: `payment.received`
  - Payload: información de pago, cobrador, manager, cliente

- `CreditRequiresAttention`
  - Canales: `credits-attention`, `user.{cobrador}`, `user.{manager}`
  - Evento: `credit.requires.attention`

- `CreditWaitingListUpdate`
  - Canales: `waiting-list`, `user.{client_id}`, `user.{created_by}`
  - Evento: `credit.waiting.list.update`

- `TestNotification` (soporte para notificaciones en BD)
  - Canales: `user.{id}` y `test.notifications`
  - Evento: `test.notification`

## Limpieza de duplicados

- Eliminado el reenvío a Node.js desde:
  - `App\Listeners\WebSocketNotificationListener` (desvinculado del sistema de eventos)
  - `SendPaymentReceivedNotification` ya no usa `WebSocketNotificationService`
  - `SendCreditAttentionNotification` ya no usa `WebSocketNotificationService`
- La clase `App\Services\WebSocketNotificationService` queda como DEPRECADA para compatibilidad con herramientas de prueba heredadas; no debe usarse en producción. Puede eliminarse en una futura iteración si ya no hay referencias activas.

## Checklist de despliegue

- [ ] Configurar `.env` con claves de Reverb
- [ ] Configurar reglas en `routes/channels.php`
- [ ] Asegurar autenticación (session/Sanctum)
- [ ] Iniciar `queue:work` y `reverb:start` como servicios
- [ ] Verificar suscripción desde Flutter a `private-user.{id}` y demás canales

## Troubleshooting

- Si no recibes eventos en Flutter:
  - Verifica que `broadcasting/auth` responda 200 con las cookies/cabeceras correctas
  - Revisa `storage/logs/laravel.log` por errores de broadcasting
  - Asegura que las colas estén corriendo (los eventos ShouldBroadcast encolan por defecto si usan ShouldBroadcastNow vs ShouldBroadcast)
  - Comprueba host/port/TLS entre app y Flutter

## Estado del servidor Node.js

- El directorio `websocket-server/` ya no es necesario. No lo inicies en ningún entorno.
- Si deseas, puedes eliminarlo del repositorio para evitar confusiones.
