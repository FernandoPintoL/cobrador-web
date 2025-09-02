# Documentación de API de Notificaciones (Frontend)

Todas las rutas están protegidas con Sanctum. Incluye los headers en cada petición:

- Authorization: Bearer {token}
- Accept: application/json
- Content-Type: application/json (para peticiones con cuerpo)

Base URL: http://192.168.100.21:8000

## Entidad: Notification
- id: integer
- user_id: integer (requerido)
- payment_id: integer | null
- type: string (enum)
- message: string
- status: string (enum: unread, read, archived)
- created_at: datetime
- updated_at: datetime

Relaciones incluidas en respuestas:
- user: objeto User
- payment: objeto Payment | null

### Tipos permitidos
- En creación (store): payment_received, payment_due, credit_approved, credit_rejected, system_alert, cobrador_payment_received
- En actualización (update): payment_received, payment_due, credit_approved, credit_rejected, system_alert, cobrador_payment_received

### Estados permitidos
- unread, read, archived

## Rutas

### Listar notificaciones (paginadas, 15 por página, más recientes primero)
GET /api/notifications

Query params opcionales:
- user_id: integer
- type: string (ver enum)
- status: string (unread|read|archived)
- page: integer

Respuesta: paginator con data (carga eager de user y payment).

### Crear notificación
POST /api/notifications

Body JSON:
- user_id: integer (required, exists:users,id)
- payment_id: integer|null (exists:payments,id)
- type: enum requerido (incluye cobrador_payment_received)
- message: string requerido
- status: enum opcional (default unread)

Respuesta: notificación creada con user y payment incluidos.

### Ver detalle
GET /api/notifications/{id}

Respuesta: notificación con user y payment.

### Actualizar notificación
PUT/PATCH /api/notifications/{id}

Body JSON:
- user_id: integer (required)
- payment_id: integer|null
- type: enum requerido (incluye cobrador_payment_received)
- message: string requerido
- status: enum (unread|read|archived). Si se omite, conserva el valor actual.

Respuesta: notificación actualizada con user y payment.

### Eliminar notificación
DELETE /api/notifications/{id}

Respuesta: vacía o mensaje de éxito.

### Marcar una como leída
PATCH /api/notifications/{id}/mark-read

Respuesta: notificación con status=read.

### Marcar todas como leídas (por usuario)
POST /api/notifications/mark-all-read

Body JSON:
- user_id: integer (required)

Respuesta: vacía o mensaje de éxito.

### Listar por usuario (no paginado, orden desc)
GET /api/notifications/user/{userId}

Respuesta: array de notificaciones con payment incluido.

### Contar no leídas por usuario
GET /api/notifications/user/{userId}/unread-count

Respuesta: { unread_count: number } (envuelto con sendResponse).

## Esquema de respuesta (sendResponse)
Éxito:
{ "success": true, "data": <payload>, "message": string|null }

Error de validación:
{ "success": false, "message": "The given data was invalid.", "errors": { campo: [msg,...] } }

En colecciones paginadas puede retornar el paginator nativo. Ajusta el parseo del frontend a uno de estos dos patrones.

## Ejemplo de notificación (data)
{
  "id": 101,
  "user_id": 42,
  "payment_id": 555,
  "type": "payment_received",
  "message": "Tu pago de S/ 50.00 fue registrado.",
  "status": "unread",
  "created_at": "2025-09-01T12:34:56.000000Z",
  "updated_at": "2025-09-01T12:34:56.000000Z",
  "user": { /* ... */ },
  "payment": { /* ... */ }
}

## Ejemplos (React + Inertia v2)

Obtener paginado con filtros:
GET /api/notifications?user_id=42&status=unread&page=1

Marcar una como leída:
PATCH /api/notifications/123/mark-read

Marcar todas:
POST /api/notifications/mark-all-read { "user_id": 42 }

Contar no leídas:
GET /api/notifications/user/42/unread-count

Snippet axios:

const res = await axios.get('http://192.168.100.21:8000/api/notifications', { params: { user_id, status, type, page }, headers: { Authorization: `Bearer ${token}` } });
const payload = res.data.data ?? res.data;
const items = payload.data ?? payload;

await axios.patch('http://192.168.100.21:8000/api/notifications/123/mark-read', {}, { headers });
await axios.post('http://192.168.100.21:8000/api/notifications/mark-all-read', { user_id }, { headers });

const { data } = await axios.get('http://192.168.100.21:8000/api/notifications/user/42/unread-count', { headers });
const unread = data.data?.unread_count ?? data.unread_count;

## Sugerencias UI/UX (Inertia v2)
- Polling: refrescar unread-count cada 30–60s.
- Prefetching: prefetch de la lista al abrir el panel.
- Empty states: skeletons cuando se usen props diferidas.

## Reglas de negocio en frontend
- unread: resaltar y permitir "marcar como leída".
- read: normal; opcional archivar (status=archived).
- archived: ocultar o filtrar por status.
- Tipos:
  - payment_received: mostrar monto/fecha del payment si existe.
  - payment_due: alerta visual.
  - credit_approved: CTA para ver crédito.
  - credit_rejected: mostrar motivo si viene en message.
  - system_alert: mensajes generales.
  - cobrador_payment_received: específico para cobradores.
- payment puede ser null; validar en UI.
- Orden: ya viene desc por created_at.
