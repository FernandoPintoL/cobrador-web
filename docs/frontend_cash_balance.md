# Documentación de Frontend: Balance de Cajas

Esta guía describe cómo debe implementarse la interfaz y la integración frontend para la funcionalidad de balance de cajas ("caja"), qué datos enviar/recibir, endpoints disponibles, reglas de negocio, estados, validaciones, errores y casos de prueba. Está pensada para un equipo frontend que use React, Vue o similar.

## Resumen funcional

- Objetivo: permitir a cobradores abrir su caja diaria, registrar pagos asociados a una caja abierta, cerrar y reconciliar la caja (comparar movimientos vs. balance declarado) y que managers/administradores puedan ver y administrar cajas de cobradores.
- Roles implicados: cobrador, manager, admin.
- Estados de una caja: `open`, `closed`, `reconciled`.

## Perspectiva por rol

Esta sección describe qué ve y qué puede hacer cada rol (cobrador, manager, admin) en la UI para la funcionalidad de balance de cajas.

- Cobrador
  - Vistas principales disponibles:
    - Dashboard personal con la caja del día (si existe) y acceso rápido a "Abrir caja".
    - Formulario de creación de pagos (solo habilitado si existe caja `open` para la fecha del pago).
    - Vista detalle de su caja (solo sus cajas).
  - Acciones permitidas:
    - Abrir caja para la fecha actual (POST `/api/cash-balances/open`). Idempotente.
    - Registrar pagos (POST `/api/payments`) siempre que exista caja abierta; los pagos se ligan a `cash_balance_id`.
    - Solicitar cierre: proponer `final_amount` y marcar la caja como `closed` (siempre confirmar con modal).
    - Añadir notas/observaciones al cierre.
  - Restricciones UX y medidas de seguridad:
    - No puede crear ni eliminar cajas de otros cobradores.
    - Los botones de abrir/ cerrar deben estar visibles solo si corresponde; el backend valida permisos.

- Manager
  - Vistas principales disponibles:
    - Dashboard con lista de cobradores a su cargo y sus cajas (filtrable por cobrador y fecha).
    - Vista detalle de cajas de cobradores asignados.
  - Acciones permitidas:
    - Abrir caja para un cobrador (necesita enviar `cobrador_id` en POST `/api/cash-balances/open`).
    - Ver y comentar cierres de cajas de sus cobradores.
    - Reabrir o solicitar revisión a admin si detecta inconsistencias (según políticas de la organización).
  - Restricciones UX y medidas de seguridad:
    - No eliminar cajas de producción sin aprobación admin.
    - En la UI, mostrar un flujo de auditoría/nota cuando manager altera un balance.

- Admin
  - Vistas principales disponibles:
    - Vista global de todas las cajas y herramientas administrativas (filtros avanzados, exportaciones masivas).
    - Acceso a historial completo y auditoría.
  - Acciones permitidas:
    - Abrir/cerrar/reconciliar cajas para cualquier cobrador.
    - Forzar cambios en balances y limpiar duplicados en BD (operaciones de mantenimiento).
    - Ejecutar importaciones y correcciones históricas.
  - Restricciones UX y medidas de seguridad:
    - Acciones destructivas deben requerir confirmación fuerte (2FA o confirm modal con tipo de permiso).
    - Registrar `opened_by`, `closed_by` y `audit_log` para trazabilidad.

## Pantallas y componentes

A continuación se describen pantallas, subcomponentes y flujos UX.

1) Dashboard / Lista de cajas

- Propósito: ver cajas recientes, buscar por cobrador/fecha, ver estado y acciones rápidas.
- Componentes:
  - Tabla paginada con columnas: Fecha, Cobrador, Inicial, Recaudado, Prestado, Final (declarado), Estado, Acciones.
  - Filtros: `cobrador_id`, `date_from`, `date_to`, `status`.
  - Acciones por fila: Ver detalles, Abrir (si usuario puede y no hay `open` para esa fecha), Cerrar/Reconciliar (si `open`), Descargar informe.
- Endpoint que consume (ejemplo): GET `/api/cash-balances?date_from=...&date_to=...&cobrador_id=...`.

2) Abrir caja (pantalla o modal)

- Propósito: idempotente — si ya existe caja `open` devuelve la existente.
- UX: botón "Abrir caja" en dashboard o en vista de cobrador. Al abrir pedir `initial_amount` (opcional) y fecha (default hoy). Mostrar resultado (la caja abierta).
- Endpoint: POST `/api/cash-balances/open`.
- Validaciones en cliente: `initial_amount >= 0`, `date` formato ISO `YYYY-MM-DD`.

3) Pantalla Detalle de Caja / Reconciliación

- Propósito: mostrar resumen y movimientos vinculados para reconciliar.
- Componentes:
  - Resumen superior: Fecha, Cobrador, Estado, Inicial, Recaudado (sumatoria de pagos), Prestado, Final declarado, Diferencia (expected vs actual).
  - Tabs / Secciones: Pagos, Créditos creados, Notas/Historial.
  - Tabla de Pagos: lista de pagos vinculados (pagos donde `cash_balance_id = cashBalance.id` o `payment_date = date && cobrador_id = cobrador_id` si aún no se ligaron). Cada pago muestra: id, cliente, monto, método, fecha, estado, vínculo (cash_balance_id).
  - Botones: Marcar como `closed` (solicitar confirmación), `reconciled` (confirmar diferencias), Exportar CSV.
- Endpoint de detalle: GET `/api/cash-balances/{cashBalance}/detailed`.

4) Crear balance con cálculo automático (admin/manager)

- Propósito: calcular `collected_amount` y `lent_amount` automáticamente a partir de datos.
- Form: `cobrador_id`, `date`, `initial_amount`, `final_amount` (requeridos según backend endpoint).
- Endpoint: POST `/api/cash-balances/auto-calculate`.

5) Form para edición / cierre de caja

- Propósito: editar valores (collected, lent, final) y cambiar `status` a `closed` o `reconciled`.
- Validaciones: montos numéricos >= 0. Fecha inmutable o editable según reglas de negocio (recomiendo: permitir solo cambios por admin).
- Endpoint: PUT `/api/cash-balances/{id}`.

6) Pantalla de Pagos (vinculación)

- Importante: cuando un cobrador intenta crear un pago, la UI debe verificar si existe una caja abierta para la fecha y cobrador; si no, deshabilitar el formulario y mostrar CTA para "Abrir caja".
- Endpoint de pagos: POST `/api/payments`.
- Al crear pagos, backend espera y devolverá `cash_balance_id` asignado cuando corresponda.

## Endpoints (contrato mínimo) — payloads JSON y respuestas ejemplo

A continuación se describen los endpoints relevantes, JSON de request y respuesta (éxito y error típico). Autenticación: token (Sanctum) en header `Authorization: Bearer <token>`.

1) POST /api/cash-balances/open

- Permisos: cobrador (abre su caja), manager/admin (pueden abrir para cobrador_id especificado).
- Request JSON:
  {
    "cobrador_id": 123,        // opcional si el caller es cobrador
    "date": "2025-09-21",    // opcional, default hoy
    "initial_amount": 1000.50  // opcional, default 0
  }
- Success (201/200):
  {
    "success": true,
    "message": "Caja abierta exitosamente",
    "data": { "id": 456, "cobrador_id": 123, "date": "2025-09-21", "initial_amount": 1000.50, "collected_amount": 0, "lent_amount": 0, "final_amount": 1000.50, "status": "open" }
  }
- Idempotencia: si ya existe caja abierta devuelve 200 con message "Caja ya abierta para esta fecha" y el mismo objeto.
- Error (400):
  {
    "success": false,
    "message": "cobrador_id requerido",
    "errors": {}
  }

2) POST /api/cash-balances

- Crear balance manual (se validan campos completos)
- Request JSON (ejemplo):
  {
    "cobrador_id": 123,
    "date": "2025-09-21",
    "initial_amount": 1000.00,
    "collected_amount": 500.00,
    "lent_amount": 100.00,
    "final_amount": 1400.00,
    "status": "open" // opcional
  }
- Success (201): devuelve objeto `cash_balance` creado.
- Error 400: si ya existe balance para cobrador+fecha u otros errores de validación.

3) PUT /api/cash-balances/{id}

- Editar/actualizar: permite cambiar montos y `status`.
- Request JSON: igual que create, los campos requeridos dependen de la validación actual (en backend están marcados como required).
- Success: objeto actualizado.

4) GET /api/cash-balances (lista)

- Query params: `cobrador_id`, `date_from`, `date_to`, `status`, `page`, `per_page`.
- Response: paginación con colección de `cash_balance`.

5) GET /api/cash-balances/{id}/detailed

- Response (ejemplo):
  {
    "success": true,
    "data": {
      "cash_balance": { ... },
      "payments": [ { id, credit_id, client_id, amount, payment_method, payment_date, cash_balance_id, received_by, ... } ],
      "credits": [ ... ],
      "reconciliation": {
         "expected_final": 1400.00,
         "actual_final": 1390.00,
         "difference": -10.00,
         "is_balanced": false
      }
    }
  }

6) POST /api/cash-balances/auto-calculate

- Request JSON:
  {
    "cobrador_id": 123,
    "date": "2025-09-21",
    "initial_amount": 1000.00,
    "final_amount": 1400.00
  }
- Response: creado con `collected_amount` calculado desde pagos y `lent_amount` desde créditos.

7) POST /api/payments

- Request JSON (ejemplo):
  {
    "credit_id": 789,
    "amount": 50.00,
    "payment_method": "cash", // cash|transfer|check|other
    "payment_date": "2025-09-21",
    "latitude": -12.045, // opcional
    "longitude": -77.028, // opcional
    "installment_number": 5 // opcional
  }
- Behavior esperado:
  - Si el caller tiene rol `cobrador`, el backend rechazará con 400 si no existe una caja abierta para (cobrador, payment_date).
  - Si existe caja abierta, backend asignará `cash_balance_id` al pago y lo devolverá en la respuesta.
- Success (200):
  {
    "success": true,
    "data": {
      "payments": [ { "id": 111, "cash_balance_id": 456, "amount": 50.00, ... } ],
      "total_paid": 50.00
    },
    "message": "Pagos registrados exitosamente"
  }
- Error 400 (sin caja abierta):
  {
    "success": false,
    "message": "Caja no abierta",
    "errors": "No existe una caja abierta para la fecha del pago. Abre la caja antes de registrar pagos."
  }

## Reglas de negocio clave que el frontend debe respetar

- Sólo se permiten pagos de cobradores si hay una caja `open` para la fecha del pago y para el cobrador. Si no existe, el frontend debe ofrecer abrirla (botón) antes de permitir crear pagos.
- Abrir caja es idempotente: llamar varias veces devuelve la misma caja abierta; la UI debe evitar crear duplicados en paralelo (deshabilitar botón hasta respuesta).
- La tabla `cash_balances` tiene una restricción única en el backend por `(cobrador_id, date)`. Evitar crear duplicados mediante UX (confirmaciones) y manejo del error 400.
- El cierre y reconciliación deben requerir confirmación y posiblemente un flujo de aprobaciones (si se desea control). Considerar: sólo admin/manager pueden forzar cambios posteriores.
- Al crear pagos en bloques (cuando el pago cubre N cuotas), el backend divide el pago en varios registros: la UI debe mostrar esto (detalle de pagos creados y total pagado).

## Validaciones en UI (copia de las reglas del backend)

- `initial_amount`, `collected_amount`, `lent_amount`, `final_amount`: numéricos, >= 0, 2 decimales permitidos.
- `date`: formato ISO `YYYY-MM-DD`.
- `payment_method`: must be one of ["cash","transfer","check","other"].
- `amount` en pagos: mínimo 0.01, no mayor que `credit.balance` (el UI debe mostrar balance pendiente antes de enviar).

## Manejo de errores y mensajes UX

- 400 (Bad Request): mostrar mensaje claro y posible acción (por ejemplo, cuando backend devuelve "Caja no abierta", mostrar CTA "Abrir caja" que llame al endpoint idempotente).
- 403 (Forbidden): mostrar mensaje "No autorizado" y deshabilitar acciones.
- 500 (Server Error): mostrar banner genérico con opción de reintentar.
- Detección de duplicados: si backend responde que ya existe balance para cobrador+fecha, ofrecer ir al detalle de la caja en lugar de crear nueva.

## Offline / sincronización y consideraciones móviles

- Escenario móvil: los cobradores pueden trabajar en zonas sin conexión. Recomendación:
  - Implementar cola local (IndexedDB / localStorage) para pagos pendientes.
  - Antes de encolar un pago, la app local debe verificar si existe una caja abierta en el servidor para la fecha (o abrirla). Si no hay conexión, la app puede marcar la operación como "pendiente de apertura de caja y envío".
  - Al reconectar, sincronizar en este orden: abrir caja (si pendiente), enviar pagos. Manejar conflictos: si otra app abrió una caja distinta, reconciliar con backend (back-end debería asignar al cash_balance que corresponda por fecha/cobrador).
  - Riesgo: el backend valida caja abierta en el momento del POST; por tanto, envío offline requiere un mecanismo de reconciliación y reintentos automáticos.

## Consideraciones de seguridad

- Autenticación: usar token Bearer (Sanctum); todas las rutas protegidas requieren `auth:sanctum`.
- Validar en cliente permisos: ocultar botones (Abrir, Cerrar) cuando el rol no lo permite para mejorar UX, pero la verificación final la hace backend.
- Evitar exponer ids sensibles en la UI, mostrar nombres y usar ids sólo para llamadas API.

## Experiencia de usuario: recomendaciones concretas

- Mostrar claramente el estado de la caja y una bandera visible en el header (por ejemplo, en la app móvil un chip "Caja abierta: S/. 1,000" si el cobrador tiene una caja abierta hoy).
- Botón rápido "Abrir caja" en la pantalla principal de cobrador si no existe caja hoy; al abrir, mostrar snackbar con link a la vista de detalles.
- Confirmaciones sobre cerrar caja: pedir `final_amount` y mostrar diferencia con calculado para que el cobrador explique los descuadres.
- Exportar CSV / Excel desde la vista detallada para auditoría.
- Registrar notas/observaciones al cerrar (campo opcional) para justificar diferencias.

## Casos de prueba (aceptación) — mínimo

1. Cobrador abre su caja hoy (POST /api/cash-balances/open) → Success y muestra caja.
2. Cobrador intenta abrir su caja dos veces → segunda llamada devuelve la misma caja (idempotencia).
3. Cobrador intenta crear pago sin caja abierta → recibe error 400 y la UI ofrece abrir caja.
4. Cobrador con caja abierta crea pago → pago creado y `cash_balance_id` presente en response.
5. Manager abre caja para un cobrador remoto → funciona con `cobrador_id` en payload.
6. Al cerrar caja, diferencias se calculan correctamente en `reconciliation` y la UI muestra `is_balanced`.

## Contratos JSON resumidos (compactos)

- Header obligatorio: Authorization: Bearer <token>

- Abrir caja (idempotente):
  POST /api/cash-balances/open
  Request: { cobrador_id?, date?, initial_amount? }
  Response success: { success: true, data: { id, cobrador_id, date, initial_amount, collected_amount, lent_amount, final_amount, status } }

- Crear/editar caja:
  POST /api/cash-balances
  PUT /api/cash-balances/{id}

- Detalle de caja:
  GET /api/cash-balances/{id}/detailed
  Response: { cash_balance, payments[], credits[], reconciliation: { expected_final, actual_final, difference, is_balanced } }

- Crear pago:
  POST /api/payments
  Request: { credit_id, amount, payment_method, payment_date?, latitude?, longitude?, installment_number? }
  Success: { payments: [ { id, cash_balance_id, ... } ], total_paid }
  Error (sin caja abierta): 400 con mensaje "Caja no abierta".

## Consideraciones de implementación frontend (tech tips)

- Componentes reutilizables:
  - MoneyInput: maneja localización (coma/punto), validación y formateo a 2 decimales.
  - DatePicker: forzar formato YYYY-MM-DD y timezone server-neutral (usar dates sin tiempo para cajas por fecha).
  - ConfirmModal: para cerrar/reconciliar cajas.
  - ErrorBanner/Snackbar: mostrar mensajes del servidor.
- Peticiones y manejo de estado:
  - Mantener un store (Redux, Vuex, Pinia) con `currentCashBalance` para el cobrador autenticado; sincronizar al login y al abrir caja.
  - Al crear pagos en lote (p. ej. un pago que cubre varias cuotas), mostrar un resumen de los pagos parciales creados devueltos por el backend.
- Localización/formatos: mostrar montos en la moneda local con dos decimales y separador de miles.

## Checklist para lanzamiento

- [ ] Revisar y limpiar duplicados en BD antes de migración del índice único `(cobrador_id, date)`.
- [ ] Ejecutar migraciones y ajustar cliente para enviar/esperar `cash_balance_id` en respuestas de pagos.
- [ ] Implementar tests E2E que cubran flujo abrir caja → crear pagos → cerrar → reconciliar.
- [ ] Comunicar a cobradores el nuevo flujo: "debés abrir la caja antes de registrar pagos".

## Anexos: puntos técnicos rápidos

- Fecha base: usar `date` (YYYY-MM-DD) como la unidad para la caja; no depender de timestamps con zona horaria.
- Recomendación para reconciliación exacta: sumar pagos `collected_amount` usando `cash_balance_id` preferentemente; como fallback sumar por `payment_date` y `cobrador_id`.
- Auditing: considerar añadir `opened_by`, `closed_by`, `closed_at` al modelo `cash_balances` en una futura migración.

---

Si quieres, lo guardo también como un archivo resumido en `d:\josecarlos\cobrador\docs\frontend_cash_balance.md` (ya lo creé). Puedo ahora:

- Añadir pruebas PHPUnit para los casos server-side (p. ej. pago sin caja abierta),
- Implementar E2E / cypress test flows para frontend,
- Generar componentes UI básicos (React/Vue) con formularios y validaciones según la especificación.

¿Qué prefieres que haga a continuación?
