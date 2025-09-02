# Frontend: ¿Cómo enviar los datos al backend para búsquedas/listados de créditos?

Este documento explica cómo el frontend (React + Inertia v2 o React con Axios) debe enviar los filtros y parámetros al backend para los endpoints de créditos actualizados.

Los endpoints relevantes (protegidos por Sanctum) aceptan filtros vía query string:
- GET /api/credits
- GET /api/credits/client/{clientId}
- GET /api/credits/cobrador/{cobradorId}

Parámetros de filtro soportados (como query params):
- status
- search (por nombre del cliente; solo en /api/credits y /api/credits/cobrador/{id})
- client_id (solo en /api/credits, si se desea)
- cobrador_id (solo en /api/credits; permitido para admin/manager)
- frequency: acepta uno o varios valores entre daily | weekly | biweekly | monthly. Si son varios, envíalos separados por comas (ej: daily,weekly)
- start_date_from, start_date_to (YYYY-MM-DD)
- end_date_from, end_date_to (YYYY-MM-DD)
- amount_min, amount_max (número)
- total_amount_min, total_amount_max (número)
- balance_min, balance_max (número)
- per_page (paginación; default 15 en /api/credits y /api/credits/cobrador, 50 en /api/credits/client/{id})
- page (paginación; número de página)

Importante:
- El backend está detrás de Sanctum. En una SPA con sesión de Laravel (cookie), asegúrate de haber hecho el flujo de auth (login via /api/login) y, si corresponde, haber obtenido la cookie CSRF previamente con /sanctum/csrf-cookie.
- Si usas token personal (Bearer), adjunta el header Authorization: Bearer <token>.
- El formato de las fechas debe ser YYYY-MM-DD.
- frequency puede enviarse como string con comas o como array transformado a string antes de enviar.

---

## Ejemplos con Axios (recomendado para /api/*)

Instancia base (opcional):

```ts
// resources/js/lib/api.ts
import axios from 'axios'

export const api = axios.create({
  baseURL: '/api',
  withCredentials: true, // necesario si usas sesión (cookie) de Sanctum
})
```

Listado general con filtros y paginación:

```ts
import { api } from '@/lib/api'

async function fetchCredits() {
  const params = {
    frequency: ['daily', 'weekly'].join(','),
    amount_min: 100,
    amount_max: 300,
    start_date_from: '2025-01-01',
    start_date_to: '2025-01-31',
    status: 'active',
    per_page: 50,
    page: 1,
  }

  const { data } = await api.get('/credits', { params })
  // data.data contiene el objeto de paginación de Laravel (según tu sendResponse)
  // Normalmente: data.data.data => items, data.data.current_page, data.data.last_page, etc.
  return data
}
```

Por cliente específico:

```ts
async function fetchCreditsByClient(clientId: number, filters: Record<string, any> = {}) {
  const params = {
    frequency: filters.frequency?.join(',') ?? undefined,
    start_date_from: filters.startDateFrom,
    start_date_to: filters.startDateTo,
    amount_min: filters.amountMin,
    amount_max: filters.amountMax,
    balance_min: filters.balanceMin,
    balance_max: filters.balanceMax,
    status: filters.status,
    per_page: filters.perPage ?? 50,
    page: filters.page ?? 1,
  }
  const { data } = await api.get(`/credits/client/${clientId}`, { params })
  return data
}
```

Por cobrador (solo admin/manager):

```ts
async function fetchCreditsByCobrador(cobradorId: number, filters: Record<string, any> = {}) {
  const params = {
    search: filters.search,
    frequency: filters.frequency?.join(','),
    start_date_from: filters.startDateFrom,
    start_date_to: filters.startDateTo,
    amount_min: filters.amountMin,
    amount_max: filters.amountMax,
    balance_min: filters.balanceMin,
    balance_max: filters.balanceMax,
    status: filters.status,
    per_page: filters.perPage ?? 15,
    page: filters.page ?? 1,
  }
  const { data } = await api.get(`/credits/cobrador/${cobradorId}`, { params })
  return data
}
```

Puntos clave:
- Usa params de Axios para que construya el querystring correctamente.
- Si frequency es un array en el estado del UI, conviértelo a string con comas.
- Verifica la forma de la respuesta: muchas APIs en este proyecto responden como `{ success, message?, data }`. Cuando `data` es un paginador Laravel, sus items suelen venir en `data.data`.

---

## Ejemplos con @inertiajs/react (router.visit)

Para páginas Inertia (rutas web), puedes usar `router.visit()` para actualizar la URL con filtros, útil si tienes una pantalla Inertia que consume estos endpoints a través de un controlador web. Para endpoints pura API (/api/*), prefiere Axios como arriba.

```tsx
import { router } from '@inertiajs/react'

function onFilterChange(filters: any) {
  router.visit(route('credits.index'), { // O la ruta web que renderice la página
    method: 'get',
    data: {
      frequency: filters.frequency?.join(','),
      start_date_from: filters.startDateFrom,
      start_date_to: filters.startDateTo,
      amount_min: filters.amountMin,
      amount_max: filters.amountMax,
      per_page: filters.perPage,
      page: filters.page,
    },
    preserveState: true,
    preserveScroll: true,
  })
}
```

Nota: `route('credits.index')` se refiere a una ruta web nombrada que devuelva una página Inertia. Las rutas API actuales están en `routes/api.php` y típicamente se consumen con Axios/fetch.

---

## Manejo de autenticación con Sanctum

- SPA con sesión (cookies):
  1) GET `/sanctum/csrf-cookie` una vez al inicio.
  2) POST `/api/login` con credenciales.
  3) A partir de ahí, Axios con `{ withCredentials: true }` enviará la cookie de sesión.

- Tokens personales (móvil o no-SPA):
  - Incluye `Authorization: Bearer <token>` en cada solicitud.

---

## Ejemplos con curl (verificación rápida)

Listado general filtrando frecuencia y montos:

```bash
curl -G "http://localhost:8000/api/credits" \
  --data-urlencode "frequency=daily,weekly" \
  --data-urlencode "amount_min=100" \
  --data-urlencode "amount_max=300" \
  --cookie "XSRF-TOKEN=...; laravel_session=..."
```

Por cliente con rango de fechas y paginación:

```bash
curl -G "http://localhost:8000/api/credits/client/123" \
  --data-urlencode "start_date_from=2025-01-01" \
  --data-urlencode "start_date_to=2025-01-31" \
  --data-urlencode "per_page=50" \
  --data-urlencode "page=2" \
  -H "Authorization: Bearer <token>"
```

---

## Forma de la respuesta (paginación Laravel)

Según el helper `sendResponse`, la carga útil suele ser:

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [ { /* credit */ }, ... ],
    "per_page": 15,
    "total": 123,
    "last_page": 9,
    // ...otros campos de la paginación
  }
}
```

En React, accederías a `res.data.data.data` para los ítems, y `res.data.data.current_page`, `res.data.data.last_page`, etc., para la paginación.

---

## Resumen
- Envía los filtros como query params.
- frequency: string con comas cuando hay múltiples.
- Usa formato de fecha YYYY-MM-DD.
- Controla `per_page` y `page` para paginación.
- Con Sanctum, usa cookies (SPA) o Bearer Token (móvil).
- Para /api/* usa Axios/fetch; reserva router.visit para rutas web de páginas Inertia.
