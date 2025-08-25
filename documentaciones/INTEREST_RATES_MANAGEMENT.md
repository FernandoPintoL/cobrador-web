# Gestión de Tasas de Interés (Interest Rates)

Esta guía documenta la nueva implementación para administrar las tasas de interés de los créditos en una tabla separada, con control de acceso por roles y su integración con el flujo de creación de créditos.

Fecha: 2025-08-20


## Objetivos
- Centralizar la configuración de tasas de interés en su propia tabla (`interest_rates`).
- Garantizar que solo `manager` o `admin` puedan crear/editar/eliminar tasas.
- Permitir que los créditos hagan referencia a una tasa activa o a una tasa específica seleccionada por `manager/admin`.
- Mantener un campo de respaldo (`interest_rate`) dentro de `credits` para denormalización y auditoría histórica.


## Modelo de Datos

### Tabla: interest_rates
Campos principales:
- `id` (PK)
- `name` (nullable): Nombre descriptivo de la tasa, p.ej. "Tasa por defecto".
- `rate` (decimal 5,2): Porcentaje, p.ej. 20.00 para 20%.
- `is_active` (boolean): Marca si la tasa es la activa del sistema.
- `created_by` (FK users.id, nullOnDelete)
- `updated_by` (FK users.id, nullOnDelete)
- timestamps

Migración: `database/migrations/2025_08_20_000001_create_interest_rates_table.php`

Modelo: `app/Models/InterestRate.php`
- Casts: `rate` como decimal(2), `is_active` boolean.
- Relaciones: `createdBy`, `updatedBy`.
- Utilidad: `InterestRate::getActive()` devuelve la última activa.

### Tabla: credits (ajustes)
- Nuevo campo: `interest_rate_id` (FK nullable a `interest_rates`).
- Ya existente: `interest_rate` (decimal), como valor numérico aplicado al crédito para auditoría/denormalización.

Migración: `database/migrations/2025_08_20_000002_add_interest_rate_id_to_credits_table.php`

Modelo: `app/Models/Credit.php`
- Fillable incluye `interest_rate_id`.
- Relación: `interestRate(): BelongsTo(InterestRate)`.


## Control de Acceso por Roles
- Solo usuarios con rol `manager` o `admin` pueden:
  - Crear tasas (`POST /api/interest-rates`)
  - Actualizar tasas (`PUT/PATCH /api/interest-rates/{id}`)
  - Eliminar tasas (`DELETE /api/interest-rates/{id}`)
- Cualquier usuario autenticado puede leer/listar tasas (`GET /api/interest-rates`, `GET /api/interest-rates/{id}`, `GET /api/interest-rates/active`).

La validación de roles se realiza en `app/Http/Controllers/Api/InterestRateController.php`.


## Endpoints de API
Base: autenticados con Sanctum, prefijo `/api`.

1) Listar tasas
- Método: GET `/api/interest-rates`
- Respuesta: arreglo de tasas ordenadas por `is_active` desc y `id` desc.

2) Ver tasa
- Método: GET `/api/interest-rates/{interestRate}`

3) Crear tasa (manager/admin)
- Método: POST `/api/interest-rates`
- Body JSON:
  - `name` (string|null)
  - `rate` (number requerido, 0–100)
  - `is_active` (boolean, opcional)
- Notas:
  - Si `is_active=true`, se desactivan las otras tasas activas.

4) Actualizar tasa (manager/admin)
- Método: PUT/PATCH `/api/interest-rates/{interestRate}`
- Body JSON opcional: `name`, `rate`, `is_active`
- Notas: si se envía `is_active=true`, se desactivan las demás activas.

5) Eliminar tasa (manager/admin)
- Método: DELETE `/api/interest-rates/{interestRate}`

6) Obtener tasa activa
- Método: GET `/api/interest-rates/active`

Rutas definidas en: `routes\api.php`.


## Integración con Créditos
Controlador: `app/Http/Controllers/Api/CreditController.php`

Escenarios clave:
- Cobrador (role `cobrador`) al crear crédito:
  - Ignora cualquier `interest_rate` o `interest_rate_id` enviado por el cliente/cliente HTTP.
  - Usa automáticamente la tasa activa (`InterestRate::getActive()`), si existe.
  - Se asignan: `interest_rate_id` y `interest_rate` (valor numérico) en el crédito.

- Manager/Admin al crear crédito:
  - Puede especificar `interest_rate_id` para vincular una tasa existente.
  - Si no se envía `interest_rate_id`, puede enviar `interest_rate` (valor numérico) directo.
  - Si no se envían ninguno de los dos, se intenta usar la tasa activa.

Esto se aplica en:
- `store(Request $request)` (creación normal de crédito)
- `storeInWaitingList(Request $request)` (creación en lista de espera)

Cálculos derivados en `storeInWaitingList`:
- `total_amount = amount * (1 + (interest_rate / 100))`
- `installment_amount` se calcula según frecuencia y rango de fechas si no se provee.


## Seeder por Defecto
`database/seeders/DatabaseSeeder.php`:
- Crea un usuario Admin por defecto.
- Crea una tasa activa por defecto:
  - `name`: "Tasa por defecto"
  - `rate`: 20.00
  - `is_active`: true
  - `created_by`: admin.id


## Ejemplos de Uso (cURL)

1) Crear tasa activa (manager/admin)
```
curl -X POST http://localhost:8000/api/interest-rates \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Promoción Agosto",
    "rate": 18.50,
    "is_active": true
  }'
```

2) Obtener tasa activa
```
curl -X GET http://localhost:8000/api/interest-rates/active \
  -H "Authorization: Bearer {TOKEN}"
```

3) Crear crédito como cobrador (usa tasa activa automáticamente)
```
curl -X POST http://localhost:8000/api/credits \
  -H "Authorization: Bearer {TOKEN_COBRADOR}" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 123,
    "amount": 1000.00,
    "balance": 0, 
    "frequency": "daily",
    "start_date": "2025-08-21",
    "end_date": "2025-09-13"
  }'
```
Nota: `balance` en el payload se mantiene por compatibilidad con la API actual, pero en flujos recomendados puede calcularse automáticamente del lado servidor según tus reglas.

4) Crear crédito en lista de espera como manager con una tasa específica
```
curl -X POST http://localhost:8000/api/credits/waiting-list \
  -H "Authorization: Bearer {TOKEN_MANAGER}" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 123,
    "cobrador_id": 45,
    "amount": 1500.00,
    "interest_rate_id": 2,
    "frequency": "weekly",
    "start_date": "2025-08-22",
    "end_date": "2025-10-03"
  }'
```


## Comportamiento y Reglas
- Solo puede haber una tasa activa efectiva. Cuando se crea/actualiza una tasa con `is_active=true`, el sistema desactiva las demás.
- En créditos, siempre se persisten ambos campos:
  - `interest_rate_id` (si existía tasa de catálogo)
  - `interest_rate` (número aplicado) para mantener auditoría histórica incluso si la tasa original cambia o se elimina.
- Cobradores no pueden forzar tasas distintas a la activa.


## Pasos de Migración/Actualización
1) Ejecutar migraciones:
```
php artisan migrate
```
2) Sembrar datos (opcional si aún no tienes roles/usuarios):
```
php artisan db:seed
```
Esto creará el admin por defecto y una tasa activa de 20%.

3) Revisar roles en tu entorno: asegúrate de tener usuarios con roles `admin`, `manager`, `cobrador`, `client` según tu flujo.


## Manejo de Errores Comunes
- 403 No autorizado al crear/editar/eliminar tasas: el usuario no tiene rol `manager` o `admin`.
- 422 Al crear/actualizar tasa: `rate` fuera de rango (0–100) o tipos inválidos.
- 404 `interest_rate_id` inexistente al crear créditos: valida que la tasa exista y sea accesible.


## Integración Frontend/Flutter
- Para formularios de creación de créditos:
  - Si el usuario es `manager/admin`, mostrar selector de tasa (cargar desde `/api/interest-rates`) y opción para usar la activa (`/api/interest-rates/active`).
  - Si el usuario es `cobrador`, no permitir cambiar la tasa; mostrar la activa en modo solo lectura.
- Notificar visualmente si se cambió la tasa activa para evitar inconsistencias en UI.


## Archivos Afectados (Resumen)
- Nuevos:
  - `database/migrations/2025_08_20_000001_create_interest_rates_table.php`
  - `database/migrations/2025_08_20_000002_add_interest_rate_id_to_credits_table.php`
  - `app/Models/InterestRate.php`
  - `app/Http/Controllers/Api/InterestRateController.php`
- Modificados:
  - `routes/api.php` (rutas interest-rates)
  - `app/Models/Credit.php` (fillable + relación `interestRate`)
  - `app/Http/Controllers/Api/CreditController.php` (lógica de selección de tasa por rol en `store` y `storeInWaitingList`)
  - `database/seeders/DatabaseSeeder.php` (crear tasa por defecto)


## Futuras Extensiones (Opcional)
- Historial de cambios de tasas con vigencias por fecha (periodos efectivos).
- Auditoría completa con Laravel Auditing/Activity log.
- Permisos con Spatie Permissions más granulares (p.ej., `manage interest rates`).
- Validar referencias: evitar eliminar una tasa si hay créditos recientes apuntando a ella, o permitir eliminación suave (soft deletes).
