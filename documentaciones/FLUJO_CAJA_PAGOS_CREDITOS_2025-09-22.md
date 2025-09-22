# Flujo de Trabajo: Cajas (Cash Balance) con relación a Pagos y Créditos

Este documento explica cómo se relaciona la “Caja” (Cash Balance) con los Pagos y los Créditos en el día a día. Incluye el ciclo de vida, responsabilidades por rol, fórmulas de conciliación y endpoints recomendados.

Base de APIs: `/api`


## 1) Entidades y campos relevantes

- Caja (CashBalance)
  - `cobrador_id`: usuario cobrador dueño de la caja.
  - `date`: fecha de la caja (por día).
  - `initial_amount`: efectivo inicial.
  - `collected_amount`: total cobrado por pagos del día.
  - `lent_amount`: total prestado por créditos creados ese día.
  - `final_amount`: efectivo final al cierre.
  - `status`: `open | closed | reconciled`.

- Pago (Payment)
  - `cobrador_id`: cobrador que registró el pago.
  - `payment_date`: fecha en que se efectúa el pago.
  - `status`: se considera en caja cuando es `paid`.
  - `amount`: monto abonado (y por campos derivados: porción a capital, interés, etc. si aplica).

- Crédito (Credit)
  - `created_by`: cobrador que creó el crédito (se considera “prestado” desde caja).
  - `created_at`: fecha/hora de creación (se usa la fecha para caja).
  - `amount`, `total_amount`, `balance`, `status`…


## 2) Principios clave de contabilización

- Colocado (Préstamos):
  - Para una fecha D y cobrador C, la caja **suma a `lent_amount`** la **sumatoria de `amount`** de los créditos **creados** ese día por C (`credits.created_by = C` y `date(created_at) = D`).

- Cobrado (Pagos):
  - Para la misma fecha D y cobrador C, la caja **suma a `collected_amount`** la **sumatoria de `amount`** de los pagos **efectuados** ese día por C (`payments.cobrador_id = C`, `payment_date = D`) y con `status = 'paid'`.

- Conciliación esperada:
  - `expected_final = initial_amount + collected_amount - lent_amount`.
  - Diferencia: `difference = final_amount - expected_final`.
  - Balanceado: `is_balanced = abs(difference) < 0.01` (tolerancia de redondeo).

Estas fórmulas ya son utilizadas por el backend en el endpoint de detalle de caja.


## 3) Ciclo de vida diario de la Caja (Cobrador)

1. Abrir caja (idempotente)
   - Endpoint: `POST /api/cash-balances/open`
   - Cobrador: puede llamar sin `cobrador_id`; Admin/Manager debe pasar `cobrador_id`.
   - Si ya existe una caja `open` para (cobrador, fecha), la retorna en lugar de crear otra.
   - Inicializa: `initial_amount` (si se envía) y `final_amount = initial_amount`.

2. Registrar actividad del día
   - Pagos que el cobrador cobra a sus clientes.
     - Cada pago registrado para la fecha D y `status = 'paid'` incrementa el total que luego verá la caja en `collected_amount`.
   - Créditos que el cobrador crea en el día (nuevos préstamos).
     - Cada crédito con `created_by = cobrador` y `date(created_at) = D` incrementa el total de `lent_amount` de la caja.

3. Visualizar conciliación en tiempo real
   - Endpoint: `GET /api/cash-balances/{id}/detailed`
   - Devuelve:
     - `payments` del día (con `client` y `credit`).
     - `credits` creados en el día (con `client`).
     - `reconciliation` con `expected_final`, `actual_final`, `difference`, `is_balanced`.

4. Cierre de caja
   - Una vez verificada la conciliación, actualizar el registro:
     - Endpoint: `PUT /api/cash-balances/{id}`
     - Enviar `final_amount` real y, si corresponde, `status = 'closed'` o `reconciled`.
   - El `collected_amount` y `lent_amount` pueden ser ajustados manualmente solo si la operación exige correcciones; idealmente se confía en los datos transaccionales (pagos/créditos) y se corrige la fuente si hay errores.


## 4) Flujos por Rol

- Cobrador
  - Abre su caja del día (idempotente).
  - Registra pagos (normalmente vía módulo Pagos) y puede crear créditos nuevos para clientes.
  - Revisa la conciliación y cierra la caja.

- Manager / Admin
  - Puede abrir caja para un cobrador específico.
  - Supervisa cajas por rangos de fechas y por cobrador.
  - Usa reportes para auditoría y KPIs.


## 5) Endpoints útiles del módulo Caja

- Listado paginado con filtros
  - `GET /api/cash-balances?cobrador_id=&date_from=&date_to=`

- CRUD manual
  - `POST /api/cash-balances`
  - `GET /api/cash-balances/{id}`
  - `PUT /api/cash-balances/{id}`
  - `DELETE /api/cash-balances/{id}`

- Por cobrador (lista simple)
  - `GET /api/cobradores/{cobrador}/cash-balances`

- Resumen sumado por cobrador
  - `GET /api/cash-balances/summary/{cobrador}`

- Detalle para conciliación
  - `GET /api/cash-balances/{id}/detailed`

- Abrir caja (idempotente)
  - `POST /api/cash-balances/open`

- Crear caja con cálculo automático (si se usa)
  - `POST /api/cash-balances/auto`
  - Calcula `collected_amount` y `lent_amount` a partir de pagos/créditos del día, y guarda con los montos resultantes.


## 6) Relación exacta con Pagos y Créditos

- Pagos → Caja (collected)
  - Consulta base: `Payment::where('cobrador_id', C).whereDate('payment_date', D).where('status','paid')`.
  - En `GET /api/cash-balances/{id}/detailed` los pagos vienen con `client` y `credit` para trazabilidad.

- Créditos → Caja (lent)
  - Consulta base: `Credit::where('created_by', C).whereDate('created_at', D)`.
  - En `GET /api/cash-balances/{id}/detailed` los créditos vienen con `client`.


## 7) Conciliación y fórmulas

- `expected_final = initial_amount + collected_amount - lent_amount`
- `difference = final_amount - expected_final`
- `is_balanced = abs(difference) < 0.01`

Recomendación: mostrar en UI estos tres datos y resaltar cuando `is_balanced = false`.


## 8) Reportes relacionados

- Reporte de Pagos
  - `GET /api/reports/payments?format=json|html|pdf|excel&start_date=&end_date=&cobrador_id=`
  - Respeta el alcance por rol (un cobrador solo verá sus pagos).

- Reporte de Créditos
  - `GET /api/reports/credits?format=...&status=&cobrador_id=&client_id=`
  - Usa `created_by` para filtrar por cobrador.

- Reporte de Balances (Cajas)
  - `GET /api/reports/balances?format=...&start_date=&end_date=&cobrador_id=`

Útiles para auditoría, paneles y exportación.


## 9) Buenas prácticas y consideraciones

- Zona horaria: la caja usa la fecha del servidor si no se envía (`now()->toDateString()`). Alinear la UI a esa fecha o permitir especificarla.
- Idempotencia: el endpoint de apertura evita duplicados por (cobrador, fecha). Manejar reintentos sin miedo.
- Redondeo: usar 2 decimales y tolerancia al comparar diferencias.
- Correcciones: si hay diferencias, revisar pagos y créditos antes de forzar cambios manuales en la caja.
- Roles en UI: ocultar selector de `cobrador_id` para cobrador; habilitarlo para manager/admin.
- Rendimiento: para vistas de conciliación, cargar `GET /api/cash-balances/{id}/detailed` bajo demanda (deferred props / skeletons si aplica).


## 10) Ejemplo de jornada (Cobrador)

1) 08:00 — Abre caja:
```
POST /api/cash-balances/open
{
  "initial_amount": 150
}
```
→ Caja `open` con `final_amount = 150`.

2) 09:00–17:00 — Registra pagos y crea 1 crédito:
- Pagos registrados (paid) por 300 en total.
- Nuevo crédito creado por 100.

3) 17:05 — Revisa conciliación:
```
GET /api/cash-balances/{id}/detailed
```
- expected_final = 150 + 300 - 100 = 350.

4) 17:10 — Cuenta efectivo real y cierra:
```
PUT /api/cash-balances/{id}
{
  "final_amount": 350,
  "status": "closed"
}
```
`difference = 0`, `is_balanced = true`.

Si hubiera 10 de diferencia (final 340), la UI muestra alerta y el cobrador debe revisar pagos/créditos y/o registrar ajustes con su responsable.
