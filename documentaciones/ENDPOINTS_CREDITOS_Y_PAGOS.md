# Guía de Endpoints: Créditos y Pagos

Fecha: 2025-08-24

Este documento describe cómo usar los endpoints REST para gestionar Créditos y Pagos en la API. Incluye autenticación, permisos, parámetros, ejemplos y notas importantes (incluida geolocalización).

## 1) Autenticación
- La mayoría de endpoints están protegidos por `auth:sanctum`.
- Obtén un token con `/api/login` y envíalo en el header Authorization en cada request.

Headers comunes:
- `Authorization: Bearer <TOKEN>`
- `Accept: application/json`
- `Content-Type: application/json`

Ejemplo login (POST /api/login):
```json
{
  "email": "usuario@ejemplo.com",
  "password": "secret"
}
```
La respuesta incluye un token. Úsalo en los siguientes llamados.

## 2) Roles y permisos relevantes
- Cobrador:
  - Puede listar y crear créditos solo para sus clientes asignados.
  - Puede registrar pagos de créditos de sus clientes asignados.
- Manager:
  - Puede gestionar créditos de clientes asignados directamente a él y de clientes de cobradores bajo su supervisión.
  - Puede usar el flujo de “lista de espera” (aprobación, entrega, reprogramación).
- Admin:
  - Acceso amplio (salvo restricciones de negocio específicas).

Reglas de negocio clave:
- Un cobrado no puede crear créditos para clientes que no le están asignados.
- Al crear pagos:
  - El crédito debe estar en estado `active`.
  - El monto del pago no puede exceder el balance pendiente del crédito.

## 3) Geolocalización (latitude/longitude)
- Créditos: se pueden enviar `latitude` y `longitude` al crearlos; se guardan en la entidad del crédito.
- Pagos: se pueden enviar `latitude` y `longitude` al registrarlos; se guardan en la entidad del pago.
- Rangos válidos:
  - `latitude`: -90 a 90
  - `longitude`: -180 a 180

## 4) Endpoints de Créditos
Base: `/api/credits`

- GET `/api/credits`
  - Lista paginada de créditos.
  - Filtros (query params):
    - `client_id` (int)
    - `status` (string: pending_approval, waiting_delivery, active, completed, defaulted, cancelled)
    - `search` (string: busca por nombre del cliente)
    - `cobrador_id` (int; solo admin/manager)
  - Respuesta: paginada con `data`, `links`, `meta` y envoltura estándar de la API.

- POST `/api/credits`
  - Crea un crédito.
  - Body (JSON) requerido y opcional:
    - `client_id` (required, exists: users)
    - `cobrador_id` (optional para managers/admin)
    - `amount` (required, numeric)
    - `balance` (required, numeric)
    - `frequency` (required, enum: daily, weekly, biweekly, monthly)
    - `start_date` (required, date: YYYY-MM-DD)
    - `end_date` (required, date > start_date)
    - `status` (optional; si no se envía puede entrar como pending_approval según el rol)
    - `scheduled_delivery_date` (optional, datetime futura; managers)
    - `interest_rate_id` o `interest_rate` (optional)
    - `total_amount`, `installment_amount` (opcionales; se autocalculan si aplica)
    - `total_installments` (opcional, entero >= 1; por defecto 24 si no se envía)
    - `latitude`, `longitude` (opcionales, ver rangos)
  - Notas de permisos:
    - Cobrador solo para sus clientes y sin cambiar `cobrador_id` (debe ser él mismo).
    - Manager puede crear para clientes directos o de cobradores bajo su supervisión; si envía `cobrador_id`, debe ser de los suyos y el cliente debe estar asignado a ese cobrador.
  - Respuesta: objeto `credit` creado con mensaje. Si entra a lista de espera, el mensaje lo indica.

- GET `/api/credits/{credit}`
  - Obtiene detalle de un crédito.

- PUT/PATCH `/api/credits/{credit}`
  - Actualiza un crédito. Campos según negocio (ver modelo/controlador para restricciones vigentes en tu despliegue).

- DELETE `/api/credits/{credit}`
  - Elimina un crédito (según permisos/negocio).

- GET `/api/credits/{credit}/remaining-installments`
  - Devuelve número de cuotas restantes.

- GET `/api/credits/client/{client}`
  - Créditos por cliente.

- GET `/api/credits/cobrador/{cobrador}`
  - Créditos por cobrador.

- GET `/api/credits/cobrador/{cobrador}/stats`
  - Estadísticas por cobrador.

- GET `/api/credits/manager/{manager}/stats`
  - Estadísticas por manager.

- GET `/api/credits-requiring-attention`
  - Créditos que requieren atención (vencidos, alto balance, próximos a vencer, etc.).

### 4.1) Sistema de Lista de Espera (Waiting List)
- POST `/api/credits/waiting-list`
  - Crea un crédito directamente en “lista de espera” (para managers). Enviar campos como en `POST /api/credits` pertinentes.

Listado/sumarizaciones:
- GET `/api/credits/waiting-list/pending-approval`
- GET `/api/credits/waiting-list/waiting-delivery`
- GET `/api/credits/waiting-list/ready-today`
- GET `/api/credits/waiting-list/overdue-delivery`
- GET `/api/credits/waiting-list/summary`

Acciones por crédito:
- POST `/api/credits/{credit}/waiting-list/approve`
- POST `/api/credits/{credit}/waiting-list/reject`
- POST `/api/credits/{credit}/waiting-list/deliver`
- POST `/api/credits/{credit}/waiting-list/reschedule`
- GET  `/api/credits/{credit}/waiting-list/status`

### 4.2) Gestión avanzada de un crédito
Prefijo: `/api/credits/{credit}`
- GET `/details`
- POST `/simulate-payment`
- GET `/payment-schedule`

## 5) Endpoints de Pagos
Base: `/api/payments`

- GET `/api/payments`
  - Lista paginada de pagos.
  - Filtros (query params):
    - `credit_id` (int)
    - `received_by` (int)
    - `payment_method` (cash, transfer, check, other)
    - `date_from` (YYYY-MM-DD)
    - `date_to` (YYYY-MM-DD)
    - `amount_min` (number)
    - `amount_max` (number)
  - Regla por rol:
    - Cobrador verá solo sus pagos.
    - Manager verá pagos de sus cobradores.

- POST `/api/payments`
  - Registra un pago.
  - Body (JSON):
    - `credit_id` (required)
    - `amount` (required, > 0)
    - `payment_method` (required: cash, transfer, check, other)
    - `payment_date` (required, date/datetime; ej: 2025-08-24 10:30:00)
    - `installment_number` (optional)
    - `latitude`, `longitude` (opcionales; se guardan en el pago)
  - Reglas de negocio:
    - El crédito debe estar en `active`.
    - `amount` no puede ser mayor al `balance` del crédito.
    - Cobrador solo puede registrar pago de clientes asignados.
  - Efectos:
    - Se descuenta el `balance` del crédito.
    - Si el balance llega a 0, el crédito puede marcarse `completed`.

- GET `/api/payments/{payment}`
  - Detalle del pago (cobrador solo si es quien lo recibió).

- PUT/PATCH `/api/payments/{payment}`
  - Actualiza un pago (monto, método, fecha, notas). Ajusta el balance del crédito según diferencia de montos.

- DELETE `/api/payments/{payment}`
  - Elimina/cancela un pago (reglas según negocio en tu despliegue; revisar controlador si aplican restricciones adicionales).

Listados auxiliares:
- GET `/api/payments/credit/{credit}`
- GET `/api/payments/cobrador/{cobrador}`
- GET `/api/payments/cobrador/{cobrador}/stats`
- GET `/api/payments/recent`
- GET `/api/payments/today-summary`

## 6) Ejemplos prácticos

### 6.1) Crear un crédito (con geolocalización opcional)
Request:
```
POST /api/credits
Authorization: Bearer <TOKEN>
Content-Type: application/json

{
  "client_id": 123,
  "amount": 1000.00,
  "balance": 1000.00,
  "frequency": "weekly",
  "start_date": "2025-08-25",
  "end_date": "2025-12-25",
  "interest_rate_id": 1,
  "scheduled_delivery_date": "2025-08-26 09:00:00",
  "latitude": -17.7833,
  "longitude": -63.1821
}
```
Respuesta (resumen):
```json
{
  "success": true,
  "data": { "id": 456, "client_id": 123, "status": "pending_approval", "latitude": -17.7833, "longitude": -63.1821, "...": "..." },
  "message": "Crédito creado exitosamente (en lista de espera para aprobación)"
}
```

### 6.2) Registrar un pago (con geolocalización opcional)
Request:
```
POST /api/payments
Authorization: Bearer <TOKEN>
Content-Type: application/json

{
  "credit_id": 456,
  "amount": 100.00,
  "payment_method": "cash",
  "payment_date": "2025-08-24 10:30:00",
  "installment_number": 1,
  "latitude": -17.785,
  "longitude": -63.180
}
```
Respuesta (resumen):
```json
{
  "success": true,
  "data": { "id": 789, "credit_id": 456, "amount": 100.0, "received_by": 22, "latitude": -17.785, "longitude": -63.18, "...": "..." },
  "message": "Pago registrado exitosamente"
}
```

### 6.3) Listar pagos de un crédito
```
GET /api/payments/credit/456
Authorization: Bearer <TOKEN>
```

### 6.4) Ver calendario de pagos estimado de un crédito
```
GET /api/credits/456/payment-schedule
Authorization: Bearer <TOKEN>
```

## 7) Errores comunes
- 401 Unauthorized: faltan credenciales o token inválido.
- 403 Forbidden: violación de reglas de rol/asignación (ej. cobrador intentando actuar sobre cliente no asignado).
- 404 Not Found: entidad inexistente o no accesible.
- 422 Unprocessable Entity: validación fallida (campos requeridos, rangos de geolocalización, etc.).
- 400 Bad Request: violaciones de negocio (p.ej. pago mayor al balance o crédito no activo).

## 8) Notas adicionales
- Paginación: las listas principales son paginadas (15 por defecto). Usa `?page=2` y revisa `meta`/`links`.
- Estructura de respuesta: los controladores usan un envoltorio estándar `success`, `data`, `message` (puede variar según implementación exacta de BaseController).
- Tiempos y formatos: usar `YYYY-MM-DD` para fechas y `YYYY-MM-DD HH:mm:ss` para datetimes cuando aplique.
- Cambios futuros: valida en `routes/api.php` por si nuevos endpoints o cambios de nombres han sido añadidos.

---
Cualquier duda o si necesitas ejemplos adicionales (cURL/Postman) para casos específicos, indícalo y los añadimos a este documento.
