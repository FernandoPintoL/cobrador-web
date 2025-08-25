# API de Imágenes de Perfil

## Descripción
Esta funcionalidad permite a los usuarios subir y gestionar sus imágenes de perfil en el sistema.

## Endpoints

### 1. Crear Usuario con Imagen de Perfil
**POST** `/api/users`

**Headers:**
```
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

**Body (form-data):**
```
name: "Juan Pérez"
email: "juan@ejemplo.com"
password: "password123"
phone: "123456789"
address: "Calle Principal 123"
profile_image: [archivo de imagen]
roles: ["cobrador"]
```

**Respuesta:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Juan Pérez",
        "email": "juan@ejemplo.com",
        "phone": "123456789",
        "address": "Calle Principal 123",
        "profile_image": "profile-images/1234567890_imagen.jpg",
        "profile_image_url": "http://192.168.5.44:8000/storage/profile-images/1234567890_imagen.jpg",
        "created_at": "2025-08-02T23:30:00.000000Z",
        "updated_at": "2025-08-02T23:30:00.000000Z"
    },
    "message": "Usuario creado exitosamente"
}
```

### 2. Actualizar Usuario con Imagen de Perfil
**PUT/PATCH** `/api/users/{user_id}`

**Headers:**
```
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

**Body (form-data):**
```
name: "Juan Pérez Actualizado"
email: "juan.nuevo@ejemplo.com"
phone: "987654321"
address: "Nueva Dirección 456"
profile_image: [archivo de imagen]
roles: ["cobrador", "admin"]
```

### 3. Actualizar Solo la Imagen de Perfil
**POST** `/api/users/{user_id}/profile-image`

**Headers:**
```
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

**Body (form-data):**
```
profile_image: [archivo de imagen]
```

**Respuesta:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Juan Pérez",
        "email": "juan@ejemplo.com",
        "profile_image": "profile-images/1234567890_nueva_imagen.jpg",
        "profile_image_url": "http://192.168.5.44:8000/storage/profile-images/1234567890_nueva_imagen.jpg"
    },
    "message": "Imagen de perfil actualizada exitosamente"
}
```

### 4. Obtener Usuario con URL de Imagen
**GET** `/api/users/{user_id}`

**Headers:**
```
Authorization: Bearer {token}
```

**Respuesta:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Juan Pérez",
        "email": "juan@ejemplo.com",
        "profile_image": "profile-images/1234567890_imagen.jpg",
        "profile_image_url": "http://192.168.5.44:8000/storage/profile-images/1234567890_imagen.jpg"
    }
}
```

## Especificaciones de Imagen

- **Formatos permitidos:** JPEG, PNG, JPG, GIF
- **Tamaño máximo:** 2MB (2048 KB)
- **Ubicación de almacenamiento:** `storage/app/public/profile-images/`
- **URL de acceso:** `http://tu-dominio.com/storage/profile-images/`

## Imagen por Defecto

Si un usuario no tiene imagen de perfil, se devuelve una imagen por defecto:
```
http://tu-dominio.com/images/default-avatar.png
```

## Ejemplos de Uso

### Con cURL
```bash
# Subir imagen de perfil
curl -X POST \
  http://192.168.5.44:8000/api/users/1/profile-image \
  -H "Authorization: Bearer {tu_token}" \
  -F "profile_image=@/path/to/image.jpg"
```

### Con JavaScript/Fetch
```javascript
const formData = new FormData();
formData.append('profile_image', fileInput.files[0]);

fetch('/api/users/1/profile-image', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token
    },
    body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

### Con Postman
1. Selecciona método `POST`
2. URL: `http://192.168.5.44:8000/api/users/1/profile-image`
3. Headers: `Authorization: Bearer {token}`
4. Body: `form-data`
5. Key: `profile_image`, Type: `File`
6. Selecciona tu archivo de imagen

## Notas Importantes

1. **Autenticación requerida:** Todos los endpoints requieren autenticación con Sanctum
2. **Eliminación automática:** Al actualizar una imagen, la anterior se elimina automáticamente
3. **Validación:** Las imágenes se validan por tipo y tamaño
4. **Nombres únicos:** Los nombres de archivo incluyen timestamp para evitar conflictos
5. **URLs automáticas:** El sistema genera automáticamente las URLs de acceso a las imágenes 