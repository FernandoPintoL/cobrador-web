# 📡 Canales de WebSocket para Flutter - Guía Completa

## 📊 CANALES NUEVOS (Estadísticas en Tiempo Real)

### 1️⃣ `stats.global.updated`

**Cuándo se dispara:**
- Se crea un pago
- Se crea un crédito
- Se aprueba un crédito
- Se entrega un crédito
- Se rechaza un crédito

**Quién lo recibe:**
- ✅ Todos los usuarios conectados (Global/Broadcast)
- ✅ Admin/Manager/Cobrador

**Estructura de datos:**
```dart
{
  "type": "global",
  "stats": {
    "total_clients": 150,           // Total de clientes
    "total_cobradores": 5,          // Total de cobradores
    "total_managers": 2,            // Total de managers
    "total_credits": 45,            // Créditos activos
    "total_payments": 200,          // Total de pagos registrados
    "overdue_payments": 3,          // Pagos atrasados
    "pending_payments": 12,         // Pagos pendientes
    "total_balance": 25000.50,      // Balance total pendiente
    "today_collections": 1200.00,   // Cobros del día de hoy
    "month_collections": 18500.75,  // Cobros del mes actual
    "updated_at": "2025-10-31T14:30:45.000Z"
  },
  "timestamp": "2025-10-31T14:30:45.000Z"
}
```

**Para qué sirve:**
- 📊 Dashboard global/admin
- 📈 Mostrar resumen de la empresa
- 🎯 Métricas principales en tiempo real
- 💰 Actualizar totales de cobros y balance

**Ejemplo de uso:**
```dart
socket.on('stats.global.updated', (data) {
  final stats = GlobalStats.fromJson(data['stats']);

  // Actualizar UI
  setState(() {
    totalClients = stats.totalClients;
    todayCollections = stats.todayCollections;
    totalBalance = stats.totalBalance;
  });
});
```

---

### 2️⃣ `stats.cobrador.updated`

**Cuándo se dispara:**
- Un cobrador crea un pago
- Un cobrador crea un crédito
- Se aprueba crédito de un cobrador
- Se entrega crédito de un cobrador
- Se rechaza crédito de un cobrador

**Quién lo recibe:**
- ✅ El cobrador específico
- ✅ Todos en la sala `cobradors` (para dashboards de managers)
- ❌ Otros cobradores NO lo reciben

**Estructura de datos:**
```dart
{
  "type": "cobrador",
  "user_id": 42,                  // ID del cobrador
  "stats": {
    "cobrador_id": 42,            // ID del cobrador
    "total_clients": 25,          // Clientes del cobrador
    "total_credits": 8,           // Créditos activos del cobrador
    "total_payments": 50,         // Total de pagos
    "overdue_payments": 1,        // Pagos atrasados
    "pending_payments": 3,        // Pagos pendientes
    "total_balance": 5000.00,     // Balance pendiente
    "today_collections": 250.00,  // Cobros hoy de este cobrador
    "month_collections": 3500.75, // Cobros del mes
    "updated_at": "2025-10-31T14:30:45.000Z"
  },
  "timestamp": "2025-10-31T14:30:45.000Z"
}
```

**Para qué sirve:**
- 👤 Dashboard personal del cobrador
- 📊 Mostrar sus propias métricas
- 💰 Cobros del día/mes del cobrador
- 🎯 Balance pendiente personal

**Ejemplo de uso:**
```dart
socket.on('stats.cobrador.updated', (data) {
  final userId = data['user_id'];
  final stats = CobradorStats.fromJson(data['stats']);

  // Si es mi usuario, actualizar mi dashboard
  if (userId == currentUser.id) {
    setState(() {
      myClients = stats.totalClients;
      myTodayCollections = stats.todayCollections;
    });
  }
});
```

---

### 3️⃣ `stats.manager.updated`

**Cuándo se dispara:**
- Un cobrador asignado al manager crea pago/crédito
- Se aprueba/entrega/rechaza crédito de un cobrador del manager

**Quién lo recibe:**
- ✅ El manager específico
- ✅ Todos en la sala `managers`
- ❌ Cobradores NO lo reciben

**Estructura de datos:**
```dart
{
  "type": "manager",
  "user_id": 15,                  // ID del manager
  "stats": {
    "manager_id": 15,             // ID del manager
    "total_cobradores": 5,        // Cobradores bajo este manager
    "total_credits": 40,          // Créditos del equipo
    "total_payments": 180,        // Pagos del equipo
    "overdue_payments": 3,        // Pagos atrasados del equipo
    "pending_payments": 10,       // Pagos pendientes del equipo
    "total_balance": 22000.00,    // Balance total del equipo
    "today_collections": 1000.00, // Cobros del equipo hoy
    "month_collections": 16500.75,// Cobros del equipo mes
    "updated_at": "2025-10-31T14:30:45.000Z"
  },
  "timestamp": "2025-10-31T14:30:45.000Z"
}
```

**Para qué sirve:**
- 👥 Dashboard del manager (equipo completo)
- 📊 Supervisar rendimiento de cobradores
- 💰 Totales del equipo
- 🎯 Métricas de desempeño grupal

**Ejemplo de uso:**
```dart
socket.on('stats.manager.updated', (data) {
  final managerId = data['user_id'];
  final stats = ManagerStats.fromJson(data['stats']);

  // Si soy manager, actualizar dashboard del equipo
  if (managerId == currentUser.id) {
    setState(() {
      teamCollectors = stats.totalCobradores;
      teamBalance = stats.totalBalance;
    });
  }
});
```

---

## 🔔 CANALES EXISTENTES (Para referencia)

### 4️⃣ `credit-notification`

**Cuándo se dispara:**
- Se crea un crédito
- Se aprueba un crédito
- Se rechaza un crédito
- Se entrega un crédito

**Quién lo recibe:**
- ✅ El manager asignado
- ✅ El cobrador que creó
- ✅ El cliente del crédito

**Estructura de datos:**
```dart
{
  "action": "created|approved|rejected|delivered",
  "credit": {
    "id": 123,
    "amount": 5000,
    "total_amount": 5500,
    "balance": 2500,
    "frequency": "weekly",
    "status": "active|pending|rejected|completed",
    "start_date": "2025-10-31",
    "end_date": "2025-11-30",
    "entrega_inmediata": false,
    "scheduled_delivery_date": null,
    "client_name": "Juan Pérez",
    "client_id": 10
  },
  "cobrador": {
    "id": 42,
    "name": "Carlos López",
    "email": "carlos@example.com"
  },
  "manager": {
    "id": 15,
    "name": "María García",
    "email": "maria@example.com"
  }
}
```

**Para qué sirve:**
- 🔔 Notificar cambios de créditos
- 📲 Mostrar popup/toast
- 📝 Actualizar lista de créditos
- 🔊 Sonar alerta (opcional)

**Ejemplo de uso:**
```dart
socket.on('credit-notification', (data) {
  final action = data['action'];
  final credit = data['credit'];

  // Mostrar notificación
  _showNotification(
    title: 'Crédito ${action}',
    message: 'Monto: \$${credit['amount']}',
  );

  // Actualizar lista si estamos en esa pantalla
  if (action == 'delivered') {
    refreshCreditsList();
  }
});
```

---

### 5️⃣ `payment-notification`

**Cuándo se dispara:**
- Se registra un pago

**Quién lo recibe:**
- ✅ El cobrador que recibió el pago
- ✅ El manager del cobrador
- ✅ El cliente que pagó (opcional)

**Estructura de datos:**
```dart
{
  "payment": {
    "id": 456,
    "amount": 500,
    "credit_id": 123,
    "status": "paid|pending|overdue",
    "payment_date": "2025-10-31T14:30:45.000Z"
  },
  "cobrador": {
    "id": 42,
    "name": "Carlos López",
    "email": "carlos@example.com"
  },
  "manager": {
    "id": 15,
    "name": "María García",
    "email": "maria@example.com"
  },
  "client": {
    "id": 10,
    "name": "Juan Pérez"
  }
}
```

**Para qué sirve:**
- 💰 Notificar recepción de pago
- 📲 Actualizar balance pendiente
- 🔔 Alerta de pago recibido
- 📊 Actualizar métricas de cobros

**Ejemplo de uso:**
```dart
socket.on('payment-notification', (data) {
  final payment = data['payment'];

  // Mostrar toast
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text('Pago recibido: \$${payment['amount']}')),
  );

  // Actualizar pantalla de crédito si está visible
  updateCreditBalance(payment['credit_id']);
});
```

---

## 📡 TABLA COMPARATIVA

| Canal | Quién lo recibe | Cuándo | Para qué |
|-------|-----------------|--------|----------|
| `stats.global.updated` | 🌍 Todos | Cambio en stats globales | Dashboard global/admin |
| `stats.cobrador.updated` | 👤 Cobrador específico | Cambio en stats del cobrador | Dashboard personal cobrador |
| `stats.manager.updated` | 👥 Manager específico | Cambio en stats del equipo | Dashboard del manager |
| `credit-notification` | Manager + Cobrador + Cliente | Cambio en crédito | Notificar evento del crédito |
| `payment-notification` | Cobrador + Manager | Se registra pago | Notificar pago recibido |

---

## 🎯 CUÁLES ESCUCHAR POR ROL

### Cobrador
```dart
socket.on('stats.cobrador.updated', handleCobradorStats);  // ✅ NUEVO
socket.on('stats.global.updated', handleGlobalStats);      // ✅ NUEVO
socket.on('credit-notification', handleCreditNotification);
socket.on('payment-notification', handlePaymentNotification);
```

### Manager
```dart
socket.on('stats.manager.updated', handleManagerStats);    // ✅ NUEVO
socket.on('stats.global.updated', handleGlobalStats);      // ✅ NUEVO
socket.on('credit-notification', handleCreditNotification);
socket.on('payment-notification', handlePaymentNotification);
```

### Admin
```dart
socket.on('stats.global.updated', handleGlobalStats);      // ✅ NUEVO
socket.on('credit-notification', handleCreditNotification);
socket.on('payment-notification', handlePaymentNotification);
```

---

## 🚀 IMPLEMENTACIÓN EN FLUTTER

```dart
// lib/services/socket_service.dart
class SocketService {
  void setupAllListeners() {
    // Nuevos canales de estadísticas
    socket.on('stats.global.updated', (data) {
      print('📊 Global stats actualizado');
      _handleGlobalStatsUpdate(data);
    });

    socket.on('stats.cobrador.updated', (data) {
      print('📊 Cobrador stats actualizado');
      _handleCobradorStatsUpdate(data);
    });

    socket.on('stats.manager.updated', (data) {
      print('📊 Manager stats actualizado');
      _handleManagerStatsUpdate(data);
    });

    // Canales existentes
    socket.on('credit-notification', (data) {
      print('🎓 Notificación de crédito');
      _handleCreditNotification(data);
    });

    socket.on('payment-notification', (data) {
      print('💰 Notificación de pago');
      _handlePaymentNotification(data);
    });
  }

  void _handleGlobalStatsUpdate(Map<String, dynamic> data) {
    onGlobalStatsUpdated?.call(data);
  }

  void _handleCobradorStatsUpdate(Map<String, dynamic> data) {
    onCobradorStatsUpdated?.call(data);
  }

  void _handleManagerStatsUpdate(Map<String, dynamic> data) {
    onManagerStatsUpdated?.call(data);
  }

  void _handleCreditNotification(Map<String, dynamic> data) {
    onCreditNotification?.call(data);
  }

  void _handlePaymentNotification(Map<String, dynamic> data) {
    onPaymentNotification?.call(data);
  }
}
```

---

## 📋 CHECKLIST DE IMPLEMENTACIÓN

- [ ] Escuchar `stats.global.updated`
- [ ] Escuchar `stats.cobrador.updated`
- [ ] Escuchar `stats.manager.updated`
- [ ] Crear modelos para parsear datos
- [ ] Crear Provider/Controller para estado
- [ ] Actualizar UI cuando llegan eventos
- [ ] Manejar desconexiones
- [ ] Agregar logging para debugging
- [ ] Probar con datos reales

---

## 🐛 DEBUGGING

```dart
// Activar logs en socket.io
socket.onConnect((_) {
  print('✅ Conectado');
});

socket.on('stats.global.updated', (data) {
  print('📊 Global stats: ${jsonEncode(data)}');
});

socket.onError((error) {
  print('❌ Error: $error');
});

socket.onDisconnect((_) {
  print('❌ Desconectado');
});
```

---

## 🔧 TROUBLESHOOTING

**Problema: No recibo eventos**
- ✅ Verificar que socket esté conectado
- ✅ Revisar logs del WebSocket server
- ✅ Confirmar que el usuario tiene permiso para el rol

**Problema: Recibo eventos de otros usuarios**
- ✅ Verificar user_id en el evento
- ✅ Comparar con currentUser.id antes de usar

**Problema: Stats no se actualizan**
- ✅ Asegurar que `notifyListeners()` se ejecuta en Provider
- ✅ Verificar que el listener está registrado
- ✅ Revisar que el job en Laravel se ejecutó

---

¡Listo! Ya tienes la guía completa de todos los canales 🎉
