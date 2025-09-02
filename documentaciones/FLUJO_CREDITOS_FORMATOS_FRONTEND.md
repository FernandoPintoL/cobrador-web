# Flujo de Créditos: Qué debe enviar el Frontend en cada paso (Flutter y React)

Fecha: 2025-09-02 00:55

Objetivo: Entregar al equipo frontend un contrato claro de qué datos enviar al backend y qué respuesta esperar en cada etapa del ciclo de vida del crédito. Incluye ejemplos listos para usar (cURL y JSON) y notas para WebSocket/Socket.IO.

---

## 1) Resumen del Flujo

Estados: pending_approval → waiting_delivery → active → completed

Pasos y responsables:
- Crear crédito (cobrador o manager)
- Aprobar/Rechazar (manager o admin)
- Entregar (cobrador o manager)
- Pagos (cobrador)

Cada paso dispara notificaciones en tiempo real vía Node WebSocket:
- created → manager recibe credit_waiting_approval
- approved → cobrador recibe credit_approved
- rejected → cobrador recibe credit_rejected
- delivered → manager recibe credit_delivered
- payment → cobrador recibe payment_received y manager recibe cobrador_payment_received

---

## 2) Crear crédito

Endpoint: POST /api/credits
Roles: cobrador, manager, admin

Body mínimo (cobrador):
{
  "client_id": 123,
  "amount": 1000,
  "balance": 1000,
  "frequency": "daily",
  "start_date": "2025-09-02",
  "end_date": "2025-10-02",
  "immediate_delivery_requested": true
}

Notas:
- Los cobradores SIEMPRE crean con status= pending_approval (lo fija el backend).
- immediate_delivery_requested registra la intención del cobrador; la entrega inmediata real la decide el manager en la aprobación.
- Managers pueden opcionalmente pasar scheduled_delivery_date (mismo día permitido) y, si el cliente es directo del manager, se hace fast‑track a waiting_delivery con approved_by/approved_at automáticos.

Respuesta típica (cobrador):
{
  "success": true,
  "data": {
    "id": 456,
    "status": "pending_approval",
    "immediate_delivery_requested": true,
    "scheduled_delivery_date": null,
    "client": {"id": 123, "name": "Cliente X"},
    "created_by": {"id": 33, "name": "Cobrador"}
  },
  "message": "Crédito creado exitosamente (en lista de espera para aprobación)"
}

---

## 3) Aprobación del crédito

Endpoint: POST /api/credits/{credit}/waiting-list/approve
Roles: manager, admin

Cuerpo – tres escenarios admitidos:
A) Inmediata ahora:
{
  "immediate_delivery": true,
  "notes": "Entregar hoy por urgencia"
}

B) Con fecha específica:
{
  "scheduled_delivery_date": "2025-09-05 10:00",
  "notes": "Entregar en visita programada"
}

C) Sin fecha y no inmediata → por defecto mañana:
{
  "immediate_delivery": false
}

Reglas que aplica el backend al aprobar:
- scheduled_delivery_date =
  - now() si immediate_delivery=true,
  - fecha enviada si viene,
  - mañana si no se envía fecha y no es inmediata.
- start_date del cronograma = (approved_at + 1 día)
- end_date se ajusta automáticamente si no es posterior a start_date (fallback +30 días)
- Si scheduled_delivery_date <= now() o immediate=true → se entrega de inmediato (status active).

Respuesta típica:
- Solo aprobado (no inmediato): status waiting_delivery y delivery_status detallado.
- Aprobado + entregado (inmediato): status active.

---

## 4) Rechazo del crédito

Endpoint: POST /api/credits/{credit}/waiting-list/reject
Roles: manager, admin

Body:
{
  "reason": "Cliente excede límites de categoría B"
}

Respuesta:
{
  "success": true,
  "data": { "credit": { "status": "rejected", "rejection_reason": "..." } },
  "message": "Crédito rechazado exitosamente"
}

Efectos:
- Notificación en tiempo real al cobrador (credit_rejected) vía WebSocket Node.
- Persistencia del motivo en la BD.

---

## 5) Entrega manual (cuando quedó programada para futuro)

Endpoint: POST /api/credits/{credit}/waiting-list/deliver
Roles: cobrador, manager, admin

Body:
{
  "notes": "Entrega en tienda del cliente"
}

Respuesta:
{
  "success": true,
  "data": { "credit": { "status": "active", "delivered_at": "..." }, "delivery_status": {...} },
  "message": "Crédito entregado al cliente exitosamente"
}

Efectos:
- Notificación al manager (credit_delivered).

---

## 6) Pagos

Endpoint: POST /api/payments
Roles: cobrador, manager, admin (validaciones por relación)

Body mínimo:
{
  "credit_id": 456,
  "amount": 50.00,
  "payment_method": "cash",
  "payment_date": "2025-09-02"
}

Validaciones relevantes:
- El crédito debe estar active.
- El cobrador solo registra pagos de sus clientes asignados.
- amount no puede exceder balance.

Respuesta:
{
  "success": true,
  "data": {
    "id": 999,
    "status": "completed",
    "credit": {"id":456, "balance": 950.00, "status": "active"}
  },
  "message": "Pago registrado exitosamente"
}

Notificaciones:
- payment_received → cobrador
- cobrador_payment_received → manager del cobrador

---

## 7) Listas de Espera útiles para UI

- GET /api/credits/waiting-list/pending-approval
- GET /api/credits/waiting-list/waiting-delivery
- GET /api/credits/waiting-list/ready-today
- GET /api/credits/waiting-list/overdue-delivery
- GET /api/credits/waiting-list/summary

Todas devuelven estructuras amigables para pintar dashboards y tarjetas de acción.

---

## 8) Conexión WebSocket (Socket.IO)

- URL: http://{WEBSOCKET_HOST}:{PORT}
- Tras conectar, emitir authenticate con:
{
  "userId": 33,
  "userType": "cobrador|manager|admin|client",
  "userName": "Nombre Apellido"
}

Eventos a escuchar según rol:
- Cobrador: credit_approved, credit_rejected, payment_received
- Manager: credit_waiting_approval, credit_delivered, cobrador_payment_received

Payloads típicos emitidos por Node incluyen: { title, message, type, credit|payment, cobrador|manager|client }

---

## 9) Errores comunes y cómo evitarlos

- 422 al crear: revisa límites por categoría del cliente y número de créditos en curso (A/B/C).
- 403 en acciones: validar que el usuario tiene el rol y relación correcta (cliente asignado, cobrador del manager, etc.).
- 400 en pagos: crédito no active o amount > balance.
- Notificaciones no llegan: confirmar authenticate en socket, CORS permitidos y que el Node server está vivo (/health).

---

## 10) Apéndice: Esquemas de Request por Rol

Cobrador crea crédito:
{
  "client_id": 1,
  "amount": 800,
  "balance": 800,
  "frequency": "daily",
  "start_date": "2025-09-02",
  "end_date": "2025-10-02",
  "immediate_delivery_requested": false
}

Manager crea crédito fast‑track (cliente directo):
{
  "client_id": 1,
  "cobrador_id": 33,
  "amount": 1500,
  "balance": 1500,
  "frequency": "weekly",
  "start_date": "2025-09-02",
  "end_date": "2025-12-02",
  "scheduled_delivery_date": "2025-09-02 12:00"
}

---

Referencias:
- app/Http/Controllers/Api/CreditController.php
- app/Http/Controllers/Api/CreditWaitingListController.php
- app/Http/Controllers/Api/PaymentController.php
- app/Listeners/SendCreditWaitingListNotification.php
- app/Listeners/SendPaymentReceivedNotification.php
- websocket-server/server.js
- documentaciones/ARQUITECTURA_COMUNICACION_BACKEND_FLUTTER_WEBSOCKET.md
- FORMATOS_FRONTEND_BACKEND_CREDITOS.md (consultas y listados)
