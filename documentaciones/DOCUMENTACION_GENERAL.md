# Documentación General del Sistema de Créditos

Fecha: 2025-08-24 13:53

Este documento resume el funcionamiento actual del sistema (API y lógica de negocio) y enlaza a guías específicas ya existentes dentro de `documentaciones/`. Incluye los cambios más recientes solicitados: entrega el mismo día (si el manager lo permite), cronograma que inicia al día siguiente de la aprobación y fast‑track para que un manager pueda crear créditos para sus clientes directos sin aprobación.

Secciones clave:
- Roles y permisos
- Rankings de clientes (A, B, C) y límites
- Flujo de ciclo de vida de créditos (creación, aprobación, entrega, pagos)
- Endpoints principales y ejemplos
- Lista de espera (waiting list)
- Gestión de tasas de interés
- Ubicación y mapas
- Notificaciones y WebSockets

Referencias relacionadas:
- ENDPOINTS_CLIENTES_LISTADO.md
- ENDPOINTS_CREDITOS_POR_CLIENTE.md
- ENDPOINTS_CREDITOS_Y_PAGOS.md
- WAITING_LIST_SYSTEM.md
- INTEREST_RATES_MANAGEMENT.md
- CLIENT_COBRADOR_ASSIGNMENT_API.md
- MANAGER_COBRADOR_ASSIGNMENT_API.md
- PROFILE_IMAGE_API.md / USER_PHOTOS_MANAGEMENT.md
- WEBSOCKETS_IMPLEMENTATION_GUIDE.md / REACT_WEBSOCKET_INTEGRATION.md / FLUTTER_WEBSOCKET_INTEGRATION.md / ARQUITECTURA_COMUNICACION_BACKEND_FLUTTER_WEBSOCKET.md / FLUJO_CREDITOS_FORMATOS_FRONTEND.md

---

## 1) Roles y permisos

Roles base:
- admin: Acceso total a la administración y consultas.
- manager: Gestiona cobradores y clientes (directos e indirectos). Puede aprobar/rechazar/reprogramar/entregar créditos. Fast‑track al crear créditos para clientes directos.
- cobrador: Gestiona sus clientes asignados. Puede crear créditos para sus clientes y entregarlos cuando estén en `waiting_delivery`.
- client: Cliente final que recibe créditos y realiza pagos.

Relaciones de asignación:
- Un cliente puede tener `assigned_cobrador_id` y/o `assigned_manager_id` (directo). 
- Un cobrador se asigna a un manager por `assigned_manager_id`.

Endpoints de asignación (ver detalles en docs dedicadas):
- GET /api/users/{cobrador}/clients
- POST /api/users/{cobrador}/assign-clients
- GET /api/users/{manager}/cobradores
- POST /api/users/{manager}/assign-cobradores
- GET /api/users/{manager}/clients-direct
- GET /api/users/{manager}/manager-clients (directos + indirectos)

## 2) Rankings de clientes (A, B, C) y límites

El sistema soporta categorías de cliente en `users.client_category` con valores: A (VIP), B (Normal), C (Mal Cliente). Endpoints:
- GET /api/client-categories (lista de categorías)
- PATCH /api/users/{client}/category (actualizar categoría)
- GET /api/clients/by-category (listar clientes por categoría)
- GET /api/client-categories/statistics (estadísticas por categoría)
- POST /api/clients/bulk-update-categories (actualización masiva)

Límites por categoría aplicados al crear un crédito (en `CreditController@store`):
- A: max_amount=10000, max_credits=5
- B: max_amount=5000, max_credits=3
- C: max_amount=2000, max_credits=1

Reglas de control:
- Se valida que el monto del nuevo crédito esté dentro del rango permitido para la categoría del cliente.
- Se valida que el cliente no exceda la cantidad máxima de créditos en estados: `pending_approval`, `waiting_delivery`, `active`.

Nota: Actualmente no hay ranking de cobrador en el esquema. La estructura del controlador permite extenderlo en el futuro.

## 3) Flujo de ciclo de vida de un crédito

Estados principales del crédito: `pending_approval` -> `waiting_delivery` -> `active` -> `completed` (u otros como `defaulted`, `cancelled`, `rejected`).

Cambios recientes clave:
- Aprobación permite programar entrega el mismo día: validación `after_or_equal:today` para `scheduled_delivery_date`.
- El cronograma de cuotas (start_date) inicia al día siguiente de la aprobación.
- Fast‑track: cuando un manager crea un crédito para un cliente directo, se omite la aprobación. El crédito pasa directamente a `waiting_delivery`, con `approved_by/approved_at` autoasignados y `start_date` ajustado a mañana. La fecha de entrega por defecto es al día siguiente, salvo que el manager indique mismo día.

Acciones del flujo:
- Crear crédito: POST /api/credits
  - Reglas de acceso: cobrador (solo para sus clientes), manager (clientes directos o de sus cobradores), admin (sin restricciones). 
  - Si manager + cliente directo: fast‑track a `waiting_delivery`.
- Aprobar crédito: POST /api/credits/{credit}/waiting-list/approve (ver sección Lista de Espera)
  - `start_date` se fija a mañana respecto a `approved_at`.
  - Si `end_date` no es posterior a `start_date`, se extiende automáticamente (30 días por defecto de seguridad).
- Entregar crédito: POST /api/credits/{credit}/waiting-list/deliver
  - Cambia a `active` y marca `delivered_by/delivered_at`.
- Pagos: ver sección de pagos.

## 4) Endpoints principales y ejemplos (resumen)

Autenticación (Sanctum):
- POST /api/register, POST /api/login, POST /api/logout, GET /api/me

Usuarios y asignaciones:
- GET /api/users (listado)
- GET /api/users/{manager}/manager-clients (clientes directos e indirectos del manager)
- GET /api/users/{cobrador}/clients (clientes del cobrador)
- Ver el archivo `routes/api.php` para el mapa completo de rutas.

Créditos:
- GET /api/credits (lista con filtros por status, search, client_id, cobrador_id)
- POST /api/credits (crear)
- GET /api/credits/{credit} (detalle)
- PATCH/PUT /api/credits/{credit} (actualizar)
- DELETE /api/credits/{credit} (eliminar)
- GET /api/credits/client/{client} (por cliente)
- GET /api/credits/cobrador/{cobrador} (por cobrador)
- GET /api/credits/manager/{manager}/stats (estadísticas del manager)
- GET /api/credits-requiring-attention (vencidos o por vencer en 7 días)
- GET /api/credits/overdue (atrasados activos)

Pagos de créditos (uso recomendado: PaymentController):
- POST /api/payments (crear pago)
- GET /api/payments/credit/{credit} (pagos por crédito)
- GET /api/credits/{credit}/details (resumen crédito + pagos + cronograma)
- POST /api/credits/{credit}/simulate-payment (simular pago)
- GET /api/credits/{credit}/payment-schedule (cronograma con cuotas marcadas pagadas)

Ejemplo crear crédito (manager, cliente directo -> fast‑track):
```
POST /api/credits
{
  "client_id": 123,
  "amount": 1500,
  "balance": 1500,
  "frequency": "daily",
  "start_date": "2025-08-24",
  "end_date": "2025-09-30",
  "scheduled_delivery_date": "2025-08-24"  // permitido mismo día
}
```
Respuesta: status `waiting_delivery`, `approved_by` y `approved_at` asignados, `start_date` ajustado a mañana.

## 5) Lista de Espera (Waiting List)

Rutas agrupadas en `/api/credits/waiting-list` y `/api/credits/{credit}/waiting-list`:
- GET /pending-approval: créditos en espera de aprobación
- GET /waiting-delivery: créditos aprobados esperando entrega
- GET /ready-today: listos para entregar hoy
- GET /overdue-delivery: fechas de entrega vencidas
- GET /summary: resumen general
- POST /{credit}/approve: aprueba (manager/admin). Ajusta `start_date = approved_at + 1 día` y permite `scheduled_delivery_date` hoy o futuro.
- POST /{credit}/reject: rechaza
- POST /{credit}/deliver: entrega (cobrador/manager/admin)
- POST /{credit}/reschedule: reprograma entrega (manager/admin)

Detalles adicionales en `documentaciones/WAITING_LIST_SYSTEM.md`.

## 6) Gestión de Tasas de Interés

- GET/POST/PUT/DELETE /api/interest-rates
- GET /api/interest-rates/active
- Al crear crédito:
  - Si cobrador: usa tasa activa si existe.
  - Si manager/admin: puede enviar `interest_rate_id` o `interest_rate`; sino, usa la activa.

Más info en `documentaciones/INTEREST_RATES_MANAGEMENT.md` y `CREDITS_INTEREST_SYSTEM.md`.

## 7) Ubicación y Mapas

Usuarios y créditos soportan `latitude`/`longitude` (en usuarios; en créditos también se guarda ubicación cuando aplica). Map endpoints:
- GET /api/map/clients (clientes con ubicación)
- GET /api/map/stats
- GET /api/map/clients-by-area
- GET /api/map/cobrador-routes

Detalles en `documentaciones/LOCATION_IMPLEMENTATION.md` y `LOCATION_FIX.md`.

## 8) Notificaciones y WebSockets

- Endpoints en `/api/websocket/*` para notificaciones en tiempo real.
- Eventos emitidos al crear, aprobar, entregar y reprogramar créditos (p. ej., `CreditWaitingListUpdate`).

Ver `WEBSOCKETS_IMPLEMENTATION_GUIDE.md`, `REACT_WEBSOCKET_INTEGRATION.md`, `FLUTTER_WEBSOCKET_INTEGRATION.md` y `WEBSOCKET_LARAVEL_INTEGRATION_COMPLETE.md`.

---

## Cambios Recientes (Resumen)

- Validación de fechas de entrega: `scheduled_delivery_date` ahora permite el mismo día (after_or_equal:today) en creación, lista de espera y aprobación.
- Aprobación ajusta el cronograma: `start_date = approved_at + 1 día`; si `end_date <= start_date`, se corrige automáticamente (+30 días por defecto seguro).
- Fast‑track de manager con cliente directo: se crea el crédito en `waiting_delivery`, se autoasignan `approved_by/approved_at`, y se ajustan fechas (entrega por defecto próxima, permitiendo mismo día si se indica).
- Reglas de ranking A/B/C: validación de monto y de cantidad de créditos activos/en proceso por cliente antes de crear.

## Notas Operativas

- Los cobradores solo pueden crear créditos para sus propios clientes asignados y no pueden indicar otro cobrador en la creación.
- Los managers pueden crear créditos para clientes directos o para clientes de sus cobradores.
- Los admins no tienen restricciones de relación para crear créditos.
- Para consultas más detalladas de endpoints de créditos y pagos, ver `ENDPOINTS_CREDITOS_Y_PAGOS.md`.

---

Si necesitas que agreguemos ejemplos específicos (cURL, Postman) o diagramas de flujo, indica el escenario y los agregamos en esta misma guía.
