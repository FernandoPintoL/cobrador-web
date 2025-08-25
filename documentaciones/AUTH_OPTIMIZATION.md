# Optimización del Sistema de Autenticación

## Cambios Realizados

### 1. Login Flexible por Email o Teléfono

Se ha optimizado el método `login` en `AuthController` para permitir autenticación tanto por correo electrónico como por número de teléfono.

#### Cambios en el Controlador:
- **Campo de entrada**: Cambió de `email` a `email_or_phone`
- **Validación**: Ahora acepta cualquier string (email o teléfono)
- **Lógica de búsqueda**: 
  - Detecta automáticamente si el input es un email válido
  - Si es email: busca en la columna `email`
  - Si es teléfono: busca en la columna `phone`

#### Ejemplo de uso:
```json
{
  "email_or_phone": "usuario@ejemplo.com",
  "password": "password123"
}
```

O

```json
{
  "email_or_phone": "1234567890",
  "password": "password123"
}
```

### 2. Validación Única para Teléfono

Se agregó la restricción `unique` al campo `phone` en el registro de usuarios para evitar duplicados.

#### Migración creada:
- Archivo: `2025_08_02_161919_add_unique_constraint_to_phone_column_in_users_table.php`
- Agrega restricción única al campo `phone` en la tabla `users`

### 3. Nuevo Endpoint de Verificación

Se agregó el método `checkExists` para verificar si un email o teléfono ya existe en el sistema.

#### Endpoint:
- **URL**: `POST /api/check-exists`
- **Parámetros**: `email_or_phone` (string)
- **Respuesta**: 
  ```json
  {
    "exists": true/false,
    "type": "email" | "phone"
  }
  ```

### 4. Laravel Sanctum Configurado

Se ha instalado y configurado Laravel Sanctum para manejo de tokens API.

#### Configuraciones realizadas:
- Instalación de `laravel/sanctum`
- Agregado trait `HasApiTokens` al modelo `User`
- Configurado guard `api` con driver `sanctum`
- Ejecutadas migraciones de Sanctum

### 5. Rutas Actualizadas

#### Rutas públicas:
- `POST /api/register` - Registro de usuarios
- `POST /api/login` - Login por email o teléfono
- `POST /api/check-exists` - Verificar existencia de email/teléfono

#### Rutas protegidas (requieren token):
- `POST /api/logout` - Cerrar sesión
- `GET /api/me` - Obtener usuario autenticado
- Todas las demás rutas API

## Uso de Tokens API

### Login y Obtención de Token
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email_or_phone": "usuario@ejemplo.com",
    "password": "password123"
  }'
```

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Usuario Ejemplo",
      "email": "usuario@ejemplo.com",
      "phone": "1234567890"
    },
    "token": "1|abcdef123456..."
  },
  "message": "Inicio de sesión exitoso"
}
```

### Uso del Token en Peticiones
```bash
curl -X GET http://localhost:8000/api/me \
  -H "Authorization: Bearer 1|abcdef123456..." \
  -H "Accept: application/json"
```

### Logout
```bash
curl -X POST http://localhost:8000/api/logout \
  -H "Authorization: Bearer 1|abcdef123456..." \
  -H "Accept: application/json"
```

## Beneficios

1. **Flexibilidad**: Los usuarios pueden iniciar sesión con email o teléfono
2. **Mejor UX**: No necesitan recordar si se registraron con email o teléfono
3. **Validación robusta**: Evita duplicados de teléfonos
4. **Verificación previa**: Permite verificar disponibilidad antes del registro
5. **Autenticación API segura**: Usando Laravel Sanctum para tokens
6. **Compatibilidad**: Mantiene funcionamiento con usuarios existentes

## Compatibilidad

- ✅ Mantiene compatibilidad con usuarios existentes
- ✅ No requiere cambios en el frontend existente (solo cambiar el nombre del campo)
- ✅ Migración segura que no afecta datos existentes
- ✅ Sistema de tokens API robusto y seguro

## Notas Técnicas

- El sistema detecta automáticamente el tipo de entrada usando `filter_var($email, FILTER_VALIDATE_EMAIL)`
- La validación de contraseña se mantiene igual
- Los mensajes de error se han actualizado para reflejar el nuevo campo
- Se mantiene la funcionalidad de roles y permisos existente
- Laravel Sanctum maneja la autenticación API de forma segura
- Los tokens se almacenan en la base de datos y se pueden revocar 