# Corrección del Endpoint `/api/users/{manager}/cobradores`

## Problema Identificado

El endpoint `http://192.168.5.44:8000/api/users/17/cobradores` estaba devolviendo usuarios con roles mixtos (`client` y `cobrador`) en lugar de devolver únicamente usuarios con rol `cobrador`.

### Datos problemáticos originales:
- **Total usuarios devueltos**: 7
- **Usuarios con rol client**: 6 (IDs: 14, 7, 16, 19, 10, 20)
- **Usuarios con rol cobrador**: 1 (ID: 18)
- **Problema**: La consulta incluía todos los usuarios asignados al manager sin filtrar por rol

## Causa Raíz

El método `getCobradoresByManager` en `UserController.php` usaba la relación `assignedCobradores()` del modelo `User`, pero esta relación no filtraba por rol:

```php
// CÓDIGO PROBLEMÁTICO:
$cobradores = $manager->assignedCobradores()  // ❌ No filtra por rol
    ->with(['roles', 'permissions'])
    ->when($request->search, function ($query, $search) {
        $query->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
    })
    ->orderBy('name', 'asc')
    ->paginate($request->get('per_page', 15));
```

La relación `assignedCobradores()` en el modelo `User` solo verificaba `assigned_manager_id` sin filtrar por rol:

```php
public function assignedCobradores(): HasMany
{
    return $this->hasMany(User::class, 'assigned_manager_id'); // ❌ Sin filtro de rol
}
```

## Solución Implementada

### 1. Corrección del método `getCobradoresByManager`

```php
// CÓDIGO CORREGIDO:
$cobradores = User::whereHas('roles', function ($query) {
        $query->where('name', 'cobrador');  // ✅ Filtrar por rol cobrador
    })
    ->where('assigned_manager_id', $manager->id)  // ✅ Filtrar por manager
    ->with(['roles', 'permissions'])
    ->when($request->search, function ($query, $search) {
        $query->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
    })
    ->orderBy('name', 'asc')
    ->paginate($request->get('per_page', 15));
```

### 2. Corrección del método `getAllClientsByManager`

También se corrigió un problema similar en `getAllClientsByManager` que usaba la misma relación defectuosa:

```php
// ANTES (problemático):
$cobradorIds = $manager->assignedCobradores()->pluck('id');

// DESPUÉS (corregido):
$cobradorIds = User::whereHas('roles', function ($query) {
        $query->where('name', 'cobrador');
    })
    ->where('assigned_manager_id', $manager->id)
    ->pluck('id');
```

## Resultado de la Corrección

### ✅ Antes de la corrección:
- Endpoint devolvía 7 usuarios (6 clientes + 1 cobrador)
- Incluía usuarios con roles incorrectos
- Respuesta inconsistente con el propósito del endpoint

### ✅ Después de la corrección:
- Endpoint devuelve 1 usuario (solo cobradores)
- Todos los usuarios tienen rol "cobrador" únicamente
- Respuesta consistente y correcta

### Respuesta corregida:
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 18,
                "name": "cobrador uno Fernando",
                "roles": [
                    {
                        "name": "cobrador"
                    }
                ]
            }
        ],
        "total": 1
    },
    "message": "Cobradores asignados al manager Fernando Pinto Lino obtenidos exitosamente"
}
```

## Comandos de Prueba Creados

1. **`php artisan test:manager-cobradores`**: Analiza usuarios asignados al manager
2. **`php artisan test:direct-endpoint`**: Prueba la lógica del endpoint directamente
3. **`php artisan test:cobrador-endpoint`**: Prueba el UserController corregido

## Archivos Modificados

- **`app/Http/Controllers/Api/UserController.php`**
  - Método `getCobradoresByManager()` corregido
  - Método `getAllClientsByManager()` corregido

- **`routes/console.php`** (comandos de prueba)
- **`app/Console/Commands/TestCobradorEndpoint.php`** (comando de verificación)

## Validación

✅ **Pruebas unitarias**: Todos los comandos de prueba pasan  
✅ **Filtrado por rol**: Solo usuarios con rol "cobrador"  
✅ **Funcionalidad**: Endpoint responde correctamente  
✅ **Consistencia**: Lógica aplicada en métodos relacionados  

La corrección está **completa y validada** ✅
