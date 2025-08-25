# Documentación de APIs - Sistema de Cobrador

## Autenticación

### Registro de Usuario
```
POST /api/register
Content-Type: application/json

{
  "name": "Juan Pérez",
  "email": "juan@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+1234567890",
  "address": "Calle Principal 123"
}
```

### Login
```
POST /api/login
Content-Type: application/json

{
  "email": "juan@example.com",
  "password": "password123"
}
```

### Logout
```
POST /api/logout
Authorization: Bearer {token}
```

### Obtener Usuario Actual
```
GET /api/me
Authorization: Bearer {token}
```

## Usuarios

### Listar Usuarios
```
GET /api/users?search=nombre&page=1
Authorization: Bearer {token}
```

### Crear Usuario
```
POST /api/users
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Nuevo Usuario",
  "email": "nuevo@example.com",
  "password": "password123",
  "phone": "+1234567890",
  "address": "Dirección 123",
  "roles": ["cobrador"]
}
```

### Obtener Usuario
```
GET /api/users/{id}
Authorization: Bearer {token}
```

### Actualizar Usuario
```
PUT /api/users/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Usuario Actualizado",
  "email": "actualizado@example.com",
  "phone": "+1234567890",
  "address": "Nueva Dirección",
  "roles": ["admin"]
}
```

### Eliminar Usuario
```
DELETE /api/users/{id}
Authorization: Bearer {token}
```

### Obtener Roles
```
GET /api/users/{id}/roles
Authorization: Bearer {token}
```

## Rutas

### Listar Rutas
```
GET /api/routes?cobrador_id=1&search=nombre&page=1
Authorization: Bearer {token}
```

### Crear Ruta
```
POST /api/routes
Authorization: Bearer {token}
Content-Type: application/json

{
  "cobrador_id": 1,
  "name": "Ruta Centro",
  "description": "Ruta del centro de la ciudad",
  "client_ids": [1, 2, 3]
}
```

### Obtener Ruta
```
GET /api/routes/{id}
Authorization: Bearer {token}
```

### Actualizar Ruta
```
PUT /api/routes/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "cobrador_id": 1,
  "name": "Ruta Centro Actualizada",
  "description": "Descripción actualizada",
  "client_ids": [1, 2, 3, 4]
}
```

### Eliminar Ruta
```
DELETE /api/routes/{id}
Authorization: Bearer {token}
```

### Obtener Rutas por Cobrador
```
GET /api/routes/cobrador/{cobrador_id}
Authorization: Bearer {token}
```

### Obtener Clientes Disponibles
```
GET /api/routes/available-clients
Authorization: Bearer {token}
```

## Créditos

### Listar Créditos
```
GET /api/credits?client_id=1&status=active&search=cliente&page=1
Authorization: Bearer {token}
```

### Crear Crédito
```
POST /api/credits
Authorization: Bearer {token}
Content-Type: application/json

{
  "client_id": 1,
  "amount": 1000.00,
  "balance": 1000.00,
  "frequency": "weekly",
  "start_date": "2024-01-01",
  "end_date": "2024-12-31",
  "status": "active"
}
```

### Obtener Crédito
```
GET /api/credits/{id}
Authorization: Bearer {token}
```

### Actualizar Crédito
```
PUT /api/credits/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "client_id": 1,
  "amount": 1200.00,
  "balance": 800.00,
  "frequency": "weekly",
  "start_date": "2024-01-01",
  "end_date": "2024-12-31",
  "status": "active"
}
```

### Eliminar Crédito
```
DELETE /api/credits/{id}
Authorization: Bearer {token}
```

### Obtener Créditos por Cliente
```
GET /api/credits/client/{client_id}
Authorization: Bearer {token}
```

### Obtener Cuotas Restantes
```
GET /api/credits/{id}/remaining-installments
Authorization: Bearer {token}
```

## Pagos

### Listar Pagos
```
GET /api/payments?client_id=1&cobrador_id=1&credit_id=1&status=completed&date_from=2024-01-01&date_to=2024-12-31&page=1
Authorization: Bearer {token}
```

### Crear Pago
```
POST /api/payments
Authorization: Bearer {token}
Content-Type: application/json

{
  "client_id": 1,
  "cobrador_id": 1,
  "credit_id": 1,
  "amount": 100.00,
  "payment_date": "2024-01-15T10:30:00Z",
  "payment_method": "cash",
  "location": {
    "lat": 19.4326,
    "lng": -99.1332
  },
  "status": "completed",
  "transaction_id": "TXN123456",
  "installment_number": 1
}
```

### Obtener Pago
```
GET /api/payments/{id}
Authorization: Bearer {token}
```

### Actualizar Pago
```
PUT /api/payments/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "client_id": 1,
  "cobrador_id": 1,
  "credit_id": 1,
  "amount": 150.00,
  "payment_date": "2024-01-15T10:30:00Z",
  "payment_method": "transfer",
  "location": {
    "lat": 19.4326,
    "lng": -99.1332
  },
  "status": "completed",
  "transaction_id": "TXN123456",
  "installment_number": 1
}
```

### Eliminar Pago
```
DELETE /api/payments/{id}
Authorization: Bearer {token}
```

### Obtener Pagos por Cliente
```
GET /api/payments/client/{client_id}
Authorization: Bearer {token}
```

### Obtener Pagos por Cobrador
```
GET /api/payments/cobrador/{cobrador_id}
Authorization: Bearer {token}
```

### Obtener Pagos por Crédito
```
GET /api/payments/credit/{credit_id}
Authorization: Bearer {token}
```

## Balances de Efectivo

### Listar Balances
```
GET /api/cash-balances?cobrador_id=1&date_from=2024-01-01&date_to=2024-12-31&page=1
Authorization: Bearer {token}
```

### Crear Balance
```
POST /api/cash-balances
Authorization: Bearer {token}
Content-Type: application/json

{
  "cobrador_id": 1,
  "date": "2024-01-15",
  "initial_amount": 1000.00,
  "collected_amount": 500.00,
  "lent_amount": 200.00,
  "final_amount": 1300.00
}
```

### Obtener Balance
```
GET /api/cash-balances/{id}
Authorization: Bearer {token}
```

### Actualizar Balance
```
PUT /api/cash-balances/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "cobrador_id": 1,
  "date": "2024-01-15",
  "initial_amount": 1000.00,
  "collected_amount": 600.00,
  "lent_amount": 200.00,
  "final_amount": 1400.00
}
```

### Eliminar Balance
```
DELETE /api/cash-balances/{id}
Authorization: Bearer {token}
```

### Obtener Balances por Cobrador
```
GET /api/cash-balances/cobrador/{cobrador_id}
Authorization: Bearer {token}
```

### Obtener Resumen de Balances
```
GET /api/cash-balances/cobrador/{cobrador_id}/summary
Authorization: Bearer {token}
```

## Notificaciones

### Listar Notificaciones
```
GET /api/notifications?user_id=1&type=payment_received&status=unread&page=1
Authorization: Bearer {token}
```

### Crear Notificación
```
POST /api/notifications
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_id": 1,
  "payment_id": 1,
  "type": "payment_received",
  "message": "Pago recibido exitosamente",
  "status": "unread"
}
```

### Obtener Notificación
```
GET /api/notifications/{id}
Authorization: Bearer {token}
```

### Actualizar Notificación
```
PUT /api/notifications/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_id": 1,
  "payment_id": 1,
  "type": "payment_received",
  "message": "Pago recibido exitosamente",
  "status": "read"
}
```

### Eliminar Notificación
```
DELETE /api/notifications/{id}
Authorization: Bearer {token}
```

### Marcar como Leída
```
PATCH /api/notifications/{id}/mark-read
Authorization: Bearer {token}
```

### Marcar Todas como Leídas
```
POST /api/notifications/mark-all-read
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_id": 1
}
```

### Obtener Notificaciones por Usuario
```
GET /api/notifications/user/{user_id}
Authorization: Bearer {token}
```

### Obtener Contador de No Leídas
```
GET /api/notifications/user/{user_id}/unread-count
Authorization: Bearer {token}
```

## Códigos de Respuesta

- `200`: Éxito
- `201`: Creado exitosamente
- `400`: Error de validación
- `401`: No autorizado
- `403`: Prohibido
- `404`: No encontrado
- `422`: Error de validación
- `500`: Error interno del servidor

## Formato de Respuesta

### Respuesta Exitosa
```json
{
  "success": true,
  "data": {
    // Datos de la respuesta
  },
  "message": "Operación exitosa"
}
```

### Respuesta de Error
```json
{
  "success": false,
  "message": "Mensaje de error",
  "data": {
    // Detalles del error (opcional)
  }
}
```

## Autenticación en Flutter

```dart
// Ejemplo de configuración en Flutter
class ApiService {
  static const String baseUrl = 'https://tu-dominio.com/api';
  static String? token;

  static Future<void> login(String email, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/login'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'email': email,
        'password': password,
      }),
    );

    final data = jsonDecode(response.body);
    if (data['success']) {
      token = data['data']['token'];
      // Guardar token en SharedPreferences
    }
  }

  static Future<Map<String, String>> getHeaders() async {
    return {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer $token',
    };
  }

  static Future<dynamic> get(String endpoint) async {
    final response = await http.get(
      Uri.parse('$baseUrl/$endpoint'),
      headers: await getHeaders(),
    );
    return jsonDecode(response.body);
  }

  static Future<dynamic> post(String endpoint, Map<String, dynamic> data) async {
    final response = await http.post(
      Uri.parse('$baseUrl/$endpoint'),
      headers: await getHeaders(),
      body: jsonEncode(data),
    );
    return jsonDecode(response.body);
  }
}
```

## Roles y Permisos

### Roles Disponibles
- `admin`: Acceso completo al sistema
- `manager`: Gestión de usuarios, rutas, créditos y pagos
- `cobrador`: Gestión de pagos y balances de efectivo
- `client`: Visualización de créditos y pagos propios

### Permisos por Rol
- **Admin**: Todos los permisos
- **Manager**: Gestión de usuarios, rutas, créditos, pagos, balances y notificaciones
- **Cobrador**: Visualización de rutas, créditos, gestión de pagos y balances
- **Client**: Visualización de créditos y pagos propios, notificaciones

## Notas Importantes

1. **Autenticación**: Todas las rutas protegidas requieren el header `Authorization: Bearer {token}`
2. **Validación**: Los datos enviados deben cumplir con las reglas de validación definidas
3. **Paginación**: Las listas incluyen paginación automática
4. **Filtros**: Se pueden aplicar filtros mediante parámetros de query
5. **Geolocalización**: Los pagos pueden incluir coordenadas GPS
6. **Notificaciones**: El sistema genera notificaciones automáticas para eventos importantes
7. **Roles**: El sistema utiliza Spatie Laravel Permission para gestión de roles y permisos 