# Gestión de Categorías de Cliente

Esta guía explica cómo gestionar las categorías de los clientes en el sistema, incluyendo:
- Conceptos y rangos de atraso.
- Endpoints disponibles (API) para listar, actualizar y consultar por categorías.
- Cómo funciona la actualización automática por atrasos en cuotas.
- Ejemplos de solicitudes.

Requisitos previos:
- Autenticación con Sanctum (Bearer Token) para todas las rutas bajo /api, excepto donde se indique lo contrario.
- Migraciones ejecutadas y tabla `client_categories` existente con sus rangos por defecto: A=0, B=1–3, C=4+.

## 1) Concepto y rangos

Las categorías de cliente se almacenan en:
- users.client_category: código de la categoría (A, B, C).
- Tabla `client_categories`: catálogo maestro con columnas:
  - code (A, B, C)
  - name (ej. "Cliente VIP")
  - description (opcional)
  - is_active (bool)
  - min_overdue_count (mínimo número de cuotas en atraso para pertenecer a la categoría)
  - max_overdue_count (máximo número de cuotas en atraso para pertenecer a la categoría; puede ser NULL para abierto superior)

Rangos por defecto:
- A: 0 atrasos (min=0, max=0)
- B: 1–3 atrasos (min=1, max=3)
- C: 4+ atrasos (min=4, max=NULL)

El sistema determina la categoría de un cliente en base al total de cuotas esperadas vs. pagadas en sus créditos activos.

## 2) Endpoints disponibles

Todas estas rutas requieren autenticación Sanctum y están definidas en `routes/api.php`.

1. Listar categorías activas
   - GET /api/client-categories
   - Respuesta: lista de objetos { code, name, description }
   - Notas: Si la tabla existe, se usa como fuente; si no, se devuelven categorías por defecto del modelo User.

2. Actualizar categoría de un cliente (manual)
   - PATCH /api/users/{client}/category
   - Body JSON: { "client_category": "A|B|C" } (debe existir en client_categories.code)
   - Validaciones: user con rol client; código válido en tabla.
   - Respuesta: usuario actualizado + mensaje amigable con el nombre de categoría.

3. Listar clientes por categoría
   - GET /api/clients/by-category?category=A|B|C
   - Respuesta: paginada, incluye relaciones básicas y mensaje con el nombre legible.

4. Estadísticas por categoría
   - GET /api/client-categories/statistics
   - Respuesta: arreglo con elementos { category_code, category_name, client_count } y un registro adicional para clientes sin categoría.

5. Actualización masiva de categorías
   - POST /api/clients/bulk-update-categories
   - Body JSON: { "updates": [ { "client_id": 1, "category": "B" }, ... ] }
   - Validaciones: cliente debe existir y tener rol client; código permitido.

## 3) Actualización automática por atrasos

- Cada vez que se crea o actualiza un pago (Payment::created/updated), el sistema recalcula la categoría del cliente con `User::recalculateCategoryFromOverdues()`.
- El total de atrasos se calcula con `User::getTotalOverdueInstallments()` sumando, por cada crédito activo del cliente:
  - expected = Credit::getExpectedInstallments() (según frecuencia y fechas)
  - completed = pagos con status=completed
  - overdue = max(0, expected - completed)
- Luego se determina la categoría buscando en `client_categories` la fila cuyo rango [min_overdue_count, max_overdue_count] contenga el valor.

Importante:
- Si la tabla no existe (entornos iniciales), el sistema conserva la categoría actual del usuario y usa los valores por defecto como respaldo.

## 4) Ejemplos de uso

1. Listar categorías

Request:
GET /api/client-categories
Authorization: Bearer {token}

Respuesta (ejemplo):
{
  "success": true,
  "data": [
    { "code": "A", "name": "Cliente VIP", "description": "Clientes preferenciales con excelente historial" },
    { "code": "B", "name": "Cliente Normal", "description": "Clientes estándar" },
    { "code": "C", "name": "Mal Cliente", "description": "Clientes con historial de mora o riesgo" }
  ],
  "message": "Categorías de clientes obtenidas exitosamente"
}

2. Actualizar categoría manualmente

Request:
PATCH /api/users/123/category
Authorization: Bearer {token}
Content-Type: application/json

{
  "client_category": "B"
}

Respuesta (resumen): success=true, data=usuario, message="Categoría del cliente actualizada a: Cliente Normal"

3. Listar clientes por categoría

Request:
GET /api/clients/by-category?category=C
Authorization: Bearer {token}

Respuesta: success=true, data=paginada con clientes, message="Clientes con categoría Mal Cliente obtenidos exitosamente"

4. Estadísticas

Request:
GET /api/client-categories/statistics
Authorization: Bearer {token}

Respuesta (ejemplo):
{
  "success": true,
  "data": [
    { "category_code": "A", "category_name": "Cliente VIP", "client_count": 10 },
    { "category_code": "B", "category_name": "Cliente Normal", "client_count": 42 },
    { "category_code": "C", "category_name": "Mal Cliente", "client_count": 7 },
    { "category_code": null, "category_name": "Sin categoría", "client_count": 3 }
  ],
  "message": "Estadísticas de categorías de clientes obtenidas exitosamente"
}

5. Actualización masiva

Request:
POST /api/clients/bulk-update-categories
Authorization: Bearer {token}
Content-Type: application/json

{
  "updates": [
    { "client_id": 12, "category": "A" },
    { "client_id": 15, "category": "C" }
  ]
}

Respuesta: success=true, data con resumen de actualizados y errores (si los hay).

## 5) Notas y buenas prácticas

- Preferir la actualización automática por atrasos; usar el PATCH manual sólo para correcciones administrativas.
- Los rangos pueden modificarse directamente en la tabla `client_categories` (min_overdue_count, max_overdue_count). Si desea CRUD por API para estas filas, solicite habilitar un controlador/endpoint de administración.
- Filtros comunes: en listados de usuarios puede filtrar por `client_category` y usar paginación estándar (`per_page`).
- Pruebas: ver `tests/Feature/ClientCategoryTest.php` y `tests/Feature/ClientCategoryOverdueRulesTest.php`.
