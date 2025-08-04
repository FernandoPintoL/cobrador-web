# 🔔 Implementación de WebSockets para Notificaciones en Tiempo Real

## 📋 **Resumen Ejecutivo**

### **Recomendación: Backend Laravel con Laravel Reverb**

**¿Por qué Backend?**
- ✅ **Centralización**: Un solo punto de control para todas las notificaciones
- ✅ **Seguridad**: Autenticación y autorización centralizadas con Sanctum
- ✅ **Escalabilidad**: Mejor manejo de conexiones simultáneas
- ✅ **Integración**: Acceso directo a base de datos y modelos de Laravel
- ✅ **Mantenimiento**: Un solo código base para la lógica de notificaciones

## 🏗️ **Arquitectura Propuesta**

```
Flutter App ←→ Laravel API ←→ Laravel Reverb WebSocket
     ↑                              ↓
   Notificaciones ←← Broadcasting ←← Eventos
     Push                           del Sistema
```

## 🚀 **Plan de Implementación**

### **Fase 1: Instalación y Configuración Backend**

#### **1.1 Instalar Laravel Reverb**
```bash
# Instalar Laravel Reverb (WebSocket server nativo)
composer require laravel/reverb

# Instalar Pusher PHP SDK (para compatibilidad)
composer require pusher/pusher-php-server

# Configurar Reverb
php artisan reverb:install

# Ejecutar migraciones de broadcasting
php artisan migrate
```

#### **1.2 Configurar .env**
```bash
# Configuración de Broadcasting
BROADCAST_DRIVER=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST="0.0.0.0"
REVERB_PORT=8080
REVERB_SCHEME=http

# Para producción usar HTTPS
# REVERB_SCHEME=https
# REVERB_PORT=443
```

#### **1.3 Configurar config/broadcasting.php**
```php
'connections' => [
    'reverb' => [
        'driver' => 'reverb',
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_APP_SECRET'),
        'app_id' => env('REVERB_APP_ID'),
        'options' => [
            'host' => env('REVERB_HOST', '0.0.0.0'),
            'port' => env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
            'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
        ],
    ],
]
```

### **Fase 2: Eventos y Notificaciones (Ya implementado)**

✅ `CreditRequiresAttention` - Notificación de créditos que requieren atención
✅ `WebSocketNotificationController` - Controller para manejar notificaciones WebSocket

### **Fase 3: Frontend Flutter**

#### **3.1 Dependencias Flutter**
```yaml
# pubspec.yaml
dependencies:
  socket_io_client: ^2.0.3+1
  flutter_local_notifications: ^16.3.2
  permission_handler: ^11.2.0
```

#### **3.2 Servicio WebSocket Flutter**
```dart
// lib/services/websocket_service.dart
import 'package:socket_io_client/socket_io_client.dart' as IO;

class WebSocketService {
  static final WebSocketService _instance = WebSocketService._internal();
  factory WebSocketService() => _instance;
  WebSocketService._internal();

  IO.Socket? socket;
  bool _isConnected = false;

  // Conectar al WebSocket
  Future<void> connect(String token, int userId) async {
    try {
      socket = IO.io('http://tu-servidor.com:8080', <String, dynamic>{
        'transports': ['websocket'],
        'auth': {'token': token},
        'autoConnect': false,
      });

      socket!.connect();

      socket!.onConnect((_) {
        print('🔗 Conectado a WebSocket');
        _isConnected = true;
        _subscribeToUserChannel(userId);
      });

      socket!.onDisconnect((_) {
        print('❌ Desconectado de WebSocket');
        _isConnected = false;
      });

      _setupEventListeners();
    } catch (e) {
      print('❌ Error conectando WebSocket: $e');
    }
  }

  // Suscribirse al canal del usuario
  void _subscribeToUserChannel(int userId) {
    socket!.emit('subscribe', {
      'channel': 'private-cobrador.$userId'
    });
  }

  // Configurar listeners de eventos
  void _setupEventListeners() {
    // Créditos que requieren atención
    socket!.on('credit.requires.attention', (data) {
      _handleCreditAttention(data);
    });

    // Pagos recibidos
    socket!.on('payment.received', (data) {
      _handlePaymentReceived(data);
    });

    // Notificaciones generales
    socket!.on('notification', (data) {
      _handleGeneralNotification(data);
    });
  }

  // Manejar notificación de crédito
  void _handleCreditAttention(dynamic data) {
    final notification = {
      'title': 'Crédito requiere atención',
      'body': data['message'],
      'type': 'credit_attention',
      'data': data,
    };
    
    _showLocalNotification(notification);
    _updateNotificationBadge();
  }

  // Manejar notificación de pago
  void _handlePaymentReceived(dynamic data) {
    final notification = {
      'title': 'Pago recibido',
      'body': 'Pago de \$${data['amount']} recibido',
      'type': 'payment_received',
      'data': data,
    };
    
    _showLocalNotification(notification);
  }

  // Mostrar notificación local
  void _showLocalNotification(Map<String, dynamic> notification) {
    // Implementar con flutter_local_notifications
    print('📱 Notificación: ${notification['title']}');
  }

  // Actualizar badge de notificaciones
  void _updateNotificationBadge() {
    // Actualizar contador de notificaciones no leídas
  }

  // Desconectar
  void disconnect() {
    socket?.disconnect();
    _isConnected = false;
  }

  bool get isConnected => _isConnected;
}
```

#### **3.3 Integración en la App Flutter**
```dart
// lib/main.dart
class MyApp extends StatefulWidget {
  @override
  _MyAppState createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  final WebSocketService _websocketService = WebSocketService();

  @override
  void initState() {
    super.initState();
    _initializeWebSocket();
  }

  Future<void> _initializeWebSocket() async {
    // Obtener token del usuario autenticado
    final token = await AuthService.getToken();
    final userId = await AuthService.getUserId();
    
    if (token != null && userId != null) {
      await _websocketService.connect(token, userId);
    }
  }

  @override
  void dispose() {
    _websocketService.disconnect();
    super.dispose();
  }
}
```

### **Fase 4: Automatización de Notificaciones**

#### **4.1 Command para Detectar Créditos que Requieren Atención**
```bash
php artisan make:command CheckCreditsRequiringAttention
```

```php
// app/Console/Commands/CheckCreditsRequiringAttention.php
class CheckCreditsRequiringAttention extends Command
{
    protected $signature = 'credits:check-attention';
    protected $description = 'Check for credits requiring attention and send notifications';

    public function handle()
    {
        $credits = Credit::with(['client.assignedCobrador'])
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('end_date', '<', now())
                      ->orWhere('end_date', '<=', now()->addDays(7));
            })
            ->get();

        foreach ($credits as $credit) {
            if ($credit->client->assignedCobrador) {
                event(new CreditRequiresAttention($credit, $credit->client->assignedCobrador));
                $this->info("Notificación enviada para crédito {$credit->id}");
            }
        }

        $this->info("Procesados {$credits->count()} créditos");
    }
}
```

#### **4.2 Programar en Cron (app/Console/Kernel.php)**
```php
protected function schedule(Schedule $schedule)
{
    // Verificar créditos que requieren atención cada hora
    $schedule->command('credits:check-attention')
             ->hourly()
             ->withoutOverlapping();
}
```

## 🔒 **Seguridad y Autenticación**

### **Canal Privado con Sanctum**
```php
// routes/channels.php
Broadcast::channel('cobrador.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId && $user->hasRole('cobrador');
});

Broadcast::channel('admin.notifications', function ($user) {
    return $user->hasRole(['admin', 'manager']);
});
```

## 📊 **Tipos de Notificaciones Implementadas**

### **1. Créditos que Requieren Atención**
- **Trigger**: Créditos vencidos o próximos a vencer
- **Target**: Cobrador asignado al cliente
- **Canal**: `private-cobrador.{cobrador_id}`

### **2. Pagos Recibidos**
- **Trigger**: Nuevo pago registrado
- **Target**: Cobrador que recibió el pago
- **Canal**: `private-cobrador.{cobrador_id}`

### **3. Asignación de Clientes**
- **Trigger**: Cliente asignado a cobrador
- **Target**: Cobrador asignado
- **Canal**: `private-cobrador.{cobrador_id}`

## 🚀 **Comandos para Poner en Marcha**

```bash
# 1. Instalar dependencias
composer require laravel/reverb pusher/pusher-php-server

# 2. Configurar Reverb
php artisan reverb:install

# 3. Ejecutar migraciones
php artisan migrate

# 4. Iniciar el servidor WebSocket (desarrollo)
php artisan reverb:start

# 5. Iniciar queue worker (para broadcasting)
php artisan queue:work

# 6. Programar comando de verificación
php artisan schedule:work
```

## 🔧 **Producción y Deployment**

### **Usando PM2 o Supervisor**
```bash
# pm2.config.js
module.exports = {
  apps: [
    {
      name: 'reverb',
      script: 'php',
      args: 'artisan reverb:start',
      cwd: '/path/to/your/app',
      instances: 1,
      watch: false,
      restart_delay: 1000,
    }
  ]
};
```

### **Nginx Proxy (Producción)**
```nginx
# Proxy WebSocket
location /ws {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
}
```

## ✅ **Ventajas de esta Implementación**

1. **🔐 Seguridad**: Autenticación integrada con Sanctum
2. **📱 Cross-platform**: Funciona en Android e iOS
3. **🚀 Performance**: WebSocket nativo de Laravel optimizado
4. **🔄 Escalabilidad**: Fácil de escalar horizontalmente
5. **🛠️ Mantenibilidad**: Todo en el mismo stack tecnológico
6. **💰 Costo**: Sin costos adicionales de servicios terceros
7. **🎯 Customización**: Control total sobre la lógica de notificaciones

## 📈 **Próximos Pasos**

1. ✅ **Implementar eventos básicos** (Ya hecho)
2. 🔄 **Configurar Laravel Reverb**
3. 📱 **Implementar cliente Flutter**
4. 🤖 **Automatizar notificaciones**
5. 🔒 **Configurar canales seguros**
6. 🚀 **Deploy en producción**

El sistema propuesto te dará notificaciones en tiempo real robustas, seguras y completamente integradas con tu arquitectura actual.
