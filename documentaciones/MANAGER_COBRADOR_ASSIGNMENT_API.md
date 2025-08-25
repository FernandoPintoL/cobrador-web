# API de Asignación de Cobradores a Managers

Este documento describe los endpoints para gestionar la asignación de cobradores a managers en el sistema.

## Estructura de Base de Datos

Se ha agregado un campo `assigned_manager_id` a la tabla `users` que permite:
- Asignar un cobrador a un manager específico
- Mantener la relación uno a muchos (un manager puede tener muchos cobradores)
- Consultas eficientes para obtener cobradores por manager
- Crear una jerarquía completa: Manager → Cobrador → Cliente

## Jerarquía del Sistema

```
┌─────────────┐
│   MANAGER   │ (gestiona cobradores)
└─────────────┘
       │
       ▼
┌─────────────┐
│  COBRADOR   │ (gestiona clientes, asignado a un manager)
└─────────────┘
       │
       ▼
┌─────────────┐
│   CLIENTE   │ (asignado a un cobrador)
└─────────────┘
```

## Endpoints Disponibles

### 1. GET /api/users/{manager}/cobradores

Obtiene todos los cobradores asignados a un manager específico.

**Parámetros de consulta:**
- `search`: Búsqueda opcional por nombre o email del cobrador
- `per_page`: Número de resultados por página (default: `15`)

**Ejemplo:**
```bash
GET /api/users/3/cobradores?search=Carlos&per_page=10
```

**Respuesta:**
```json
{
    "success": true,
    "message": "Cobradores asignados al manager Ana García obtenidos exitosamente",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 5,
                "name": "Carlos López",
                "email": "carlos@example.com",
                "phone": "555123456",
                "address": "Zona Norte",
                "assigned_manager_id": 3,
                "roles": [{"name": "cobrador"}]
            }
        ],
        "total": 1
    }
}
```

### 2. POST /api/users/{manager}/assign-cobradores

Asigna múltiples cobradores a un manager.

**Parámetros requeridos:**
```json
{
    "cobrador_ids": [5, 6, 7, 8]
}
```

**Ejemplo:**
```bash
POST /api/users/3/assign-cobradores
{
    "cobrador_ids": [5, 6, 7, 8]
}
```

**Respuesta:**
```json
{
    "success": true,
    "message": "Se asignaron 4 cobradores al manager Ana García exitosamente",
    "data": [
        {
            "id": 5,
            "name": "Carlos López",
            "email": "carlos@example.com",
            "assigned_manager_id": 3
        }
    ]
}
```

### 3. DELETE /api/users/{manager}/cobradores/{cobrador}

Remueve la asignación de un cobrador específico de un manager.

**Ejemplo:**
```bash
DELETE /api/users/3/cobradores/5
```

**Respuesta:**
```json
{
    "success": true,
    "message": "Cobrador Carlos López removido del manager Ana García exitosamente",
    "data": {
        "id": 5,
        "name": "Carlos López",
        "email": "carlos@example.com",
        "assigned_manager_id": null
    }
}
```

### 4. GET /api/users/{cobrador}/manager

Obtiene el manager asignado a un cobrador específico.

**Ejemplo:**
```bash
GET /api/users/5/manager
```

**Respuesta (con manager asignado):**
```json
{
    "success": true,
    "message": "Manager asignado al cobrador Carlos López obtenido exitosamente",
    "data": {
        "id": 3,
        "name": "Ana García",
        "email": "ana@example.com",
        "phone": "555654321",
        "roles": [{"name": "manager"}]
    }
}
```

**Respuesta (sin manager asignado):**
```json
{
    "success": true,
    "message": "El cobrador no tiene un manager asignado",
    "data": null
}
```

## Ejemplos de Uso

### Para el Frontend

```javascript
// Obtener cobradores de un manager
const response = await fetch('/api/users/3/cobradores', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});

// Asignar cobradores a un manager
const response = await fetch('/api/users/3/assign-cobradores', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        cobrador_ids: [5, 6, 7, 8]
    })
});

// Remover cobrador de un manager
const response = await fetch('/api/users/3/cobradores/5', {
    method: 'DELETE',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});

// Obtener manager de un cobrador
const response = await fetch('/api/users/5/manager', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});
```

### Para Testing

```bash
# Obtener cobradores de un manager
curl -X GET "http://localhost:8000/api/users/3/cobradores" \
  -H "Authorization: Bearer {tu_token}" \
  -H "Content-Type: application/json"

# Asignar cobradores a un manager
curl -X POST "http://localhost:8000/api/users/3/assign-cobradores" \
  -H "Authorization: Bearer {tu_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "cobrador_ids": [5, 6, 7, 8]
  }'

# Remover cobrador de un manager
curl -X DELETE "http://localhost:8000/api/users/3/cobradores/5" \
  -H "Authorization: Bearer {tu_token}" \
  -H "Content-Type: application/json"

# Obtener manager de un cobrador
curl -X GET "http://localhost:8000/api/users/5/manager" \
  -H "Authorization: Bearer {tu_token}" \
  -H "Content-Type: application/json"
```

## Validaciones y Reglas

### **Para Asignar Cobradores:**
1. El usuario debe ser un manager válido
2. Los cobradores deben existir y tener rol de `cobrador`
3. Se pueden asignar múltiples cobradores a la vez
4. Si un cobrador ya está asignado a otro manager, se reasigna

### **Para Remover Cobradores:**
1. El usuario debe ser un manager válido
2. El cobrador debe estar asignado al manager especificado
3. Al remover, el cobrador queda sin manager asignado (`assigned_manager_id = null`)

### **Para Consultar:**
1. Se valida que el usuario sea del rol correcto (manager o cobrador)
2. Se incluyen búsquedas y paginación donde corresponde
3. Se cargan las relaciones necesarias (roles, permisos)

## Casos de Uso Comunes

### **1. Dashboard del Manager**
```javascript
// Obtener todos los cobradores asignados al manager logueado
const myCobradores = await fetch(`/api/users/${managerId}/cobradores`);
```

### **2. Asignación Masiva de Cobradores**
```javascript
// Asignar múltiples cobradores a un manager
const assignCobradores = await fetch(`/api/users/${managerId}/assign-cobradores`, {
    method: 'POST',
    body: JSON.stringify({
        cobrador_ids: selectedCobradorIds
    })
});
```

### **3. Reasignación de Cobradores**
```javascript
// Remover cobrador de un manager y asignarlo a otro
await fetch(`/api/users/${oldManagerId}/cobradores/${cobradorId}`, {
    method: 'DELETE'
});

await fetch(`/api/users/${newManagerId}/assign-cobradores`, {
    method: 'POST',
    body: JSON.stringify({
        cobrador_ids: [cobradorId]
    })
});
```

### **4. Verificación de Asignación**
```javascript
// Verificar si un cobrador tiene manager asignado
const manager = await fetch(`/api/users/${cobradorId}/manager`);
if (manager.data) {
    console.log(`Cobrador asignado a: ${manager.data.name}`);
} else {
    console.log('Cobrador sin manager asignado');
}
```

### **5. Vista Jerárquica Completa**
```javascript
// Obtener la jerarquía completa: Manager → Cobradores → Clientes
const manager = await fetch(`/api/users/${managerId}`);
const cobradores = await fetch(`/api/users/${managerId}/cobradores`);

// Para cada cobrador, obtener sus clientes
const hierarchyData = await Promise.all(
    cobradores.data.data.map(async (cobrador) => {
        const clients = await fetch(`/api/users/${cobrador.id}/clients`);
        return {
            cobrador,
            clients: clients.data.data
        };
    })
);
```

## Integración con el Sistema Existente

### **Compatibilidad Total**
- ✅ No afecta el sistema actual de clientes ↔ cobradores
- ✅ Se puede usar independientemente o junto con asignaciones de clientes
- ✅ Mantiene todas las funcionalidades existentes

### **Nuevas Posibilidades**
1. **Filtros por Manager**: Los admins pueden filtrar cobradores por manager
2. **Reportes Jerárquicos**: Estadísticas por manager y sus cobradores
3. **Permisos Granulares**: Managers solo ven sus cobradores asignados
4. **Escalabilidad**: Organización eficiente de equipos grandes

### **Queries Optimizadas**
```php
// Obtener todos los clientes de un manager específico
$clients = User::whereHas('assignedCobrador.assignedManager', function($q) use ($managerId) {
    $q->where('id', $managerId);
})->get();

// Obtener estadísticas de un manager
$stats = [
    'total_cobradores' => $manager->assignedCobradores()->count(),
    'total_clients' => User::whereHas('assignedCobrador.assignedManager', function($q) use ($managerId) {
        $q->where('id', $managerId);
    })->count(),
];
```

## Ventajas de esta Implementación

1. ✅ **Consistencia**: Usa el mismo patrón que clientes ↔ cobradores
2. ✅ **Escalabilidad**: Fácil gestión de equipos grandes
3. ✅ **Flexibilidad**: Reasignación simple de cobradores
4. ✅ **Jerarquía Clara**: Manager → Cobrador → Cliente
5. ✅ **Compatibilidad**: No rompe funcionalidades existentes
6. ✅ **Eficiencia**: Consultas optimizadas con relaciones directas

## Notas Importantes

1. **Migración**: Se ejecutó la migración para agregar `assigned_manager_id` a la tabla `users`
2. **Relaciones**: Se agregaron las relaciones `assignedCobradores()` y `assignedManager()` al modelo User
3. **Validaciones**: Todos los endpoints incluyen validaciones de roles
4. **Paginación**: Los endpoints de listado incluyen paginación automática
5. **Búsqueda**: Se puede buscar por nombre o email en los cobradores
6. **Autenticación**: Todos los endpoints requieren autenticación
7. **Jerarquía**: Se mantiene la estructura Manager → Cobrador → Cliente

## Testing

Para probar la funcionalidad, puedes usar el siguiente script de prueba:

```php
<?php
// test_manager_cobrador_assignment.php

// Buscar un manager
$manager = User::whereHas('roles', function ($q) {
    $q->where('name', 'manager');
})->first();

// Buscar cobradores disponibles
$cobradores = User::whereHas('roles', function ($q) {
    $q->where('name', 'cobrador');
})->whereNull('assigned_manager_id')->limit(2)->get();

// Asignar cobradores al manager
foreach ($cobradores as $cobrador) {
    $cobrador->update(['assigned_manager_id' => $manager->id]);
}

echo "✅ Asignados {$cobradores->count()} cobradores al manager {$manager->name}";
```
