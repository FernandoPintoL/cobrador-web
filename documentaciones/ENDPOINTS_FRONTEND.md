# ENDPOINTS PARA FRONTEND - API COBRADOR

## Base URL: `http://192.168.5.44:8000/api`

## 🔐 AUTENTICACIÓN

### Registro de Usuario
**POST** `/register`
```json
{
    "name": "string",
    "email": "string",
    "password": "string",
    "password_confirmation": "string",
    "phone": "string",
    "address": "string",
    "role": "client|cobrador|manager"
}
```

### Login
**POST** `/login`
```json
{
    "email": "string",
    "password": "string"
}
```

### Logout
**POST** `/logout`
Headers: `Authorization: Bearer {token}`

### Obtener Usuario Actual
**GET** `/me`
Headers: `Authorization: Bearer {token}`

### Verificar si Usuario Existe
**POST** `/check-exists`
```json
{
    "email": "string"
}
```

---

## 👥 USUARIOS

### Listar Usuarios
**GET** `/users`
Headers: `Authorization: Bearer {token}`

### Obtener Usuario por ID
**GET** `/users/{id}`
Headers: `Authorization: Bearer {token}`

### Crear Usuario
**POST** `/users`
Headers: `Authorization: Bearer {token}`
```json
{
    "name": "string",
    "email": "string",
    "password": "string",
    "phone": "string",
    "address": "string"
}
```

### Actualizar Usuario
**PUT** `/users/{id}`
Headers: `Authorization: Bearer {token}`
```json
{
    "name": "string",
    "email": "string",
    "phone": "string",
    "address": "string"
}
```

### Subir Imagen de Perfil
**POST** `/users/{id}/profile-image`
Headers: `Authorization: Bearer {token}`, `Content-Type: multipart/form-data`
```
profile_image: file
```

### Obtener Usuarios por Roles
**GET** `/users/by-roles?roles[]=client&roles[]=cobrador`
Headers: `Authorization: Bearer {token}`

---

## 💳 CRÉDITOS

### Listar Créditos
**GET** `/credits`
Headers: `Authorization: Bearer {token}`
Query params: `?client_id=1&status=active&page=1`

### Crear Crédito
**POST** `/credits`
Headers: `Authorization: Bearer {token}`
```json
{
    "client_id": 1,
    "amount": 1000.00,
    "interest_rate": 15.5,
    "installments": 12,
    "start_date": "2025-01-01",
    "description": "string"
}
```

### Obtener Crédito por ID
**GET** `/credits/{id}`
Headers: `Authorization: Bearer {token}`

### Actualizar Crédito
**PUT** `/credits/{id}`
Headers: `Authorization: Bearer {token}`
```json
{
    "amount": 1200.00,
    "interest_rate": 16.0,
    "installments": 10,
    "description": "string actualizada"
}
```

### Eliminar Crédito
**DELETE** `/credits/{id}`
Headers: `Authorization: Bearer {token}`

### Créditos por Cliente
**GET** `/credits/client/{client_id}`
Headers: `Authorization: Bearer {token}`

### Créditos por Cobrador
**GET** `/credits/cobrador/{cobrador_id}`
Headers: `Authorization: Bearer {token}`

### Estadísticas de Cobrador
**GET** `/credits/cobrador/{cobrador_id}/stats`
Headers: `Authorization: Bearer {token}`

### Estadísticas de Manager
**GET** `/credits/manager/{manager_id}/stats`
Headers: `Authorization: Bearer {token}`

### Cuotas Restantes de Crédito
**GET** `/credits/{id}/remaining-installments`
Headers: `Authorization: Bearer {token}`

---

## 💰 PAGOS

### Listar Pagos
**GET** `/payments`
Headers: `Authorization: Bearer {token}`
Query params: `?credit_id=1&payment_method=cash&date_from=2025-01-01&date_to=2025-12-31`

### Crear Pago ⭐ (El que usas en Flutter)
**POST** `/payments`
Headers: `Authorization: Bearer {token}`
```json
{
    "credit_id": 2,
    "amount": 250.0,
    "payment_method": "cash",
    "payment_date": "2025-08-17",
    "installment_number": 1
}
```

### Obtener Pago por ID
**GET** `/payments/{id}`
Headers: `Authorization: Bearer {token}`

### Actualizar Pago
**PUT** `/payments/{id}`
Headers: `Authorization: Bearer {token}`
```json
{
    "amount": 300.0,
    "payment_method": "transfer",
    "payment_date": "2025-08-17",
    "notes": "Pago actualizado"
}
```

### Eliminar Pago (Solo Admin)
**DELETE** `/payments/{id}`
Headers: `Authorization: Bearer {token}`

### Pagos por Crédito
**GET** `/payments/credit/{credit_id}`
Headers: `Authorization: Bearer {token}`

### Pagos por Cobrador
**GET** `/payments/cobrador/{cobrador_id}`
Headers: `Authorization: Bearer {token}`

---

## 📊 DASHBOARD Y ESTADÍSTICAS

### Dashboard Principal
**GET** `/dashboard`
Headers: `Authorization: Bearer {token}`

### Estadísticas Generales
**GET** `/dashboard/stats`
Headers: `Authorization: Bearer {token}`

---

## 🔔 NOTIFICACIONES

### Listar Notificaciones
**GET** `/notifications`
Headers: `Authorization: Bearer {token}`

### Marcar Notificación como Leída
**PUT** `/notifications/{id}/read`
Headers: `Authorization: Bearer {token}`

### Marcar Todas como Leídas
**POST** `/notifications/mark-all-read`
Headers: `Authorization: Bearer {token}`

---

## 📍 UBICACIÓN Y MAPAS

### Guardar Ubicación
**POST** `/location/save`
Headers: `Authorization: Bearer {token}`
```json
{
    "latitude": -12.0464,
    "longitude": -77.0428,
    "type": "payment"
}
```

### Obtener Ubicaciones
**GET** `/locations`
Headers: `Authorization: Bearer {token}`

---

## 🏦 BALANCE DE CAJA

### Balance Actual
**GET** `/cash-balance`
Headers: `Authorization: Bearer {token}`

### Actualizar Balance
**POST** `/cash-balance`
Headers: `Authorization: Bearer {token}`
```json
{
    "amount": 1500.00,
    "description": "Depósito inicial"
}
```

---

## 🔄 WEBSOCKETS Y TIEMPO REAL

### Conexión WebSocket
**URL**: `ws://192.168.5.44:8000/ws/{user_id}`
Headers: `Authorization: Bearer {token}`

### Enviar Notificación Manual
**POST** `/websocket/notify`
Headers: `Authorization: Bearer {token}`
```json
{
    "user_id": 1,
    "message": "Nueva notificación",
    "type": "payment_received"
}
```

---

## ⚠️ IMPORTANTE PARA TU ERROR

El error que tenías ya está **SOLUCIONADO**. El problema era:

1. ❌ **Error anterior**: El modelo `Payment` no tenía la relación `receivedBy`
2. ✅ **Solucionado**: Agregué la relación `receivedBy` al modelo
3. ✅ **Solucionado**: Agregué el campo `received_by` a la base de datos
4. ✅ **Solucionado**: Las migraciones se ejecutaron exitosamente

### Tu endpoint de crear pago ahora funciona correctamente:

**POST** `http://192.168.5.44:8000/api/payments`
```json
{
    "credit_id": 2,
    "amount": 250.0,
    "payment_method": "cash",
    "payment_date": "2025-08-17",
    "installment_number": 1
}
```

**Nota**: Ya no necesitas enviar `client_id: 0` desde el frontend, el sistema automáticamente obtiene el client_id del crédito.

¡El sistema está listo para procesar pagos sin errores!
