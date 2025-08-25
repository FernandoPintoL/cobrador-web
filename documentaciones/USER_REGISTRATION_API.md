# API de Registro de Usuarios con Roles

## Endpoint: POST /api/users

### Parámetros Requeridos:
- `name` (string, max: 255): Nombre completo
- `email` (string, email, unique): Correo electrónico
- `password` (string, min: 8): Contraseña
- `roles` (array, min: 1): Roles del usuario

### Parámetros Opcionales:
- `phone` (string, max: 20): Teléfono
- `address` (string): Dirección
- `profile_image` (file): Imagen de perfil

### Roles Disponibles:
- `admin`: Administrador
- `manager`: Gerente
- `cobrador`: Cobrador
- `client`: Cliente

### Reglas de Permisos:
- **Admin**: Puede crear cualquier rol
- **Manager**: Puede crear manager, cobrador, client (NO admin)
- **Cobrador**: Solo puede crear client
- **Client**: No puede crear usuarios

### Ejemplo de uso:
```bash
POST /api/users
Content-Type: multipart/form-data
Authorization: Bearer {token}

Body:
- name: "Juan Pérez"
- email: "juan@ejemplo.com"
- password: "password123"
- roles: ["cobrador"]
- phone: "123456789"
- address: "Calle Principal 123"
- profile_image: [archivo opcional]
``` 