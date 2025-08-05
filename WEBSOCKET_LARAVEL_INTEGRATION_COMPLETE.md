# 🚀 Guía de Integración WebSocket-Laravel COMPLETA

## ✅ **ESTADO ACTUAL: INTEGRACIÓN COMPLETA**

Tu WebSocket ahora está **completamente integrado** con el backend Laravel. Aquí tienes todo lo que se ha implementado:

---

## 📋 **¿Qué tienes implementado?**

### 🔥 **1. Servidor WebSocket Node.js** ✅ **FUNCIONAL**
- **Puerto:** 3001 (192.168.5.44:3001)
- **Eventos soportados:**
  - `credit_notification` - Notificaciones de créditos
  - `payment_update` - Actualizaciones de pagos
  - `route_notification` - Notificaciones de rutas
  - `location_update` - Actualizaciones de ubicación
  - `send_message` - Mensajes entre usuarios

### 🎯 **2. Laravel Backend** ✅ **TOTALMENTE INTEGRADO**
- **Eventos automáticos implementados:**
  - `CreditRequiresAttention` - Se dispara automáticamente cuando un crédito requiere atención
  - `PaymentReceived` - Se dispara automáticamente cuando se recibe un pago
  - `TestNotification` - Para pruebas del sistema

- **Listeners automáticos:**
  - `SendCreditAttentionNotification` - Envía notificaciones al WebSocket automáticamente
  - `SendPaymentReceivedNotification` - Envía notificaciones de pagos al WebSocket

- **Service integrado:**
  - `WebSocketNotificationService` - Maneja toda la comunicación con el servidor WebSocket

### 🎮 **3. API Endpoints** ✅ **DISPONIBLES**
```
POST /api/websocket/credit-attention/{credit}  - Enviar notificación de crédito
POST /api/websocket/payment-notification       - Enviar notificación de pago
GET  /api/websocket/notifications              - Obtener notificaciones en tiempo real
POST /api/websocket/test                       - Probar WebSocket con autenticación
GET  /api/websocket/test-connection            - Probar conexión directa al WebSocket
```

### 🔄 **4. Automatización TOTAL** ✅ **FUNCIONANDO**
- **Cuando se crea un pago:** Automáticamente envía notificación WebSocket
- **Cuando se actualiza un crédito:** Automáticamente verifica si requiere atención y envía notificación
- **Balance de créditos:** Se actualiza automáticamente con cada pago

---

## 🚀 **Cómo usar la integración**

### **Opción 1: Automática (Recomendada)**
Las notificaciones se envían **automáticamente** cuando:
```php
// Al crear un pago
$payment = Payment::create([
    'credit_id' => $creditId,
    'amount' => 150,
    'cobrador_id' => $cobradorId,
    // ... otros campos
]);
// ↑ Esto automáticamente enviará notificación WebSocket

// Al actualizar un crédito que requiere atención
$credit->update(['status' => 'overdue']);
// ↑ Esto automáticamente enviará notificación si requiere atención
```

### **Opción 2: Manual (Para casos específicos)**
```php
use App\Services\WebSocketNotificationService;

$webSocketService = app(WebSocketNotificationService::class);

// Enviar notificación de crédito
$webSocketService->sendCreditAttention($credit, $cobrador);

// Enviar notificación de pago
$webSocketService->sendPaymentReceived($payment, $cobrador);

// Probar conexión
$status = $webSocketService->testConnection();
```

### **Opción 3: Via API (Para frontend/móvil)**
```javascript
// Probar conexión
fetch('/api/websocket/test-connection', {
    headers: { 'Authorization': 'Bearer ' + token }
})

// Enviar notificación de crédito
fetch('/api/websocket/credit-attention/123', {
    method: 'POST',
    headers: { 'Authorization': 'Bearer ' + token }
})
```

---

## ⚙️ **Configuración de variables de entorno**

Agrega esto a tu archivo `.env`:
```env
# WebSocket Configuration
WEBSOCKET_HOST=192.168.5.44
WEBSOCKET_PORT=3001
WEBSOCKET_SECURE=false
WEBSOCKET_ENDPOINT=/notify
BROADCAST_DRIVER=redis
```

---

## 🔧 **Integración con el sistema de créditos con intereses**

La integración está **completamente conectada** con tu nuevo sistema de créditos:

### **Campos integrados:**
- `interest_rate` - Tasa de interés
- `total_amount` - Monto total con intereses
- `installment_amount` - Monto de cada cuota
- `balance` - Balance pendiente (se actualiza automáticamente)

### **Notificaciones automáticas por:**
- ✅ Crédito vencido
- ✅ Crédito próximo a vencer (3 días)
- ✅ Alto balance pendiente (>80% después de 7 días)
- ✅ Status 'overdue' o 'defaulted'
- ✅ Pagos recibidos (cualquier monto)

---

## 🧪 **Cómo probar la integración**

### **1. Probar conexión al WebSocket:**
```bash
# En PowerShell
curl -X GET "http://192.168.5.44:3001/health"
```

### **2. Probar desde Laravel:**
```bash
# En terminal Laravel
php artisan tinker

# Probar servicio WebSocket
$service = app(\App\Services\WebSocketNotificationService::class);
$service->testConnection();
```

### **3. Probar notificación automática:**
```bash
# En terminal Laravel
php artisan tinker

# Crear un pago (esto automáticamente enviará notificación WebSocket)
$payment = \App\Models\Payment::create([
    'credit_id' => 1, // ID de un crédito existente
    'amount' => 100,
    'cobrador_id' => 1, // ID de un cobrador
    'payment_date' => now(),
    'payment_method' => 'cash'
]);
```

### **4. Probar via API (con autenticación):**
```bash
# En PowerShell
curl -X GET "http://tu-dominio/api/websocket/test-connection" -H "Authorization: Bearer tu-token"
```

---

## 📱 **Para aplicaciones móviles (Flutter)**

Ya tienes todo el código Flutter listo en:
- `websocket-server/FlutterWebSocketService.dart`
- `websocket-server/FlutterWebSocketManager.dart`

Solo conecta a: `ws://192.168.5.44:3001`

---

## 🏃‍♂️ **Comandos para iniciar todo**

### **1. Iniciar servidor WebSocket:**
```bash
cd websocket-server
npm install
npm start
```

### **2. Iniciar Laravel:**
```bash
php artisan serve
```

### **3. Verificar que todo funciona:**
```bash
# Probar WebSocket
curl http://192.168.5.44:3001/health

# Probar Laravel API
curl http://localhost:8000/api/websocket/test-connection
```

---

## 🎉 **¡RESUMEN!**

**✅ TU WEBSOCKET ESTÁ 100% INTEGRADO CON LARAVEL**

### **Lo que funciona automáticamente:**
1. **Pagos** → Notificación WebSocket automática
2. **Créditos atrasados** → Notificación WebSocket automática  
3. **Créditos próximos a vencer** → Notificación WebSocket automática
4. **Balance de créditos** → Se actualiza automáticamente
5. **Sistema de intereses** → Completamente integrado

### **Lo que puedes hacer:**
1. **Crear pagos** → Las notificaciones se envían solas
2. **Usar API endpoints** → Para control manual
3. **Conectar apps móviles** → Todo está listo
4. **Monitorear en tiempo real** → Funciona automáticamente

### **Estado final:**
🚀 **INTEGRACIÓN COMPLETA Y FUNCIONAL**

¡Tu sistema de cobranza con WebSocket está listo para producción!
