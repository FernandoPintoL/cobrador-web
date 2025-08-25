# API de Filtrado de Usuarios

Este documento describe los endpoints disponibles para filtrar usuarios por roles en el sistema de cobranza.

## Endpoints Disponibles

### 1. GET /api/users (Mejorado)

El endpoint principal ahora soporta filtrado por roles mediante parámetros de consulta.

**Parámetros de consulta:**
- `roles`: Lista de roles separados por comas (ej: `client,manager,cobrador`)
- `role`: Un solo rol (para compatibilidad)
- `search`: Búsqueda por nombre o email
- `sort_by`: Campo para ordenar (default: `name`)
- `sort_order`: Orden ascendente o descendente (default: `asc`)
- `per_page`: Número de resultados por página (default: `15`)

**Ejemplos de uso:**

```bash
# Obtener todos los usuarios
GET /api/users

# Obtener solo clientes
GET /api/users?role=client

# Obtener clientes y cobradores
GET /api/users?roles=client,cobrador

# Buscar clientes por nombre
GET /api/users?role=client&search=Juan

# Obtener managers ordenados por email
GET /api/users?role=manager&sort_by=email&sort_order=desc

# Obtener cobradores con 10 por página
GET /api/users?role=cobrador&per_page=10
```

### 2. GET /api/users/by-roles

Endpoint específico para obtener usuarios por un rol específico.

**Parámetros:**
- `roles` (requerido): Un solo rol (`client`, `manager`, `cobrador`, `admin`)
- `search`: Búsqueda opcional por nombre o email
- `per_page`: Número de resultados por página (default: `15`)

**Ejemplos:**

```bash
# Obtener todos los clientes
GET /api/users/by-roles?roles=client

# Buscar cobradores por nombre
GET /api/users/by-roles?roles=cobrador&search=Carlos

# Obtener managers con 20 por página
GET /api/users/by-roles?roles=manager&per_page=20
```

### 3. GET /api/users/by-multiple-roles

Endpoint para obtener usuarios que tengan cualquiera de los roles especificados.

**Parámetros:**
- `roles` (requerido): Lista de roles separados por comas
- `search`: Búsqueda opcional por nombre o email
- `per_page`: Número de resultados por página (default: `15`)

**Ejemplos:**

```bash
# Obtener clientes y cobradores
GET /api/users/by-multiple-roles?roles=client,cobrador

# Obtener managers y cobradores
GET /api/users/by-multiple-roles?roles=manager,cobrador

# Buscar en clientes y managers
GET /api/users/by-multiple-roles?roles=client,manager&search=Ana
```

## Creación y Actualización de Usuarios

### POST /api/users - Crear Usuario

**Parámetros requeridos:**
- `name`: Nombre del usuario
- `email`: Email único
- `roles`: Array con al menos un rol

**Parámetros opcionales:**
- `password`: Contraseña (requerida para roles que no sean cliente)
- `phone`: Teléfono
- `address`: Dirección
- `profile_image`: Imagen de perfil

**Nota importante sobre contraseñas:**
- Para **clientes**: La contraseña es opcional. Si no se proporciona, se genera una contraseña temporal y el cliente no puede acceder al sistema por ahora.
- Para **otros roles** (admin, manager, cobrador): La contraseña es requerida.

**Ejemplos de creación:**

```bash
# Crear cliente sin contraseña (recomendado)
POST /api/users
{
    "name": "María García",
    "email": "maria@example.com",
    "phone": "987654321",
    "address": "Calle 123",
    "roles": ["client"]
}

# Crear cobrador con contraseña
POST /api/users
{
    "name": "Carlos López",
    "email": "carlos@example.com",
    "password": "password123",
    "phone": "555123456",
    "address": "Av. Principal 456",
    "roles": ["cobrador"]
}

# Crear manager con contraseña
POST /api/users
{
    "name": "Ana Rodríguez",
    "email": "ana@example.com",
    "password": "password123",
    "phone": "444987654",
    "address": "Centro Comercial 123",
    "roles": ["manager"]
}
```

### PUT /api/users/{id} - Actualizar Usuario

**Parámetros requeridos:**
- `name`: Nombre del usuario
- `email`: Email único

**Parámetros opcionales:**
- `phone`: Teléfono
- `address`: Dirección
- `profile_image`: Imagen de perfil
- `roles`: Array de roles

**Ejemplos de actualización:**

```bash
# Actualizar información básica
PUT /api/users/1
{
    "name": "Juan Pérez López",
    "email": "juan.lopez@example.com",
    "phone": "999888777",
    "address": "Nueva Dirección 456"
}

# Actualizar roles
PUT /api/users/1
{
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "roles": ["client", "cobrador"]
}
```

## Roles Disponibles

- `client`: Clientes del sistema
- `cobrador`: Cobradores que realizan las visitas
- `manager`: Gerentes que supervisan cobradores
- `admin`: Administradores del sistema

## Respuesta

Todos los endpoints devuelven una respuesta con la siguiente estructura:

```json
{
    "success": true,
    "message": "Mensaje descriptivo",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name": "Juan Pérez",
                "email": "juan@example.com",
                "phone": "123456789",
                "address": "Calle 123",
                "profile_image": "profile-images/123_avatar.jpg",
                "profile_image_url": "http://localhost:8000/storage/profile-images/123_avatar.jpg",
                "roles": [
                    {
                        "id": 1,
                        "name": "client",
                        "guard_name": "web"
                    }
                ],
                "permissions": []
            }
        ],
        "first_page_url": "...",
        "from": 1,
        "last_page": 1,
        "last_page_url": "...",
        "links": [...],
        "next_page_url": null,
        "path": "...",
        "per_page": 15,
        "prev_page_url": null,
        "to": 1,
        "total": 1
    }
}
```

## Autenticación

Todos los endpoints requieren autenticación mediante token Bearer. Incluye el header:

```
Authorization: Bearer {tu_token}
```

## Ejemplos de Uso Comunes

### Para el Frontend

```javascript
// Obtener solo clientes
const response = await fetch('/api/users?role=client', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});

// Crear cliente sin contraseña
const response = await fetch('/api/users', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        name: 'María García',
        email: 'maria@example.com',
        phone: '987654321',
        address: 'Calle 123',
        roles: ['client']
    })
});

// Crear cobrador con contraseña
const response = await fetch('/api/users', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        name: 'Carlos López',
        email: 'carlos@example.com',
        password: 'password123',
        phone: '555123456',
        address: 'Av. Principal 456',
        roles: ['cobrador']
    })
});
```

### Para Testing

```bash
# Test con curl - Crear cliente sin contraseña
curl -X POST "http://localhost:8000/api/users" \
  -H "Authorization: Bearer {tu_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "María García",
    "email": "maria@example.com",
    "phone": "987654321",
    "address": "Calle 123",
    "roles": ["client"]
  }'

# Test con curl - Crear cobrador con contraseña
curl -X POST "http://localhost:8000/api/users" \
  -H "Authorization: Bearer {tu_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Carlos López",
    "email": "carlos@example.com",
    "password": "password123",
    "phone": "555123456",
    "address": "Av. Principal 456",
    "roles": ["cobrador"]
  }'

# Test con Postman
POST http://localhost:8000/api/users
Headers:
  Authorization: Bearer {tu_token}
  Content-Type: application/json
Body:
{
    "name": "María García",
    "email": "maria@example.com",
    "phone": "987654321",
    "address": "Calle 123",
    "roles": ["client"]
}
```

## Notas Importantes

1. **Paginación**: Todos los endpoints incluyen paginación automática
2. **Búsqueda**: La búsqueda funciona tanto en nombre como en email
3. **Ordenamiento**: Por defecto se ordena por nombre ascendente
4. **Roles múltiples**: Un usuario puede tener múltiples roles
5. **Validación**: Los roles se validan antes de procesar la consulta
6. **Compatibilidad**: El endpoint principal mantiene compatibilidad con el uso anterior
7. **Contraseñas para clientes**: Los clientes pueden crearse sin contraseña ya que no interactúan con el sistema por ahora
8. **Contraseñas para otros roles**: Admin, manager y cobrador requieren contraseña obligatoria 