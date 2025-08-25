# Implementación de Guardado de Ubicación GeoJSON

## Problema Identificado
Los datos de ubicación enviados desde el frontend no se estaban guardando en la base de datos porque el `UserController` no procesaba el campo `location` en formato GeoJSON.

## Datos del Frontend
```json
{
  "name": "mi cliente Fernando",
  "email": "",
  "roles": ["client"],
  "phone": "76843652",
  "address": "25PX+8G5, Puerto Suárez, Departamento de Santa Cruz",
  "location": {
    "type": "Point",
    "coordinates": [-57.8012161, -18.9643238]
  }
}
```

## Solución Implementada

### 1. Actualización del UserController

#### Método `store()` - Validación
```php
// Validación dinámica basada en el rol
$validationRules = [
    'name' => 'required|string|max:255',
    'password' => 'nullable|string|min:8',
    'address' => 'nullable|string',
    'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    'roles' => 'required|array|min:1',
    'roles.*' => 'string|in:admin,manager,cobrador,client',
    'location' => 'nullable|array',
    'location.type' => 'nullable|string|in:Point',
    'location.coordinates' => 'nullable|array|size:2',
    'location.coordinates.0' => 'nullable|numeric|between:-180,180', // longitude
    'location.coordinates.1' => 'nullable|numeric|between:-90,90',   // latitude
];
```

#### Método `store()` - Procesamiento de Ubicación
```php
// Procesar ubicación GeoJSON si se proporciona
if ($request->has('location') && $request->location) {
    $location = $request->location;
    if (isset($location['type']) && $location['type'] === 'Point' && 
        isset($location['coordinates']) && is_array($location['coordinates']) && 
        count($location['coordinates']) === 2) {
        
        $userData['longitude'] = $location['coordinates'][0]; // longitude es el primer elemento
        $userData['latitude'] = $location['coordinates'][1];  // latitude es el segundo elemento
    }
}
```

#### Método `update()` - Mismas Validaciones y Procesamiento
Se aplicaron las mismas validaciones y procesamiento de ubicación al método `update()` para que también funcione al actualizar usuarios.

### 2. Validaciones Implementadas

- **Formato GeoJSON**: Solo acepta objetos con `type: "Point"`
- **Coordenadas válidas**: Array de exactamente 2 elementos numéricos
- **Rangos geográficos**: 
  - Longitude: -180 a 180 grados
  - Latitude: -90 a 90 grados

### 3. Mapeo de Coordenadas

- **coordinates[0]** → `longitude` (columna DB)
- **coordinates[1]** → `latitude` (columna DB)

### 4. Comandos de Prueba Creados

```bash
# Prueba básica de funcionalidad
php artisan test:user-location

# Simulación exacta de datos del frontend
php artisan test:frontend-location
```

## Resultado

### ✅ Antes de la Implementación
- Campo `location` del frontend se ignoraba
- Coordenadas no se guardaban en la DB
- Sin validación de formato GeoJSON

### ✅ Después de la Implementación
- Campo `location` se procesa correctamente
- Coordenadas se extraen y guardan como `latitude`/`longitude`
- Validaciones robustas para formato GeoJSON
- Compatible con ambos métodos `store()` y `update()`

## Prueba de Funcionamiento

```bash
# Datos del frontend:
{
  "location": {
    "type": "Point", 
    "coordinates": [-57.8012161, -18.9643238]
  }
}

# Resultado en la DB:
longitude: -57.8012161
latitude: -18.9643238
```

## Archivos Modificados

1. **`app/Http/Controllers/Api/UserController.php`**
   - Métodos `store()` y `update()` actualizados
   - Validaciones GeoJSON agregadas
   - Procesamiento de coordenadas implementado

2. **`routes/console.php`**
   - Comandos de prueba agregados
   - Verificaciones de funcionalidad

La implementación está **completa y probada** ✅
