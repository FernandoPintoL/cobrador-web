# 📱 Guía de Integración WebSocket para Flutter

## 🔌 Conexión Inicial

### 1. URL de Conexión
```dart
final socket = IO.io('http://192.168.56.22:3001', <String, dynamic>{
  'transports': ['websocket'],
  'autoConnect': false,
});
```

### 2. Conectar al servidor
```dart
socket.connect();
```

### 3. Autenticarse (OBLIGATORIO después de conectar)
```dart
socket.on('connect', (_) {
  print('🔌 Conectado al WebSocket');

  // DEBES AUTENTICARTE INMEDIATAMENTE
  socket.emit('authenticate', {
    'userId': '123',           // ID del usuario (String)
    'userType': 'cobrador',    // Tipo: 'cobrador', 'manager', 'admin', 'client'
    'userName': 'Juan Pérez'   // Nombre del usuario
  });
});
```

### 4. Escuchar confirmación de autenticación
```dart
socket.on('authenticated', (data) {
  print('✅ Autenticado exitosamente: $data');
  // data = { success: true, message: "Autenticación exitosa", userId: "123", userType: "cobrador", clientIP: "192.168.1.10" }
});

socket.on('authentication_error', (data) {
  print('❌ Error de autenticación: $data');
  // data = { success: false, message: "Datos de autenticación inválidos" }
});
```

---

## 📤 EVENTOS QUE FLUTTER DEBE ENVIAR (emit)

### 1. **Autenticación** (OBLIGATORIO al conectar)
```dart
socket.emit('authenticate', {
  'userId': '123',           // String - ID del usuario
  'userType': 'cobrador',    // String - Tipo de usuario
  'userName': 'Juan Pérez'   // String - Nombre del usuario
});
```

---

### 2. **Actualizar Ubicación** (Solo cobradores)
```dart
socket.emit('location_update', {
  'latitude': -16.5000,   // double
  'longitude': -68.1500,  // double
});
```
**Quién recibe:** Administradores (sala `admins`)

---

### 3. **Enviar Mensaje a Usuario**
```dart
socket.emit('send_message', {
  'senderId': '123',      // String - ID del remitente
  'recipientId': '456',   // String - ID del destinatario
  'message': 'Hola!'      // String - Mensaje
});
```
**Quién recibe:** Usuario específico (recipientId)

---

### 4. **Notificación de Ruta**
```dart
socket.emit('route_notification', {
  // Datos de la ruta que quieras enviar
  'routeId': '789',
  'status': 'completed',
  // ... otros campos
});
```
**Quién recibe:** Managers y el mismo emisor

---

### 5. **Ciclo de Vida de Crédito** (Desde Flutter - Opcional)
```dart
socket.emit('credit_lifecycle', {
  'action': 'created',           // String: 'created', 'approved', 'rejected', 'delivered', 'requires_attention'
  'creditId': '100',             // String
  'targetUserId': '456',         // String (opcional) - Usuario específico a notificar
  'credit': {                    // Map - Datos del crédito
    'id': '100',
    'amount': 5000,
    'client_name': 'Cliente X'
  },
  'message': 'Crédito creado',   // String (opcional)
});
```

---

### 6. **Actualización de Pago** (Desde Flutter - Opcional)
```dart
socket.emit('payment_update', {
  'payment': {
    'id': '200',
    'amount': 500,
    'credit_id': '100'
  },
  'cobradorId': '123',  // String (opcional)
  'clientId': '789'     // String (opcional)
});
```

---

## 📥 EVENTOS QUE FLUTTER DEBE ESCUCHAR (on/listen)

### 🔐 **Eventos de Autenticación y Conexión**

#### 1. `connect`
```dart
socket.on('connect', (_) {
  print('🔌 Conectado al servidor WebSocket');
  // IMPORTANTE: Autenticarse aquí
});
```

#### 2. `disconnect`
```dart
socket.on('disconnect', (_) {
  print('❌ Desconectado del WebSocket');
});
```

#### 3. `authenticated`
```dart
socket.on('authenticated', (data) {
  // data: { success: true, message: "...", userId: "123", userType: "cobrador", clientIP: "..." }
  print('✅ Autenticado: $data');
});
```

#### 4. `authentication_error`
```dart
socket.on('authentication_error', (data) {
  // data: { success: false, message: "..." }
  print('❌ Error de autenticación: $data');
});
```

#### 5. `user_connected`
```dart
socket.on('user_connected', (data) {
  // data: { userId: "456", userName: "María", userType: "manager", clientIP: "...", connectedAt: "2025-10-19T..." }
  print('👤 Usuario conectado: ${data['userName']}');
});
```

#### 6. `user_disconnected`
```dart
socket.on('user_disconnected', (data) {
  // data: { userId: "456", userName: "María", userType: "manager", clientIP: "..." }
  print('👋 Usuario desconectado: ${data['userName']}');
});
```

---

### 💳 **Eventos de Créditos**

#### 7. `credit_waiting_approval` (MANAGER recibe)
```dart
socket.on('credit_waiting_approval', (data) {
  // data: {
  //   message: "El cobrador Juan ha creado un crédito de $5000 que requiere aprobación",
  //   data: {
  //     type: 'credit_created',
  //     credit: { id, amount, total_amount, balance, frequency, status, start_date, end_date, ... },
  //     cobrador: { id, name, email }
  //   },
  //   timestamp: "2025-10-19T..."
  // }

  print('📋 Crédito pendiente de aprobación: ${data['message']}');
  // MOSTRAR NOTIFICACIÓN AL MANAGER
});
```

#### 8. `credit_approved` (COBRADOR recibe)
```dart
socket.on('credit_approved', (data) {
  // data: {
  //   message: "Tu crédito de $5000 ha sido aprobado por Gerente (Entrega inmediata: Sí)",
  //   data: {
  //     title: 'Crédito aprobado',
  //     type: 'credit_approved',
  //     credit: { id, amount, entrega_inmediata: true, ... },
  //     manager: { id, name, email },
  //     entrega_inmediata: true
  //   },
  //   timestamp: "2025-10-19T..."
  // }

  print('✅ Crédito aprobado: ${data['message']}');
  bool entregaInmediata = data['data']['entrega_inmediata'] ?? false;
  // MOSTRAR NOTIFICACIÓN AL COBRADOR
  // SI entregaInmediata == true, PERMITIR ENTREGA INMEDIATA
});
```

#### 9. `credit_rejected` (COBRADOR recibe)
```dart
socket.on('credit_rejected', (data) {
  // data: {
  //   message: "Tu crédito de $5000 ha sido rechazado por Gerente",
  //   data: {
  //     title: 'Crédito rechazado',
  //     type: 'credit_rejected',
  //     credit: { id, amount, ... },
  //     manager: { id, name, email }
  //   },
  //   timestamp: "2025-10-19T..."
  // }

  print('❌ Crédito rechazado: ${data['message']}');
  // MOSTRAR NOTIFICACIÓN AL COBRADOR
});
```

#### 10. `credit_delivered` (MANAGER recibe)
```dart
socket.on('credit_delivered', (data) {
  // data: {
  //   message: "El cobrador Juan ha entregado el crédito de $5000",
  //   data: {
  //     title: 'Crédito entregado',
  //     type: 'credit_delivered',
  //     credit: { id, amount, ... },
  //     cobrador: { id, name, email }
  //   },
  //   timestamp: "2025-10-19T..."
  // }

  print('📦 Crédito entregado: ${data['message']}');
  // MOSTRAR NOTIFICACIÓN AL MANAGER
});
```

#### 11. `credit_attention_required` (COBRADOR recibe)
```dart
socket.on('credit_attention_required', (data) {
  // data: {
  //   message: "El crédito de $5000 requiere tu atención",
  //   data: {
  //     title: 'Crédito requiere atención',
  //     type: 'credit_attention',
  //     credit: { id, amount, ... }
  //   },
  //   timestamp: "2025-10-19T..."
  // }

  print('⚠️ Crédito requiere atención: ${data['message']}');
  // MOSTRAR NOTIFICACIÓN AL COBRADOR
});
```

#### 12. `credit_pending_approval` (MANAGER recibe - desde socket)
```dart
socket.on('credit_pending_approval', (data) {
  // Similar a credit_waiting_approval
  // Se emite cuando otro usuario emite 'credit_lifecycle' con action='created'
  print('📋 Crédito pendiente: ${data['message']}');
});
```

#### 13. `credit_decision` (COBRADOR recibe - desde socket)
```dart
socket.on('credit_decision', (data) {
  // data: { action: 'approved' | 'rejected', creditId, credit, message, timestamp, from: {...} }
  print('📊 Decisión de crédito: ${data['action']}');
});
```

#### 14. `credit_delivered_notification` (MANAGER recibe - desde socket)
```dart
socket.on('credit_delivered_notification', (data) {
  print('📦 Notificación de entrega: ${data['message']}');
});
```

#### 15. `credit_lifecycle_update` (Usuario específico recibe)
```dart
socket.on('credit_lifecycle_update', (data) {
  // data: { action, creditId, credit, message, timestamp, from: { id, name, type } }
  print('🔄 Actualización de crédito: ${data['action']}');
});
```

#### 16. `new_credit_notification` (Genérico)
```dart
socket.on('new_credit_notification', (data) {
  print('🆕 Nueva notificación de crédito');
});
```

---

### 💰 **Eventos de Pagos**

#### 17. `payment_received` (COBRADOR recibe)
```dart
socket.on('payment_received', (data) {
  // data: {
  //   message: "Has realizado un pago de $500 de Cliente X",
  //   data: {
  //     title: 'Pago realizado',
  //     type: 'payment_received',
  //     payment: { id, amount, credit_id, status, payment_date },
  //     client: { id, name }
  //   },
  //   timestamp: "2025-10-19T..."
  // }

  print('💵 Pago recibido: ${data['message']}');
  // MOSTRAR NOTIFICACIÓN AL COBRADOR
  // ACTUALIZAR SALDO DEL CRÉDITO
});
```

#### 18. `cobrador_payment_received` (MANAGER recibe)
```dart
socket.on('cobrador_payment_received', (data) {
  // data: {
  //   message: "El cobrador Juan recibió un pago de $500 de Cliente X",
  //   data: {
  //     title: 'Pago de cobrador recibido',
  //     type: 'cobrador_payment_received',
  //     payment: { id, amount, credit_id, status, payment_date },
  //     cobrador: { id, name, email },
  //     client: { id, name }
  //   },
  //   timestamp: "2025-10-19T..."
  // }

  print('💰 Pago de cobrador: ${data['message']}');
  // MOSTRAR NOTIFICACIÓN AL MANAGER
});
```

---

### 📍 **Eventos de Ubicación y Rutas**

#### 19. `cobrador_location_update` (ADMIN recibe)
```dart
socket.on('cobrador_location_update', (data) {
  // data: {
  //   cobradorId: "123",
  //   cobradorName: "Juan",
  //   latitude: -16.5000,
  //   longitude: -68.1500,
  //   timestamp: "2025-10-19T..."
  // }

  print('📍 Ubicación de ${data['cobradorName']}: ${data['latitude']}, ${data['longitude']}');
  // ACTUALIZAR MAPA CON UBICACIÓN DEL COBRADOR
});
```

#### 20. `route_updated` (MANAGER recibe)
```dart
socket.on('route_updated', (data) {
  print('🛣️ Ruta actualizada: $data');
  // REFRESCAR LISTA DE RUTAS
});
```

---

### 💬 **Eventos de Mensajería**

#### 21. `new_message` (Usuario específico recibe)
```dart
socket.on('new_message', (data) {
  // data: {
  //   senderId: "456",
  //   message: "Hola!",
  //   timestamp: "2025-10-19T..."
  // }

  print('💬 Nuevo mensaje de ${data['senderId']}: ${data['message']}');
  // MOSTRAR NOTIFICACIÓN DE MENSAJE
  // ACTUALIZAR CHAT
});
```

---

### ⚙️ **Eventos del Sistema**

#### 22. `server_shutdown`
```dart
socket.on('server_shutdown', (data) {
  // data: { message: "El servidor se está cerrando...", timestamp: "..." }
  print('🔴 Servidor cerrando: ${data['message']}');
  // MOSTRAR ALERTA AL USUARIO
  // INTENTAR RECONECTAR DESPUÉS DE UN TIEMPO
});
```

#### 23. `error`
```dart
socket.on('error', (error) {
  print('❌ Error del socket: $error');
});
```

---

## 🎯 **FLUJOS COMPLETOS POR ROL**

### 👨‍💼 **COBRADOR (Collector)**

#### Eventos a ESCUCHAR:
- ✅ `authenticated` - Confirmación de autenticación
- ✅ `credit_approved` - **MUY IMPORTANTE** - Tu crédito fue aprobado
- ✅ `credit_rejected` - Tu crédito fue rechazado
- ✅ `credit_attention_required` - Crédito necesita atención
- ✅ `payment_received` - Confirmación de pago registrado
- ✅ `new_message` - Mensajes entrantes
- ✅ `credit_decision` - Decisión sobre tu crédito

#### Eventos a ENVIAR:
- 📤 `authenticate` - Al conectar
- 📤 `location_update` - Actualizar ubicación periódicamente
- 📤 `send_message` - Enviar mensajes
- 📤 `credit_lifecycle` - Notificar creación/entrega de créditos
- 📤 `payment_update` - Notificar pagos recibidos

---

### 👔 **MANAGER/GERENTE**

#### Eventos a ESCUCHAR:
- ✅ `authenticated` - Confirmación de autenticación
- ✅ `credit_waiting_approval` - **MUY IMPORTANTE** - Nuevo crédito para aprobar
- ✅ `credit_pending_approval` - Crédito pendiente de aprobación
- ✅ `credit_delivered` - Cobrador entregó un crédito
- ✅ `credit_delivered_notification` - Notificación de entrega
- ✅ `cobrador_payment_received` - Cobrador recibió un pago
- ✅ `cobrador_location_update` - Ubicación de cobradores
- ✅ `route_updated` - Rutas actualizadas
- ✅ `new_message` - Mensajes entrantes

#### Eventos a ENVIAR:
- 📤 `authenticate` - Al conectar
- 📤 `send_message` - Enviar mensajes
- 📤 `credit_lifecycle` - Aprobar/Rechazar créditos

---

### 🔑 **ADMIN**

#### Eventos a ESCUCHAR:
- ✅ Todos los eventos de MANAGER
- ✅ `cobrador_location_update` - Ubicación de todos los cobradores
- ✅ Todos los eventos de créditos y pagos

#### Eventos a ENVIAR:
- 📤 Todos los eventos de MANAGER

---

## 📝 **EJEMPLO COMPLETO EN FLUTTER (COBRADOR)**

```dart
import 'package:socket_io_client/socket_io_client.dart' as IO;

class WebSocketService {
  late IO.Socket socket;
  final String userId;
  final String userType;
  final String userName;

  WebSocketService({
    required this.userId,
    required this.userType,
    required this.userName,
  });

  void connect() {
    socket = IO.io('http://192.168.56.22:3001', <String, dynamic>{
      'transports': ['websocket'],
      'autoConnect': false,
    });

    // Configurar todos los listeners
    _setupListeners();

    // Conectar
    socket.connect();
  }

  void _setupListeners() {
    // Conexión
    socket.on('connect', (_) {
      print('🔌 Conectado al WebSocket');
      _authenticate();
    });

    socket.on('disconnect', (_) {
      print('❌ Desconectado del WebSocket');
    });

    // Autenticación
    socket.on('authenticated', (data) {
      print('✅ Autenticado: $data');
    });

    socket.on('authentication_error', (data) {
      print('❌ Error de autenticación: $data');
    });

    // Créditos (para COBRADOR)
    socket.on('credit_approved', (data) {
      print('✅ Crédito aprobado: ${data['message']}');
      bool entregaInmediata = data['data']['entrega_inmediata'] ?? false;

      // TODO: Mostrar notificación
      // TODO: Si entregaInmediata, navegar a pantalla de entrega
    });

    socket.on('credit_rejected', (data) {
      print('❌ Crédito rechazado: ${data['message']}');
      // TODO: Mostrar notificación
    });

    socket.on('credit_attention_required', (data) {
      print('⚠️ Atención requerida: ${data['message']}');
      // TODO: Mostrar notificación
    });

    // Pagos
    socket.on('payment_received', (data) {
      print('💵 Pago confirmado: ${data['message']}');
      // TODO: Actualizar UI con nuevo pago
    });

    // Mensajes
    socket.on('new_message', (data) {
      print('💬 Nuevo mensaje: ${data['message']}');
      // TODO: Mostrar notificación de mensaje
    });

    // Sistema
    socket.on('server_shutdown', (data) {
      print('🔴 Servidor cerrando: ${data['message']}');
      // TODO: Mostrar alerta y reconectar
    });
  }

  void _authenticate() {
    socket.emit('authenticate', {
      'userId': userId,
      'userType': userType,
      'userName': userName,
    });
  }

  void updateLocation(double latitude, double longitude) {
    socket.emit('location_update', {
      'latitude': latitude,
      'longitude': longitude,
    });
  }

  void sendMessage(String recipientId, String message) {
    socket.emit('send_message', {
      'senderId': userId,
      'recipientId': recipientId,
      'message': message,
    });
  }

  void disconnect() {
    socket.disconnect();
  }
}
```

---

## 🔄 **FLUJO DE CRÉDITO COMPLETO**

```
1. COBRADOR crea crédito en Flutter → Laravel API
                ↓
2. Laravel dispara evento CreditCreated
                ↓
3. Laravel envía a WebSocket Server (POST /credit-notification)
                ↓
4. WebSocket emite 'credit_waiting_approval' → MANAGER
                ↓
5. MANAGER recibe notificación en Flutter
                ↓
6. MANAGER aprueba/rechaza en Flutter → Laravel API
                ↓
7. Laravel dispara evento CreditApproved/CreditRejected
                ↓
8. Laravel envía a WebSocket Server
                ↓
9. WebSocket emite 'credit_approved' o 'credit_rejected' → COBRADOR
                ↓
10. COBRADOR recibe notificación en Flutter
                ↓
11. Si aprobado con entrega_inmediata=true → COBRADOR puede entregar
                ↓
12. COBRADOR entrega crédito → Laravel API
                ↓
13. Laravel dispara evento CreditDelivered
                ↓
14. WebSocket emite 'credit_delivered' → MANAGER
```

---

## 🔄 **FLUJO DE PAGO COMPLETO**

```
1. COBRADOR registra pago en Flutter → Laravel API
                ↓
2. Laravel dispara evento PaymentCreated
                ↓
3. Laravel envía a WebSocket Server (POST /payment-notification)
                ↓
4. WebSocket emite DOS eventos:
   - 'payment_received' → COBRADOR (confirmación)
   - 'cobrador_payment_received' → MANAGER (notificación)
                ↓
5. Ambos reciben la notificación en Flutter
                ↓
6. UI se actualiza con el nuevo pago
```

---

## ⚠️ **NOTAS IMPORTANTES**

1. **SIEMPRE autenticarse** después de conectar con `authenticate`
2. **userId debe ser String**, no int
3. **userType** debe ser: `'cobrador'`, `'manager'`, `'admin'`, o `'client'`
4. Todos los eventos incluyen `timestamp` en formato ISO
5. El servidor WebSocket corre en `http://192.168.56.22:3001`
6. Usar `transports: ['websocket']` para conexión directa
7. **Manejar reconexión** automática en caso de desconexión
8. **No confundir** eventos similares:
   - `credit_waiting_approval` (desde Laravel → Manager)
   - `credit_pending_approval` (desde Socket → Manager)
   - Ambos son para lo mismo, pero de diferentes fuentes

---

## 🛠️ **DEPENDENCIAS FLUTTER**

```yaml
dependencies:
  socket_io_client: ^2.0.3+1
```

---

## 📞 **ENDPOINTS HTTP ADICIONALES** (Opcional)

Si necesitas consultar información sin WebSocket:

- `GET http://192.168.56.22:3001/health` - Estado del servidor
- `GET http://192.168.56.22:3001/active-users` - Usuarios conectados

Estos requieren autenticación con header:
```
x-ws-secret: cobrador-websocket-secret-key-2025
```
