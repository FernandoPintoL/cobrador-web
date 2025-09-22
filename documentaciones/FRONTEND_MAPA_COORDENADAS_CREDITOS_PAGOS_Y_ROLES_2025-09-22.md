# Documentación Frontend: Mapa de Clientes — Coordenadas, Créditos, Pagos y Roles

Este documento describe cómo construir en el frontend (Inertia + React) la experiencia para:
- Visualizar las coordenadas de cada cliente en un mapa.
- Mostrar información clave de créditos y pagos por cliente.
- Respetar las reglas de visibilidad según el rol del usuario (admin, manager, cobrador).

Las funcionalidades se basan en los endpoints expuestos por el backend (MapController) y ya definidos en `routes/api.php`.


## Roles y visibilidad

- Admin: puede ver todos los clientes y usar el filtro manual por cobrador (`cobrador_id`).
- Manager: puede ver sus clientes directos y los clientes de sus cobradores asignados; también puede filtrar por `cobrador_id`.
- Cobrador: solo ve los clientes que tiene asignados. No debe ver ni usar el filtro manual por cobrador.

Los endpoints ya aplican estos filtros en el backend; el frontend solo debe adaptar la UI (mostrar/ocultar filtros) según el rol reportado por el backend.


## Endpoints disponibles

Base URL: `/api`

1) Coordenadas de clientes (ligero, ideal para marcadores del mapa)
- GET `/api/map/coordinates`
- Query opcionales:
  - `cobrador_id` (solo admin/manager): filtra clientes de un cobrador específico.
- Respuesta:
```json
{
  "success": true,
  "data": {
    "total_clients": 12,
    "clients": [
      {
        "id": 101,
        "name": "Juan Pérez",
        "coordinates": { "latitude": -12.0464, "longitude": -77.0428 },
        "address": "Av. Principal 123",
        "phone": "+51 999 999 999"
      }
    ],
    "user_role": "manager",
    "filtered_by_role": "manager"
  },
  "message": "Coordenadas de clientes obtenidas exitosamente"
}
```

2) Clientes con ubicación + créditos y pagos (detallado)
- GET `/api/map/clients`
- Query opcionales:
  - `status`: `overdue` | `pending` | `paid` (filtra por estado de pagos)
  - `cobrador_id` (solo admin/manager)
- Respuesta (por cliente):
```json
{
  "id": 101,
  "name": "Juan Pérez",
  "phone": "+51 999 999 999",
  "address": "Av. Principal 123",
  "location": null,
  "overall_status": "pending",
  "total_balance": 450.50,
  "active_credits_count": 1,
  "overdue_payments_count": 0,
  "pending_payments_count": 2,
  "paid_payments_count": 5,
  "credits": [
    {"id": 1, "amount": 1000, "balance": 450.5, "status": "active", "start_date": "2025-09-01", "end_date": "2026-03-01"}
  ],
  "recent_payments": [
    {"id": 10, "amount": 50, "payment_date": "2025-09-20", "status": "pending", "payment_method": "cash"}
  ]
}
```

3) Estadísticas para el mapa (resúmenes)
- GET `/api/map/stats`
- Query opcional: `cobrador_id` (solo admin/manager)
- Respuesta:
```json
{
  "success": true,
  "data": {
    "total_clients": 120,
    "clients_with_location": 110,
    "clients_without_location": 10,
    "overdue_clients": 24,
    "pending_clients": 30,
    "paid_clients": 66,
    "total_balance": 12345.67
  }
}
```

4) Clientes por área (cuadro visible en el mapa)
- GET `/api/map/clients-by-area`
- Query requeridos: `north`, `south`, `east`, `west` (números)
- Respuesta (por cliente):
```json
{"id": 101, "name": "Juan Pérez", "location": null, "total_balance": 450.5, "has_overdue": false, "has_pending": true}
```

5) Rutas de cobradores con sus clientes
- GET `/api/map/cobrador-routes`
- Respuesta (simplificada):
```json
{
  "id": 21,
  "name": "Cobrador 1",
  "routes": [
    {
      "id": 5,
      "name": "Ruta A",
      "description": "Centro",
      "clients": [
        {"id": 101, "name": "Juan Pérez", "location": null, "total_balance": 450.5, "has_overdue": false, "has_pending": true}
      ]
    }
  ]
}
```


## Recomendación de consumo en el frontend

- Para renderizar marcadores en el mapa: usar `/api/map/coordinates` por ser más liviano.
- Para el panel lateral o modal de detalle de cliente: usar `/api/map/clients` y cruzar por `id` del cliente para mostrar créditos y pagos recientes.
- Para filtros rápidos por estado: volver a llamar `/api/map/clients` con `status` = `overdue | pending | paid`.
- Para dashboards o resúmenes de la vista de mapa: usar `/api/map/stats`.
- Para selecciones en base a la ventana visible del mapa: usar `/api/map/clients-by-area` con los bounds actuales del mapa.


## UI: estados y colores sugeridos

- Estado general del cliente (`overall_status`):
  - `overdue`: rojo (alerta)
  - `pending`: amarillo (pendiente)
  - `paid`: verde (al día)
- Mostrar contadores: `active_credits_count`, `overdue_payments_count`, `pending_payments_count`, `paid_payments_count` como chips o badges.
- En móvil, priorizar: nombre, estado y balance; el resto bajo un acordeón.


## Permisos en la interfaz

- Si `user_role` = `admin`: mostrar selector de `cobrador` (dropdown) para filtrar (envía `cobrador_id`).
- Si `user_role` = `manager`: mostrar selector `cobrador` (sus asignados) para filtrar.
- Si `user_role` = `cobrador`: ocultar selector de cobrador. Todos los endpoints ya devuelven solo sus clientes.

Nota: `user_role` también llega en `/api/map/coordinates`. Alternativamente, si ya cuentan con el rol en props de Inertia, usen eso como fuente de verdad.


## Ejemplos con React + Inertia

Ejemplo: cargar coordenadas para marcadores del mapa (con cancelación):

```tsx
import { useEffect, useState } from 'react'

export function useClientCoordinates(cobradorId?: number) {
  const [data, setData] = useState<{ clients: any[]; user_role?: string } | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const controller = new AbortController()
    const params = new URLSearchParams()
    if (cobradorId) params.set('cobrador_id', String(cobradorId))

    setLoading(true)
    fetch(`/api/map/coordinates?${params.toString()}`, { signal: controller.signal })
      .then(async (r) => {
        const json = await r.json()
        if (!json.success) throw new Error(json.message || 'Error')
        setData(json.data)
      })
      .catch((e) => {
        if (e.name !== 'AbortError') setError(e.message)
      })
      .finally(() => setLoading(false))

    return () => controller.abort()
  }, [cobradorId])

  return { data, loading, error }
}
```

Ejemplo: detalle de cliente para panel lateral (tomando la lista detallada y buscando por id):

```tsx
import { useEffect, useMemo, useState } from 'react'

export function useClientDetails(status?: 'overdue' | 'pending' | 'paid', cobradorId?: number) {
  const [clients, setClients] = useState<any[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const controller = new AbortController()
    const params = new URLSearchParams()
    if (status) params.set('status', status)
    if (cobradorId) params.set('cobrador_id', String(cobradorId))

    setLoading(true)
    fetch(`/api/map/clients?${params.toString()}`, { signal: controller.signal })
      .then(async (r) => {
        const json = await r.json()
        if (!json.success) throw new Error(json.message || 'Error')
        setClients(json.data)
      })
      .catch((e) => {
        if (e.name !== 'AbortError') setError(e.message)
      })
      .finally(() => setLoading(false))

    return () => controller.abort()
  }, [status, cobradorId])

  const getById = (id: number) => clients.find((c) => c.id === id)

  return { clients, getById, loading, error }
}
```

Sugerencia de flujo:
- Cargar `/api/map/coordinates` para marcadores.
- Al hacer click en un marcador, usar `getById(marker.clientId)` del hook de detalles; si no existe en memoria, opcionalmente disparar una carga puntual del endpoint detallado y cachear.


## Vacíos y estados de carga

- Mientras llegan coordenadas: mostrar skeleton del mapa o spinners sobre el lienzo.
- Si no hay clientes: mostrar un estado vacío con CTA para “Refrescar” o “Cambiar filtros”.
- Si faltan coordenadas: indicar "Cliente sin ubicación registrada" y ofrecer acción para registrar ubicación (si existe esa pantalla en el proyecto).


## Errores y resiliencia

- Manejar `success=false` o códigos HTTP != 200 mostrando un toast con `message` del backend.
- Implementar reintentos (1–2) solo en lecturas críticas como `/api/map/coordinates`.
- Usar `AbortController` para evitar fugas de memoria cuando el usuario navega rápido entre vistas.


## Notas de implementación

- Si usan Ziggy, pueden generar URLs por nombre de ruta: `route('api.map.coordinates')`, `route('api.map.clients')`, etc.
- Las propiedades `status` de pagos admiten: `overdue`, `pending`, `paid`.
- `overall_status` del cliente se deriva de sus pagos: `overdue` > `pending` > `paid`.
- `total_balance` proviene de la suma de `credits.balance` activos.


## Checklist UI (resumen)

- [ ] Mapa con marcadores desde `/api/map/coordinates`.
- [ ] Panel lateral/modal con detalles (créditos activos, pagos recientes, balances).
- [ ] Filtros por estado (`status`) y por cobrador (`cobrador_id` para admin/manager).
- [ ] Resumen superior con `/api/map/stats`.
- [ ] Carga por área visible con `/api/map/clients-by-area` (opcional si necesitan optimizar grandes volúmenes).
- [ ] Comportamientos por rol: mostrar/ocultar filtros y acciones conforme a `user_role`.


## Glosario de campos clave

- `coordinates.latitude`, `coordinates.longitude`: número (float) de ubicación del cliente.
- `overall_status`: estado agregado del cliente (por pagos).
- `credits[*]`: créditos activos con `amount`, `balance`, `status`, `start_date`, `end_date`.
- `recent_payments[*]`: últimos pagos con `amount`, `payment_date`, `status`, `payment_method`.
