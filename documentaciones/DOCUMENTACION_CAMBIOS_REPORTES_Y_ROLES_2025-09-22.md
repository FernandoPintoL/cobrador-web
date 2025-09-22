# Documentación de cambios: Reportes, Control de Roles y Formatos

Fecha: 2025-09-22 10:32
Autor: Equipo de desarrollo

## Resumen ejecutivo

Se corrigieron y fortalecieron varios módulos para asegurar datos consistentes en reportes (PDF/HTML/JSON/Excel), controles de visibilidad por rol y cálculos de totales. Cambios clave:

- Reportes (ReportController):
  - Filtros y validaciones por endpoint (pagos, créditos, usuarios, balances).
  - Visibilidad por rol: el cobrador ve solo lo suyo; el manager ve su equipo y clientes directos/indirectos.
  - Se añadieron campos de resumen faltantes (por ejemplo, `pending_amount` en créditos; conteos por rol en usuarios).
  - Nuevo endpoint de catálogo de reportes: `/api/reports/types`.
- Exports (Excel):
  - PaymentsExport: muestra `payment_method` y agrega fila de resumen con totales y promedio.
  - CreditsExport: calcula correctamente `Monto Pagado` como `total_amount - balance` y expone `pending_amount` en el resumen.
  - UsersExport: usa la relación existente `assignedManager` y agrega conteos de `cobradores` y `managers` al resumen.
  - BalancesExport: agrega fila de resumen general con totales y diferencia promedio.
- Modelos:
  - Payment: se agregaron atributos calculados `principal_portion`, `interest_portion`, `remaining_for_installment`; normalización del `installment_number`; eventos de modelo para ajustar balances de créditos al crear/actualizar pagos.
  - CashBalance: tipos de datos (casts) y relación con `User`.
- Vista HTML de pagos: se muestra información adicional de cuotas y totales estimados.
- Pruebas: se agregaron pruebas de visibilidad y filtros en créditos. Se validó el estado de reportes con pruebas puntuales.

---

## Detalle de cambios por archivo

### app/Http/Controllers/Api/ReportController.php
- Endpoints:
  - GET `/api/reports/payments` (filtros: `start_date`, `end_date`, `cobrador_id`; formatos: `pdf|html|json|excel`).
  - GET `/api/reports/credits` (filtros: `status`, `cobrador_id`, `client_id`; formatos: `pdf|html|json|excel`).
  - GET `/api/reports/users` (filtros: `role`, `client_category`; formatos: `pdf|html|json|excel`).
  - GET `/api/reports/balances` (filtros: `start_date`, `end_date`, `cobrador_id`; formatos: `pdf|html|json|excel`).
  - GET `/api/reports/types` (catálogo de reportes y sus filtros/formatos).
- Lógica:
  - Validación de parámetros de entrada.
  - Visibilidad por rol:
    - Cobrador: limita por `cobrador_id` del recurso (pagos/balances) o relaciones (créditos/usuarios).
    - Manager: usa `assigned_manager_id` y equipos (`cobradores` + clientes directos/indirectos).
  - Resúmenes:
    - Pagos: total de pagos, monto total, promedio, totales sin interés, intereses y faltante estimado por cuota.
    - Créditos: `total_credits`, `total_amount`, `active`, `completed`, `total_balance`, `pending_amount`.
    - Usuarios: total, agrupaciones por rol y categoría, `cobradores_count`, `managers_count`.
    - Balances: totales por columna y diferencia promedio.

### app/Exports/PaymentsExport.php
- Mapea columnas: ID, Fecha, Cobrador, Cliente, Monto, `payment_method`, Notas, Creación.
- Agrega fila de resumen con total de pagos, monto total y promedio.

### app/Exports/CreditsExport.php
- Cálculo de `Monto Pagado` como `max(0, total_amount - balance)` para consistencia.
- Incluye fila de resumen con totales y `Saldo Pendiente` (alias de `pending_amount`).

### app/Exports/UsersExport.php
- Usa `assignedManager` (relación existente) para la columna Manager.
- Fila de resumen: `Total de Usuarios`, `Cobradores`, `Managers`.

### app/Exports/BalancesExport.php
- Fila de resumen general con totales inicial, recaudado, prestado, final y diferencia promedio.

### app/Models/Payment.php
- Fillables y casts actualizados.
- Accesores/calculados:
  - `principal_portion`: estima la porción de capital del pago según composición de cuotas.
  - `interest_portion`: resto del pago luego de capital.
  - `remaining_for_installment`: saldo restante para completar la cuota asociada (si hay `installment_number`).
- Normalización de `installment_number` (0 cuando es `null`; mutador a `int|null`).
- Eventos de modelo `created`/`updated`: ajustan el `balance` del crédito y recalculan categoría de cliente.

### app/Models/CashBalance.php
- Casts de montos y fecha.
- Relación `cobrador()`.

### resources/views/reports/payments.blade.php
- Tabla con columnas de cuotas, estado, montos sin/con interés y faltante para cuota.
- Nota: actualmente la columna de método muestra `payment_type`. En Exports/JSON se usa `payment_method`. Recomendación: unificar a `payment_method` en HTML para mantener consistencia.

### database/migrations/2025_01_01_000004_create_payments_table.php
- Define `payment_method` como enum: `['cash', 'transfer', 'card', 'mobile_payment']`.
- Nota de compatibilidad: el validador en `PaymentController@store` acepta `['cash','transfer','check','other']`. Se recomienda unificar opciones (migración/validador/UI) en una próxima iteración para evitar rechazos o inconsistencias.

### routes/api.php
- Consolidación de rutas REST para usuarios, créditos, pagos, balances, mapa, dashboard y reportes.
- Rutas de reportes expuestas bajo `/api/reports/*`.

### tests/Feature/CreditVisibilityAndFiltersTest.php
- Cobertura de visibilidad para cobrador y manager.
- Filtros por frecuencia, montos y búsqueda (`name`, `ci`, `phone`).

---

## Endpoints de reportes: uso y ejemplos

- Pagos:
  - JSON: `GET /api/reports/payments?start_date=2025-01-01&end_date=2025-01-31&format=json`
  - Excel: `GET /api/reports/payments?format=excel`
  - PDF/HTML: omitir `format` para PDF por defecto o usar `format=html`.

- Créditos:
  - `GET /api/reports/credits?status=active&cobrador_id=123&format=json`

- Usuarios:
  - `GET /api/reports/users?role=cobrador&client_category=A&format=excel`

- Balances:
  - `GET /api/reports/balances?start_date=2025-03-01&end_date=2025-03-31&format=pdf`

El endpoint `GET /api/reports/types` devuelve metadatos de filtros y formatos admitidos.

---

## Control de visibilidad por rol

- Cobrador:
  - Reportes de pagos/balances: limitado a registros del propio cobrador.
  - Reporte de créditos/usuarios: clientes asignados y datos propios.
- Manager:
  - Ve datos propios, de su equipo (`cobradores` con `assigned_manager_id` = manager) y de clientes directos o indirectos (vía cobradores del equipo).

Nota: En `PaymentController@index` el filtro usa `received_by` para listados, mientras que en `ReportController@paymentsReport` la visibilidad se aplica por `cobrador_id`. Esta diferencia es intencional (quién cobró vs. a qué cobrador pertenece el pago). Ajustar según necesidades de negocio.

---

## Formatos y campos principales en Excel

- Pagos: `ID, Fecha, Cobrador, Cliente, Monto, payment_method, Notas, Creación` + fila de resumen.
- Créditos: `ID, Cliente, Cobrador, Monto Total, Monto Pagado, Saldo Pendiente, Estado, Creación, Vencimiento` + fila de resumen.
- Usuarios: `ID, Nombre, Email, Rol, Manager, Estado, Creación, Último Acceso` + fila de resumen.
- Balances: `Fecha, Cobrador, Inicial, Recaudado, Prestado, Final, Diferencia, Notas` + fila de resumen.

---

## Cómo verificar rápidamente

1. Autenticarse (Sanctum) y consumir `GET /api/reports/types` para ver filtros/formatos.
2. Probar `GET /api/reports/payments?format=json` y `format=excel` con y sin filtros.
3. Validar resúmenes (`pending_amount` en créditos, `cobradores_count` y `managers_count` en usuarios).
4. Ejecutar pruebas relacionadas (mínimas necesarias):
   - `php artisan test tests/Feature/CreditVisibilityAndFiltersTest.php`
   - (Opcional) pruebas de reportes si están disponibles: `php artisan test tests/Feature/ReportsTest.php`

---

## Notas y pendientes

- Unificación de `payment_method`:
  - Migración enum: `cash, transfer, card, mobile_payment`.
  - Validador `PaymentController@store`: `cash, transfer, check, other`.
  - Recomendación: alinear a un conjunto único de opciones y actualizar UI/validaciones.
- Vista HTML de pagos: cambiar `payment_type` por `payment_method` para evitar valores en blanco en esa columna.
- Rendimiento: para grandes volúmenes, considerar paginación/descarga asíncrona en reportes Excel.

---

## Changelog corto (orientado a producto)

- Correcciones de datos en reportes Excel y JSON.
- Control por rol aplicado a pagos, créditos, usuarios y balances.
- Resúmenes ampliados (totales, promedios y saldos).
- Nuevas métricas de cuotas e intereses por pago.
- Catálogo de reportes disponible por API.


---

## Actualización para Frontend (2025-09-22 10:55)

- Unificación de payment_method completada: valores admitidos `cash`, `transfer`, `card`, `mobile_payment`. Las validaciones de `PaymentController@store` y `@update` ya se actualizaron a este conjunto.
- Vista HTML de pagos: la columna ahora usa `payment_method` (antes `payment_type` en la vista), evitando valores vacíos.
- Registro de número de cuota: `installment_number` se asigna automáticamente al crear pagos y el backend puede dividir un pago en varias cuotas si el monto lo requiere. Esto ya está activo y probado.
- Flujos de caja integrados en pagos: si el usuario autenticado es cobrador, debe tener una caja abierta (CashBalance con status `open`) para la fecha del `payment_date`; de lo contrario, el backend devuelve 400 "Caja no abierta". Existe el endpoint idempotente `POST /api/cash-balances/open` para abrir la caja (ver guía frontend).
- Nueva guía para desarrollo Frontend (contratos + ejemplos + TypeScript): `documentaciones/FRONTEND_API_REPORTES_Y_PAGOS_2025-09-22.md` (sección 5.3 explica el flujo de caja + pagos).

Con esto, los pendientes anteriores quedan resueltos y el frontend ya puede integrar con seguridad los reportes y el flujo de pagos.
