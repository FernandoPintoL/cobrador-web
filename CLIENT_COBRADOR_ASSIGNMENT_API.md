# API de Asignación de Clientes a Cobradores

Este documento describe los endpoints para gestionar la asignación de clientes a cobradores en el sistema.

## Estructura de Base de Datos

Se ha agregado un campo `assigned_cobrador_id` a la tabla `users` que permite:
- Asignar un cliente a un cobrador específico
- Mantener la relación uno a muchos (un cobrador puede tener muchos clientes)
- Consultas eficientes para obtener clientes por cobrador

## Endpoints Disponibles

### 1. GET /api/users/{cobrador}/clients

Obtiene todos los clientes asignados a un cobrador específico.

**Parámetros de consulta:**
- `search`: Búsqueda opcional por nombre o email del cliente
- `per_page`: Número de resultados por página (default: `15`)

**Ejemplo:**
```bash
GET /api/users/5/clients?search=Juan&per_page=10
```

**Respuesta:**
```json
{
    "success": true,
    "message": "Clientes asignados al cobrador Carlos López obtenidos exitosamente",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name": "Juan Pérez",
                "email": "juan@example.com",
                "phone": "123456789",
                "address": "Calle 123",
                "assigned_cobrador_id": 5,
                "roles": [{"name": "client"}]
            }
        ],
        "total": 1
    }
}
```

### 2. POST /api/users/{cobrador}/assign-clients

Asigna múltiples clientes a un cobrador.

**Parámetros requeridos:**
```json
{
    "client_ids": [1, 2, 3, 4]
}
```

**Ejemplo:**
```bash
POST /api/users/5/assign-clients
{
    "client_ids": [1, 2, 3, 4]
}
```

**Respuesta:**
```json
{
    "success": true,
    "message": "Se asignaron 4 clientes al cobrador Carlos López exitosamente",
    "data": [
        {
            "id": 1,
            "name": "Juan Pérez",
            "email": "juan@example.com",
            "assigned_cobrador_id": 5
        }
    ]
}
```

### 3. DELETE /api/users/{cobrador}/clients/{client}

Remueve la asignación de un cliente específico de un cobrador.

**Ejemplo:**
```bash
DELETE /api/users/5/clients/1
```

**Respuesta:**
```json
{
    "success": true,
    "message": "Cliente Juan Pérez removido del cobrador Carlos López exitosamente",
    "data": {
        "id": 1,
        "name": "Juan Pérez",
        "email": "juan@example.com",
        "assigned_cobrador_id": null
    }
}
```

### 4. GET /api/users/{client}/cobrador

Obtiene el cobrador asignado a un cliente específico.

**Ejemplo:**
```bash
GET /api/users/1/cobrador
```

**Respuesta (con cobrador asignado):**
```json
{
    "success": true,
    "message": "Cobrador asignado al cliente Juan Pérez obtenido exitosamente",
    "data": {
        "id": 5,
        "name": "Carlos López",
        "email": "carlos@example.com",
        "phone": "555123456",
        "roles": [{"name": "cobrador"}]
    }
}
```

**Respuesta (sin cobrador asignado):**
```json
{
    "success": true,
    "message": "El cliente no tiene un cobrador asignado",
    "data": null
}
```

## Ejemplos de Uso

### Para el Frontend

```javascript
// Obtener clientes de un cobrador
const response = await fetch('/api/users/5/clients', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});

// Asignar clientes a un cobrador
const response = await fetch('/api/users/5/assign-clients', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        client_ids: [1, 2, 3, 4]
    })
});

// Remover cliente de un cobrador
const response = await fetch('/api/users/5/clients/1', {
    method: 'DELETE',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});

// Obtener cobrador de un cliente
const response = await fetch('/api/users/1/cobrador', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});
```

### Para Testing

```bash
# Obtener clientes de un cobrador
curl -X GET "http://localhost:8000/api/users/5/clients" \
  -H "Authorization: Bearer {tu_token}" \
  -H "Content-Type: application/json"

# Asignar clientes a un cobrador
curl -X POST "http://localhost:8000/api/users/5/assign-clients" \
  -H "Authorization: Bearer {tu_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "client_ids": [1, 2, 3, 4]
  }'

# Remover cliente de un cobrador
curl -X DELETE "http://localhost:8000/api/users/5/clients/1" \
  -H "Authorization: Bearer {tu_token}" \
  -H "Content-Type: application/json"

# Obtener cobrador de un cliente
curl -X GET "http://localhost:8000/api/users/1/cobrador" \
  -H "Authorization: Bearer {tu_token}" \
  -H "Content-Type: application/json"
```

## Validaciones y Reglas

### **Para Asignar Clientes:**
1. El usuario debe ser un cobrador válido
2. Los clientes deben existir y tener rol de `client`
3. Se pueden asignar múltiples clientes a la vez
4. Si un cliente ya está asignado a otro cobrador, se reasigna

### **Para Remover Clientes:**
1. El usuario debe ser un cobrador válido
2. El cliente debe estar asignado al cobrador especificado
3. Al remover, el cliente queda sin cobrador asignado (`assigned_cobrador_id = null`)

### **Para Consultar:**
1. Se valida que el usuario sea del rol correcto (cobrador o cliente)
2. Se incluyen búsquedas y paginación donde corresponde
3. Se cargan las relaciones necesarias (roles, permisos)

## Casos de Uso Comunes

### **1. Dashboard del Cobrador**
```javascript
// Obtener todos los clientes asignados al cobrador logueado
const myClients = await fetch(`/api/users/${cobradorId}/clients`);
```

### **2. Asignación Masiva**
```javascript
// Asignar múltiples clientes a un cobrador
const assignClients = await fetch(`/api/users/${cobradorId}/assign-clients`, {
    method: 'POST',
    body: JSON.stringify({
        client_ids: selectedClientIds
    })
});
```

### **3. Reasignación de Clientes**
```javascript
// Remover cliente de un cobrador y asignarlo a otro
await fetch(`/api/users/${oldCobradorId}/clients/${clientId}`, {
    method: 'DELETE'
});

await fetch(`/api/users/${newCobradorId}/assign-clients`, {
    method: 'POST',
    body: JSON.stringify({
        client_ids: [clientId]
    })
});
```

### **4. Verificación de Asignación**
```javascript
// Verificar si un cliente tiene cobrador asignado
const cobrador = await fetch(`/api/users/${clientId}/cobrador`);
if (cobrador.data) {
    console.log(`Cliente asignado a: ${cobrador.data.name}`);
} else {
    console.log('Cliente sin cobrador asignado');
}
```

## Ventajas de esta Implementación

1. ✅ **Simplicidad**: Una sola tabla, relaciones directas
2. ✅ **Eficiencia**: Consultas rápidas sin joins complejos
3. ✅ **Flexibilidad**: Fácil reasignación de clientes
4. ✅ **Escalabilidad**: Funciona bien con muchos clientes por cobrador
5. ✅ **Compatibilidad**: No afecta el sistema de rutas existente
6. ✅ **Mantenibilidad**: Código simple y fácil de entender

## Notas Importantes

1. **Migración**: Se ejecutó la migración para agregar `assigned_cobrador_id` a la tabla `users`
2. **Relaciones**: Se agregaron las relaciones `assignedClients()` y `assignedCobrador()` al modelo User
3. **Validaciones**: Todos los endpoints incluyen validaciones de roles
4. **Paginación**: Los endpoints de listado incluyen paginación automática
5. **Búsqueda**: Se puede buscar por nombre o email en los clientes
6. **Autenticación**: Todos los endpoints requieren autenticación 