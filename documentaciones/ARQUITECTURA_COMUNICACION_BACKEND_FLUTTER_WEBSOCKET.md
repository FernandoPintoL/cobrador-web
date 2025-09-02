# Arquitectura de Comunicación: Backend (Laravel) ↔ WebSocket (Node.js) ↔ Frontends (Flutter y React)

Fecha: 2025-09-02 00:17

Este documento describe la arquitectura final recomendada para la comunicación en tiempo real entre el backend Laravel, el servidor WebSocket en Node.js y los frontends (Flutter y React). Incluye flujos, responsabilidades de cada capa, mapeo de eventos de negocio y guías de configuración/operación.

---

## 1) Objetivo

- Enviar notificaciones en tiempo real entre cobradores y managers durante el ciclo de vida de créditos y pagos.
- Mantener Laravel como fuente de verdad y orquestador de eventos de negocio.
- Usar un servidor Node.js (Socket.IO) como transport de WebSocket para compatibilidad total con Flutter y React.

---

## 2) Diagrama de Alto Nivel

Laravel (API + Negocio) → Publica eventos HTTP
  └─ POST /notify, /credit-notification, /payment-notification → Node WebSocket (Socket.IO)
       └─ Emite a salas: user_{id}, managers, cobradores, admins → Clientes (Flutter, React)

Adicionalmente, Laravel continúa emitiendo via Broadcasting (Reverb) para compatibilidad con clientes que ya lo usan.

---

## 3) Responsabilidades

- Laravel
  - Autenticación (Sanctum), permisos y validaciones.
  - Eventos de dominio: creación, aprobación, rechazo, entrega de créditos; pagos.
  - Listeners que persisten notificaciones en BD y llaman al WebSocket Notification Service para publicar en Node.
  - Endpoints REST para CRUD y consultas.

- Node.js WebSocket (websocket-server/server.js)
  - Socket.IO con salas por rol y por usuario.
  - Endpoints HTTP para recibir notificaciones desde Laravel y reemitir a sockets:
    - POST /notify
    - POST /credit-notification
    - POST /payment-notification
  - Salud, test y depuración: /health, /test.html, /active-users.

- Frontend (Flutter / React)
  - Conexión a Socket.IO.
  - Autenticación de sesión en el socket mediante envío de userId, userType, userName (evento authenticate).
  - Suscripción a eventos específicos: credit_waiting_approval, credit_approved, credit_rejected, credit_delivered, payment_received, cobrador_payment_received, etc.

---

## 4) Mapeo de Eventos de Negocio → Eventos de Socket

- Cobrador solicita crédito (created)
  - Laravel: event CreditWaitingListUpdate(action='created')
  - Listener: SendCreditWaitingListNotification → WebSocketNotificationService->sendCreditNotification(action='created')
  - Node: emite 'credit_waiting_approval' a manager.id

- Manager aprueba (approved)
  - Laravel: CreditWaitingListUpdate('approved')
  - Listener: sendCreditNotification('approved')
  - Node: emite 'credit_approved' a cobrador.id

- Manager rechaza (rejected)
  - Laravel: CreditWaitingListUpdate('rejected')
  - Listener: sendCreditNotification('rejected')
  - Node: emite 'credit_rejected' a cobrador.id

- Entrega del crédito (delivered) → cambia a activo
  - Laravel: CreditWaitingListUpdate('delivered')
  - Listener: sendCreditNotification('delivered')
  - Node: emite 'credit_delivered' a manager.id

- Pago registrado (payment)
  - Laravel: PaymentReceived
  - Listener: SendPaymentReceivedNotification → WebSocketNotificationService->sendPaymentNotification()
  - Node: emite 'payment_received' a cobrador.id y 'cobrador_payment_received' a manager.id

- Futuro: crédito completado/terminado (completed/terminated)
  - Similar a los anteriores: agregar acción y emitir evento específico.

---

## 5) Canales y Salas

- Salas por usuario: user_{userId}
- Salas por rol: managers, cobradores, admins, clients
- Autenticación de socket: emitir 'authenticate' al conectar con { userId, userType, userName }

---

## 6) Configuración Requerida

- .env (Laravel)
  - WEBSOCKET_HOST=IP_o_Host_Node
  - WEBSOCKET_PORT=3001
  - WEBSOCKET_SECURE=false
  - WEBSOCKET_ENDPOINT=/notify

- config/broadcasting.php (Laravel)
  - Conexión personalizada 'websocket' ya creada; usada por WebSocketNotificationService.

- Node.js (Railway o local)
  - PORT (Railway lo inyecta)
  - CLIENT_URL, MOBILE_CLIENT_URL, WEBSOCKET_URL para CORS en producción.

---

## 7) Endpoints Laravel útiles

- API de WebSocket test: /api/websocket/test, /api/websocket/test-direct
- Créditos: creación, aprobación, rechazo, entrega bajo /api/credits y /api/credits/{credit}/waiting-list/*
- Pagos: /api/payments

---

## 8) Guía Frontend (resumen)

- Flutter
  - Conectar a http://{WEBSOCKET_HOST}:{PORT}
  - Emitir 'authenticate' tras conectar.
  - Escuchar eventos: 'credit_waiting_approval', 'credit_approved', 'credit_rejected', 'credit_delivered', 'payment_received', 'cobrador_payment_received'.

- React
  - Similar a Flutter, ver REACT_WEBSOCKET_INTEGRATION.md.

---

## 9) Operación y Troubleshooting

- Verificar Node vivo: GET /health
- Ver usuarios activos: GET /active-users
- Logs Laravel: storage/logs/laravel.log
- Si no llegan notificaciones:
  - Confirmar que listeners se ejecutan (revisar logs de CreditWaitingListUpdate/PaymentReceived)
  - Confirmar que WebSocketNotificationService reacha Node (logs de éxito/fracaso)
  - Comprobar CORS origen cliente y que el cliente ejecuta 'authenticate'
  - En frontend, reconectar ante desconexión y manejar pingTimeout/pingInterval

---

## 10) Seguridad

- El canal de publicación desde Laravel a Node se realiza por red interna o HTTPS.
- Validar orígenes en CORS del servidor de Socket.IO en producción.
- No se exponen tokens de Laravel en el socket; solo metadatos mínimos de usuario para enrutar.

---

## 11) Extensiones Sugeridas

- Agregar evento 'completed'/'terminated'.
- Persistir ACKs en Node si se requiere auditoría de entrega a socket.
- Integrar push notifications móviles como respaldo (FCM/APNs) cuando el socket no esté activo.

---

## 12) Referencias Internas

- websocket-server/server.js
- app/Services/WebSocketNotificationService.php
- app/Listeners/SendCreditWaitingListNotification.php
- app/Listeners/SendPaymentReceivedNotification.php
- documentaciones/FLUTTER_WEBSOCKET_INTEGRATION.md
- documentaciones/REACT_WEBSOCKET_INTEGRATION.md
- documentaciones/DOCUMENTACION_GENERAL.md (sección 8)
