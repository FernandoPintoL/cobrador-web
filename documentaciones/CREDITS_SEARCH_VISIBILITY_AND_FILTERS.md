# Créditos: Visibilidad y Filtros (Actualización 2025-08-31)

Este documento describe los cambios recientes implementados en los endpoints de búsqueda/listado de créditos para cumplir con los requerimientos:

- Un cobrador solo puede ver:
  - Créditos creados por él mismo (created_by = cobrador).
  - Créditos de clientes que tiene asignados (users con assigned_cobrador_id = cobrador).
- Un manager puede ver:
  - Créditos creados por él mismo (created_by = manager).
  - Créditos de sus clientes directos (assigned_manager_id = manager).
  - Créditos de clientes asignados a sus cobradores (cobradores con assigned_manager_id = manager).
- Soporte de filtros: frecuencias, rango de fechas (start_date y end_date) y montos (amount, total_amount, balance).
- Paginación configurable con per_page.

Fecha de implementación: 2025-08-31
Archivos impactados principales:
- app/Http/Controllers/Api/CreditController.php
- routes/api.php (rutas existentes, sin cambios de path)
- tests/Feature/CreditVisibilityAndFiltersTest.php (nuevos tests)

## Endpoints Afectados

1) GET /api/credits
- Propósito: Listado general de créditos según el rol del usuario autenticado.
- Visibilidad:
  - Cobrador: créditos creados por él o créditos de clientes asignados a él.
  - Manager: créditos creados por él o de clientes directos o de clientes de sus cobradores.
  - Admin: sin restricciones adicionales (hereda lógica previa del proyecto si aplica).
- Filtros soportados (query params):
  - client_id
  - status
  - search (por nombre del cliente)
  - frequency: uno o varios valores (daily, weekly, biweekly, monthly). Acepta lista separada por comas.
  - start_date_from, start_date_to (filtra por campo start_date)
  - end_date_from, end_date_to (filtra por campo end_date)
  - amount_min, amount_max (campo amount)
  - total_amount_min, total_amount_max (campo total_amount)
  - balance_min, balance_max (campo balance)
  - per_page (paginación, default 15)

2) GET /api/credits/client/{client}
- Propósito: Listar créditos de un cliente específico.
- Autorización:
  - Cobrador: solo si el cliente está asignado a ese cobrador.
  - Manager: solo si el cliente está asignado directamente al manager o si el cliente pertenece a un cobrador bajo ese manager.
- Filtros soportados: status, frequency, start_date_from/to, end_date_from/to, amount_min/max, balance_min/max, per_page (default 50).

3) GET /api/credits/cobrador/{cobrador}
- Propósito: Listar créditos de los clientes asignados a un cobrador específico.
- Autorización:
  - Admin/Manager: pueden consultar a cualquier cobrador (según la lógica previa de la app).
- Filtros soportados: search (por nombre del cliente), status, frequency, start_date_from/to, end_date_from/to, amount_min/max, balance_min/max, per_page (default 15).

4) GET /api/credits/{credit}/remaining-installments
- Sin cambios de reglas de negocio, pero se mantuvo la verificación de acceso por rol (un cobrador solo accede si el cliente del crédito le pertenece).

## Ejemplos de Uso

- Listar créditos visibles para un cobrador autenticado, filtrando por frecuencia múltiple y rango de montos:
  GET /api/credits?frequency=daily,weekly&amount_min=100&amount_max=300

- Listar créditos de un cliente para un manager, con rango de fechas:
  GET /api/credits/client/{clientId}?start_date_from=2025-01-01&start_date_to=2025-01-31

- Listar créditos de clientes de un cobrador (solo para admin/manager):
  GET /api/credits/cobrador/{cobradorId}?status=active&balance_min=50

- Paginación:
  GET /api/credits?per_page=50

## Detalles de Implementación

- CreditController@index:
  - Agrega condiciones OR para incluir créditos creados_por el usuario además de los de sus clientes (cobrador/manager).
  - Se añadieron filtros: frequency, rangos de fechas y montos, con soporte para listas separadas por comas.
  - Se añadió per_page con saneamiento básico.

- CreditController@getByClient:
  - Ahora recibe Request para aplicar filtros y paginación.
  - Autorización extendida para managers (directo o vía cobrador a su cargo).

- CreditController@getByCobrador:
  - Se añadieron los mismos filtros y per_page.

## Pruebas Automatizadas (Pest)

Archivo: tests/Feature/CreditVisibilityAndFiltersTest.php
- Cubre:
  - Un cobrador ve: créditos que creó y créditos de sus clientes asignados; no ve otros.
  - Un manager ve: créditos que creó y créditos de clientes directos y de cobradores a su cargo; no ve otros.
  - Filtros por frecuencia y montos; y ejemplo con rangos de fechas.

Para ejecutar solo estas pruebas:
  php artisan test tests/Feature/CreditVisibilityAndFiltersTest.php

## Notas de Compatibilidad
- No se cambiaron rutas ni payloads de respuesta base; se ampliaron capacidades de filtrado y se fortaleció la autorización.
- Si el frontend no refleja cambios, ejecutar:
  - npm run dev (o npm run build)
  - composer run dev (si existe en el proyecto)

## Checklist Rápido
- [x] Visibilidad por rol para cobradores y managers.
- [x] Filtros por frecuencia, fechas y montos.
- [x] Paginación con per_page.
- [x] Tests de regresión con Pest.
