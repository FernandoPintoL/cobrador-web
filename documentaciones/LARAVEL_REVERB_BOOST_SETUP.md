# Implementación de Laravel Reverb y Laravel Boost

Este documento describe los cambios realizados en el repositorio para integrar Laravel Reverb (WebSockets nativo) y Laravel Boost (optimizaciones de rendimiento en Blade/HTTP), además de una guía paso a paso para su instalación y despliegue.

## Cambios aplicados en el repositorio

- composer.json: se añadieron dependencias requeridas
  - laravel/reverb ^1.5
  - laravel/boost ^1.1
- config/reverb.php: se agregó archivo de configuración base para Reverb.
- config/broadcasting.php: ya incluía conexión "reverb"; se mantiene y funciona con las variables .env.
- Documentación: este archivo y WEBSOCKETS_REVERB_MIGRATION.md guían la operación.

## Requisitos previos
- PHP 8.2+
- Redis recomendado para colas y broadcasting (aunque no obligatorio)
- Node 18+ solo si compilas assets con Vite. No se requiere Node.js para websockets con Reverb.

## Instalación de dependencias

1) Instalar paquetes PHP
- composer require laravel/reverb laravel/boost

2) Publicar / instalar Reverb (si no se ha hecho antes)
- php artisan reverb:install

Esto creará/actualizará archivos de configuración y providers si corresponde. Ya existe config/reverb.php en el repo como base.

## Variables de entorno (.env)

Ajusta valores según tu entorno:

BROADCAST_DRIVER=reverb
REVERB_APP_ID=cobrador-app
REVERB_APP_KEY=reverb-app-key
REVERB_APP_SECRET=reverb-app-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_DEBUG=false

# Recomendado para auth de canales privados
APP_URL=https://tu-dominio.tld
SESSION_DOMAIN=tu-dominio.tld
SANCTUM_STATEFUL_DOMAINS=tu-dominio.tld

# Redis (opcional, recomendado)
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=redis

## Uso de Reverb

- Inicia el servidor WebSocket:
  - php artisan reverb:start --host=%REVERB_HOST% --port=%REVERB_PORT%
- Los eventos que implementan ShouldBroadcast se enviarán a los canales configurados. Ya existen eventos y listeners en el proyecto que contemplan esta arquitectura (ver WEBSOCKETS_REVERB_MIGRATION.md).

### Suscripción desde frontend

- Echo.private('private-user.{id}')
- Echo.private('private-payments')
- Echo.channel('test.notifications')

Configura el cliente con las claves de Reverb (Pusher compatible) y el host/puerto.

## Laravel Boost

Laravel Boost acelera plantillas Blade y respuestas mediante precompilación y cacheo inteligente.

### Instalación y activación

1) Instalar paquete (ya agregado a composer.json).
2) Publicar configuración si es necesario:
- php artisan vendor:publish --tag=boost-config

3) Habilitar en .env (opcional dependiendo de la versión):
BOOST_ENABLED=true

4) Limpiar y calentar cachés:
- php artisan optimize:clear
- php artisan view:cache

### Buenas prácticas con Boost
- Evita lógica pesada dentro de vistas.
- Usa componentes y layouts reutilizables.
- Asegura que las rutas que rinden páginas se beneficien de view cache.

## Despliegue (producción)

1) composer install --no-dev --optimize-autoloader
2) php artisan migrate --force
3) php artisan config:cache && php artisan route:cache && php artisan view:cache
4) Supervisar procesos:
   - Cola: php artisan queue:work --tries=1
   - Reverb: php artisan reverb:start --host=0.0.0.0 --port=8080
5) Revisar logs con php artisan pail (si instalado) o tail -f storage/logs/laravel.log

## Verificación rápida
- Ejecuta pruebas incluidas que usen broadcasting (si existieran).
- Desde el cliente, suscríbete a un canal público y dispara un evento de prueba.

## Notas
- Si usas HTTPS para WebSocket, configura REVERB_SCHEME=https y un proxy/certificado. Ajusta useTLS en clientes.
- El repositorio ya no requiere el servidor Node.js websocket-server/server.js para tiempo real.
