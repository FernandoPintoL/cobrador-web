# Despliegue en Railway con Laravel Reverb (Checklist)

Esta guía resume la configuración mínima para desplegar este proyecto en Railway usando Laravel Reverb como servidor WebSocket.

## Variables de entorno mínimas

BROADCAST_DRIVER=reverb
REVERB_APP_ID=cobrador-app
REVERB_APP_KEY=alguna-clave
REVERB_APP_SECRET=algún-secreto
REVERB_HOST=0.0.0.0
REVERB_PORT=${PORT}
REVERB_SCHEME=http
APP_URL=https://cobrador-web-production.up.railway.app
SESSION_DOMAIN=cobrador-web-production.up.railway.app
SANCTUM_STATEFUL_DOMAINS=cobrador-web-production.up.railway.app

# Recomendado (Redis)
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=redis
REDIS_URL=redis://:password@host:port

# Opcional si hay problemas detrás de proxy
APP_TRUSTED_PROXIES=*
REVERB_ALLOWED_ORIGINS=https://cobrador-web-production.up.railway.app

## Servicios recomendados en Railway

- Web (HTTP): tu servidor web para Laravel.
- Queue worker: `php artisan queue:work --queue=default --tries=1 --sleep=1`
- Reverb (WebSocket): `php artisan reverb:start --host=0.0.0.0 --port=${PORT}`

El servicio Reverb no expone rutas HTTP tradicionales; si Railway lo permite, configura health check tipo TCP o desactívalo para dicho servicio.

## Configuración de cliente (Echo)

En el frontend, Echo (Pusher compatible) debe apuntar al dominio público con TLS:

- key: VITE_REVERB_APP_KEY
- wsHost: dominio Railway o personalizado
- wsPort: 443
- wssPort: 443
- forceTLS: true
- enabledTransports: ["ws", "wss"]

Este proyecto ya utiliza `configureEcho` y toma valores de `VITE_REVERB_*` si se definen.

## Notas

- Railway termina TLS en el proxy. Dentro del contenedor Reverb usamos HTTP (`REVERB_SCHEME=http`).
- Asegúrate de que APP_URL y SESSION_DOMAIN coinciden con el dominio público para que cookies / Sanctum funcionen en canales privados.
- Si vas a escalar Reverb, habilita `REVERB_SCALING_ENABLED=true` y usa Redis.
