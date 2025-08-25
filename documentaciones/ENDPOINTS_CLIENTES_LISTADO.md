# Endpoints para Listar Clientes (Cobrador y Manager)

Fecha: 2025-08-24 14:02

Este documento describe los endpoints disponibles para obtener listados de clientes desde el punto de vista de:
- Cobrador: sus clientes asignados.
- Manager: sus clientes directos, y el total de sus clientes (directos + indirectos a través de sus cobradores).

Todos los endpoints requieren autenticación con Laravel Sanctum.

Autenticación:
- Header: Authorization: Bearer {token}
- Ruta para obtener token: POST /api/login

---

1) Cobrador: Listar sus clientes asignados
- Método: GET /api/users/{cobrador}/clients
- Controlador: UserController@getClientsByCobrador
- Descripción: Retorna los clientes que tienen assigned_cobrador_id = {cobrador}.

Parámetros de ruta:
- cobrador (int): ID del usuario con rol "cobrador".

Query params opcionales:
- search (string): Filtra por id, name, email, phone, ci, client_category (coincidencia parcial).
- per_page (int): Tamaño de página (por defecto 15).

Respuesta (200): Paginada con data de usuarios e includes básicos.
Campos destacados por cliente:
- id, name, email, phone, ci, client_category
- relationships incluidos: roles, permissions

Ejemplo cURL:
```
curl -X GET "http://localhost/api/users/45/clients?search=ana&per_page=20" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer ${TOKEN}"
```

Nota de permisos:
- El endpoint valida que el usuario de la ruta tenga rol "cobrador". En el diseño actual no se restringe que el solicitante sea el mismo cobrador, pero se asume uso desde el propio cobrador o un admin/manager en el backend/Panel. Si necesitas endurecer esto, podemos agregar verificación adicional.

---

2) Manager: Listar clientes asignados directamente
- Método: GET /api/users/{manager}/clients-direct
- Controlador: UserController@getClientsByManager
- Descripción: Retorna únicamente los clientes con assigned_manager_id = {manager}.

Parámetros de ruta:
- manager (int): ID del usuario con rol "manager".

Query params opcionales:
- search (string): Filtra por id, name, email, phone, ci, client_category.
- per_page (int): Tamaño de página (por defecto 15).

Respuesta (200): Paginada.
Campos destacados por cliente:
- id, name, email, phone, ci, client_category
- relationships incluidos: roles, permissions

Ejemplo cURL:
```
curl -X GET "http://localhost/api/users/7/clients-direct?search=vip&per_page=10" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer ${TOKEN}"
```

---

3) Manager: Listar TODOS sus clientes (directos + indirectos)
- Método: GET /api/users/{manager}/manager-clients
- Controlador: UserController@getAllClientsByManager
- Descripción: Retorna clientes que cumplen cualquiera:
  - assigned_manager_id = {manager} (directos), o
  - assigned_cobrador_id IN (cobradores asignados al manager) (indirectos)

Parámetros de ruta:
- manager (int): ID del usuario con rol "manager".

Query params opcionales:
- search (string): Filtra por id, name, email, phone, ci, client_category.
- per_page (int): Tamaño de página (por defecto 15).

Respuesta (200): Paginada. Además de los campos habituales, este endpoint agrega metadatos por cada cliente:
- assignment_type: "direct" | "through_cobrador"
- cobrador_name: nombre del cobrador si viene por relación indirecta; null si es directo
- relationships incluidos: roles, permissions, assignedCobrador, assignedManagerDirectly

Ejemplo cURL:
```
curl -X GET "http://localhost/api/users/7/manager-clients?search=carla&per_page=50" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer ${TOKEN}"
```

Ejemplo de payload (parcial):
```
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 123,
        "name": "Carla López",
        "email": "carla@example.com",
        "phone": "70000001",
        "ci": "1234567",
        "client_category": "A",
        "assignment_type": "through_cobrador",
        "cobrador_name": "Juan Pérez",
        "assigned_cobrador": { "id": 45, "name": "Juan Pérez" },
        "assigned_manager_directly": null
      }
    ],
    "per_page": 50,
    "total": 1
  },
  "message": "Todos los clientes del manager Manager X obtenidos exitosamente"
}
```

Notas de uso y permisos:
- Se valida que el usuario de la ruta tenga rol "manager". En el diseño actual, la identidad del solicitante no se limita; se asume uso por el propio manager o por admin. Si requieres restringir que solo el manager dueño del ID pueda consultar, se puede endurecer con una verificación Auth.
- Orden: por nombre ascendente.

---

Preguntas frecuentes
- ¿Puedo filtrar por categoría A/B/C? Sí, usando search con el código de categoría (A, B, C); para filtros avanzados por categoría también tienes /api/clients/by-category.
- ¿Qué paginación usan? Todos estos endpoints devuelven LengthAwarePaginator con per_page configurable.
- ¿Qué roles pueden llamar? Cualquier usuario autenticado puede llamar en el estado actual del código siempre que el usuario de la ruta tenga el rol adecuado (cobrador/manager). Se recomienda que el frontend sólo permita a cada rol consultar sus propios recursos. Si necesitas políticas más estrictas, indícalo y lo agregamos.

---

Integración rápida (Flutter/JS)
- Añade el token en el header Authorization.
- Construye la URL con el ID del cobrador o manager (usualmente el user.id del perfil autenticado).
- Usa search y per_page según tu UI.

Ejemplo JS fetch (manager clientes totales):
```
fetch(`/api/users/${managerId}/manager-clients?search=${encodeURIComponent(term)}&per_page=20`, {
  headers: { Authorization: `Bearer ${token}` }
}).then(r => r.json())
  .then(json => console.log(json.data.data));
```
