# Gestión de Fotos de Usuarios (Documentos de Identidad)

Fecha: 2025-08-22

Objetivo: Permitir guardar múltiples fotos asociadas a un usuario (por ejemplo, anverso y reverso del documento de identidad), en una tabla separada almacenando las rutas (path_url) de las imágenes.

## Esquema de Datos

Tabla: user_photos
- id (PK)
- user_id (FK users.id, cascade on delete)
- type (string, valores sugeridos: id_front, id_back, other)
- path_url (string) — ruta relativa en storage/public
- uploaded_by (FK users.id, nullOnDelete)
- notes (string, opcional)
- timestamps

Migración: database/migrations/2025_08_22_000004_create_user_photos_table.php

Modelo: app/Models/UserPhoto.php
- Relaciones:
  - user(): BelongsTo(User)
  - uploader(): BelongsTo(User, 'uploaded_by')

Relación en User: app/Models/User.php
- photos(): HasMany(UserPhoto)

## Endpoints de API

Requieren autenticación (Sanctum). Prefijo: /api

1) Listar fotos de un usuario
- GET /api/users/{user}/photos
- Permisos: el propio usuario, admin, manager
- Respuesta: lista con id, type, path_url y url absoluta (asset('storage/...'))

2) Subir fotos para un usuario
- POST /api/users/{user}/photos
- Permisos: el propio usuario, admin, manager
- Formatos aceptados:
  - photo (archivo único) + type (string en {id_front,id_back,other})
  - photos[] (múltiples archivos) + types[] (opcional, alineado por índice; default 'other') + notes (opcional)
- Validaciones: imágenes jpeg/png/jpg/gif, tamaño máx 5MB
- Respuesta: arreglo de fotos creadas con id, type, path_url y url pública

3) Eliminar una foto de un usuario
- DELETE /api/users/{user}/photos/{photo}
- Permisos: el propio usuario, admin, manager
- Efecto: elimina el registro y el archivo físico en storage/public

Rutas definidas en routes/api.php.

## Almacenamiento
- Las imágenes se guardan en storage/app/public/user-photos/{user_id}/
- Asegúrate de ejecutar `php artisan storage:link` para servir archivos desde /storage

## Notas
- Los tipos permitidos se validan en el servidor: id_front, id_back, other.
- Si en el futuro se requieren más tipos (licencia, selfie con CI, etc.), basta con ampliar la validación.
- Esta funcionalidad es independiente de la imagen de perfil existente (`profile_image`).

## Ejemplos (cURL)

Subir anverso del CI (foto única):
```
curl -X POST http://localhost:8000/api/users/123/photos \
  -H "Authorization: Bearer {TOKEN}" \
  -F "photo=@C:/path/anverso.jpg" \
  -F "type=id_front"
```

Subir múltiples fotos (anverso y reverso):
```
curl -X POST http://localhost:8000/api/users/123/photos \
  -H "Authorization: Bearer {TOKEN}" \
  -F "photos[]=@C:/path/anverso.jpg" \
  -F "photos[]=@C:/path/reverso.jpg" \
  -F "types[]==id_front" \
  -F "types[]==id_back"
```

Listar fotos:
```
curl -X GET http://localhost:8000/api/users/123/photos \
  -H "Authorization: Bearer {TOKEN}"
```

Eliminar una foto:
```
curl -X DELETE http://localhost:8000/api/users/123/photos/45 \
  -H "Authorization: Bearer {TOKEN}"
```
