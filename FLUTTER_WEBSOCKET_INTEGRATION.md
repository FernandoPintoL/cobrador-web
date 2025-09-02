# Guía rápida: Conectarte desde Flutter a tu backend (Laravel) con Reverb / Pusher protocol

Esta guía resume las recomendaciones prácticas para que tu app Flutter se conecte a los eventos de Laravel mediante el protocolo Pusher (compatible con Laravel Reverb y también con Pusher oficial). Si ya estás usando un servidor WebSocket Node propio, revisa también documentaciones/documentaciones/FLUTTER_WEBSOCKET_INTEGRATION.md para integración vía socket.io.

## 1) Elegir el modo de conexión

Tienes dos caminos compatibles con tu backend actual:

- Modo 1: Protocolo Pusher (recomendado con Laravel Reverb) — ideal para usar las emisiones nativas de tus eventos ShouldBroadcast y canales privados definidos en routes/channels.php.
- Modo 2: Servidor WebSocket externo (socket.io) — si decides consumir los endpoints puente creados en tu backend (WebSocketNotificationListener/WebSocketNotificationService) y un servidor Node.

Esta guía cubre el Modo 1 (Pusher/Reverb) con ejemplos listos para Flutter.

## 2) Paquetes Flutter

En pubspec.yaml agrega uno de los paquetes compatibles con Pusher protocol.

Opción A (muy usada):

dependencies:
  flutter:
    sdk: flutter
  pusher_channels_flutter: ^2.4.0
  dio: ^5.4.0

Opción B (alternativa):

dependencies:
  flutter:
    sdk: flutter
  laravel_echo: ^1.0.3
  pusher_client: ^2.0.0
  dio: ^5.4.0

Recomendación: comienza con pusher_channels_flutter por su simplicidad.

## 3) Variables del backend que debes conocer

- BROADCAST_DRIVER=reverb
- REVERB_HOST=192.168.100.21 y REVERB_PORT=6001 (ajusta si cambian en producción)
- REVERB_SCHEME=http (useTLS=false)
- REVERB_APP_KEY=jadsb4pnyhj87dff3kuh (no expongas SECRET/ID en el cliente)
- APP_URL=http://192.168.100.21:8000 (endpoint de API)
- Ruta de auth para canales privados: /broadcasting/auth (Laravel por defecto)
- Canales que usas hoy:
  - private-user.{id}
  - private-credits-attention
  - private-waiting-list
  - private-payments

Importante: Los nombres anteriores llevan prefijo private- al usar pusher_channels_flutter. En Laravel defines new PrivateChannel('user.{id}') y el cliente se suscribe a private-user.{id}. Esto ya está cubierto en routes/channels.php.

## 4) Configuración típica en Flutter (pusher_channels_flutter)

Crear un servicio para inicializar la conexión y manejar la autenticación privada contra tu API Laravel.

import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';
import 'package:dio/dio.dart';

class ReverbService {
  final _pusher = PusherChannelsFlutter.getInstance();
  final Dio _dio = Dio(BaseOptions(
    baseUrl: 'http://192.168.100.21:8000', // API Laravel (APP_URL)
    validateStatus: (s) => s != null && s >= 200 && s < 500,
  ));

  // Ajusta con tus valores reales
  static const String appKey = 'jadsb4pnyhj87dff3kuh';
  static const String host = '192.168.100.21';
  static const int port = 6001; // REVERB_PORT
  static const bool isTLS = false; // http => false, https => true

  Future<void> connect({
    required String bearerToken,
    required int userId,
  }) async {
    await _pusher.init(
      apiKey: appKey,
      cluster: 'mt1', // no se usa en Reverb, pero el SDK lo pide
      onConnectionStateChange: (current, previous) {},
      onError: (message, code, exception) {},
      authEndpoint: 'http://192.168.100.21:8000/broadcasting/auth',
      onAuthorizer: (channelName, socketId, options) async {
        // Authorizer: envía el socket_id y channel_name a tu backend con el Bearer Token
        final resp = await _dio.post(
          '/broadcasting/auth',
          data: {
            'socket_id': socketId,
            'channel_name': channelName,
          },
          options: Options(headers: {
            'Authorization': 'Bearer $bearerToken',
            'Accept': 'application/json',
          }),
        );
        if (resp.statusCode == 200) {
          return resp.data; // debe devolver {auth: 'key:signature', ...}
        }
        throw Exception('Auth failed: ${resp.statusCode}');
      },
      // Config Reverb self-hosted
      host: host,
      port: port,
      encrypted: isTLS,
      // Para Reverb, desactiva wss si usas http
      activityTimeout: 120000,
    );

    await _pusher.connect();

    // Suscribir a tus canales
    await _subscribeUser(userId);
    await _subscribeDomainChannels();
  }

  Future<void> _subscribeUser(int userId) async {
    final channelName = 'private-user.$userId';
    await _pusher.subscribe(channelName: channelName, onEvent: (event) {
      // event.eventName: payment.received, credit.requires.attention, credit.waiting.list.update
      // event.data: JSON string -> parse si lo necesitas
      // Maneja por nombre del evento
    });
  }

  Future<void> _subscribeDomainChannels() async {
    final channels = [
      'private-payments',
      'private-credits-attention',
      'private-waiting-list',
    ];
    for (final ch in channels) {
      await _pusher.subscribe(channelName: ch, onEvent: (event) {
        // Procesa según event.eventName
      });
    }
  }

  Future<void> disconnect() async {
    await _pusher.disconnect();
  }
}

Notas:
- Autenticación: Este proyecto usa Laravel Sanctum. Genera un token personal (Personal Access Token) tras login y úsalo como Bearer en /broadcasting/auth y en tus peticiones.
  - Si usas sesión cookie (SPA), puedes enviar cookies en lugar de Bearer, pero en apps móviles se recomienda token Bearer de Sanctum.
- En Android emulador, usa 10.0.2.2 para referenciar localhost del host. En dispositivo físico, usa la IP LAN del servidor.

## 5) Escuchar eventos emitidos desde Laravel

Tus eventos emiten con broadcastAs():
- payment.received
- credit.requires.attention
- credit.waiting.list.update

En Flutter, dentro del callback onEvent, compara event.eventName para enrutar.

## 6) Prueba rápida paso a paso

1) En Laravel: asegúrate
   - BROADCAST_DRIVER=reverb
   - REVERB_APP_KEY/SECRET/ID configurados
   - Reverb en marcha (php artisan reverb:start) o servicio en producción
   - routes/channels.php contiene user.{id}, payments, credits-attention, waiting-list (ya agregado)
   - Un usuario autenticado puede llamar a POST /broadcasting/auth

2) En Flutter:
   - Obtén bearerToken tras login contra tu API
   - Llama ReverbService().connect(bearerToken: token, userId: miId)
   - Verifica que la suscripción a private-user.{id} recibe eventos cuando en Laravel dispatch(new PaymentReceived(...))

3) Debug útil:
   - Revisa storage/logs/laravel.log si la auth de broadcast falla
   - Verifica que tu test BroadcastAuthTest ya pasa (cubre private-user.{id})

## 7) Alternativa con laravel_echo + pusher_client

import 'package:laravel_echo/laravel_echo.dart';
import 'package:pusher_client/pusher_client.dart';

class EchoService {
  late final Echo echo;

  Future<void> connect({required String bearerToken, required int userId}) async {
    final pusher = PusherClient(
      'jadsb4pnyhj87dff3kuh',
      PusherOptions(
        host: '192.168.100.21',
        port: 6001,
        encrypted: false,
      ),
      enableLogging: true,
    );

    echo = Echo(
      broadcaster: 'pusher',
      client: pusher,
      auth: {
        'headers': {
          'Authorization': 'Bearer $bearerToken',
          'Accept': 'application/json',
        }
      },
      authEndpoint: 'http://192.168.100.21:8000/broadcasting/auth',
    );

    echo.private('user.$userId').listen('payment.received', (e) {
      // e -> payload
    });

    echo.private('payments').listen('payment.received', (e) {});
    echo.private('credits-attention').listen('credit.requires.attention', (e) {});
    echo.private('waiting-list').listen('credit.waiting.list.update', (e) {});
  }
}

Observa que con Echo usas los nombres de canal sin prefijo private- porque la librería lo maneja internamente.

## 8) Checklist de red y CORS

- Si usas HTTP (no TLS), configura el cliente con encrypted: false y conecta a ws://
- Si usas HTTPS, debes usar wss:// y tener certificados válidos o aceptar self-signed en desarrollo.
- Asegúrate que el host/puerto de Reverb sea accesible desde el dispositivo.
- En Android 9+, si usas HTTP claro, agrega network_security_config para permitir tráfico no seguro a tu host de desarrollo.

## 9) Seguridad y autorización

- Canales privados ya tienen callbacks en routes/channels.php para user.{id}, payments, credits-attention, waiting-list.
- El backend valida que el user_id del token sea el dueño del canal user.{id}.
- Evita exponer REVERB_APP_SECRET al cliente; sólo usa APP_KEY en el cliente.

## 10) Errores comunes

- 401 al suscribirte: Bearer token inválido o falta enviar encabezados en authorizer.
- 404/405 en /broadcasting/auth: ruta distinta o método incorrecto (debe ser POST).
- Conexión pero sin eventos: revisa que BROADCAST_DRIVER sea reverb y que se estén dispatchando eventos.
- En producción con proxy/Nginx: asegura que la ruta de WebSocket (Upgrade/Connection) esté permitida hacia el puerto de Reverb.

## 11) Referencias

- Laravel Broadcasting (v12)
- Laravel Reverb (v1)
- pusher_channels_flutter docs
- laravel_echo + pusher_client docs

Si necesitas, puedo adaptar este ejemplo a tu dominio, puertos y a tu flujo de login actual (Sanctum/Passport/JWT).
