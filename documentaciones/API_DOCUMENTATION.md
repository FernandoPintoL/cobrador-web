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

## Dashboard

### Obtener Estadísticas Generales
```
GET /api/dashboard/stats
Authorization: Bearer {token}
```

### Obtener Estadísticas por Cobrador
```
GET /api/dashboard/stats-by-cobrador?cobrador_id=1
Authorization: Bearer {token}
```

### Obtener Actividad Reciente
```
GET /api/dashboard/recent-activity?limit=10
Authorization: Bearer {token}
```

### Obtener Alertas
```
GET /api/dashboard/alerts
Authorization: Bearer {token}
```

### Obtener Métricas de Rendimiento
```
GET /api/dashboard/performance-metrics?cobrador_id=1&start_date=2024-01-01&end_date=2024-12-31
Authorization: Bearer {token}
```

### Obtener Estadísticas del Manager
```
GET /api/dashboard/manager-stats?manager_id=1
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": {
    "manager": {
      "id": 1,
      "name": "Manager Nombre"
    },
    "team_summary": {
      "total_cobradores": 5,
      "total_clients_team": 50,
      "total_credits_team": 30,
      "total_balance_team": 15000.00,
      "today_collections_team": 2500.00,
      "month_collections_team": 45000.00
    },
    "cobradores": [
      {
        "cobrador_id": 2,
        "cobrador_name": "Cobrador Nombre",
        "clients": 10,
        "active_credits": 8,
        "today_collections": 500.00,
        "month_collections": 9000.00
      }
    ]
  }
}
```

### Obtener Resumen Financiero
```
GET /api/dashboard/financial-summary
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": {
    "general": {
      "total_lent": 100000.00,
      "total_collected": 75000.00,
      "active_balance": 25000.00,
      "total_active_credits": 45,
      "total_completed_credits": 120
    },
    "month": {
      "month_lent": 15000.00,
      "month_collected": 12000.00,
      "month_credits_count": 10,
      "month_payments_count": 150
    },
    "today": {
      "today_collected": 2000.00,
      "today_payments_count": 25,
      "today_credits_delivered": 3
    },
    "cash": {
      "total_cash_on_hand": 5000.00,
      "open_cash_balances": 5,
      "closed_cash_balances_today": 2
    }
  }
}
```

### Obtener Estadísticas del Mapa
```
GET /api/dashboard/map-stats
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": {
    "clients_with_location": 85,
    "clients_without_location": 15,
    "location_coverage_percentage": 85.00,
    "clients_by_category": {
      "A": 30,
      "B": 40,
      "C": 15
    },
    "clients_by_cobrador": [
      {
        "cobrador_id": 1,
        "cobrador_name": "Juan Pérez",
        "count": 25
      }
    ],
    "routes": {
      "total_routes": 10,
      "active_routes": 8
    }
  }
}
```

## Reportes

### Obtener Tipos de Reportes
```
GET /api/reports/types
Authorization: Bearer {token}
```

### Reporte de Pagos
```
GET /api/reports/payments?cobrador_id=1&client_id=1&start_date=2024-01-01&end_date=2024-12-31&format=json
Authorization: Bearer {token}

Parámetros:
- cobrador_id (opcional): Filtrar por cobrador
- client_id (opcional): Filtrar por cliente
- credit_id (opcional): Filtrar por crédito
- start_date (opcional): Fecha inicial
- end_date (opcional): Fecha final
- format (opcional): json|pdf|html|excel (default: json)
```

### Resumen Diario de Pagos
```
GET /api/reports/payments/daily-summary?date=2024-10-21
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": {
    "summary": {
      "date": "2024-10-21",
      "day_name": "Lunes",
      "total_payments": 45,
      "total_amount": 12500.00,
      "average_payment": 277.78,
      "by_cobrador": [
        {
          "cobrador_id": 1,
          "cobrador_name": "Juan Pérez",
          "total_payments": 20,
          "total_amount": 5000.00,
          "average_payment": 250.00
        }
      ],
      "total_cobradores_active": 3
    },
    "payments": []
  }
}
```

### Reporte de Créditos
```
GET /api/reports/credits?status=active&cobrador_id=1&start_date=2024-01-01&end_date=2024-12-31&format=json
Authorization: Bearer {token}

Parámetros:
- status (opcional): active|completed|cancelled|pending_approval|waiting_delivery
- cobrador_id (opcional): Filtrar por cobrador
- client_id (opcional): Filtrar por cliente
- start_date (opcional): Fecha inicial
- end_date (opcional): Fecha final
- format (opcional): json|pdf|html|excel (default: json)
```

### Créditos que Requieren Atención
```
GET /api/reports/credits/attention-needed
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": {
    "overdue_credits": [
      {
        "id": 1,
        "client": {...},
        "days_overdue": 5,
        "priority": "medium",
        "balance": 1500.00
      }
    ],
    "high_risk_credits": [],
    "pending_approvals": [],
    "waiting_delivery": [],
    "summary": {
      "total_attention_needed": 10,
      "overdue_count": 5,
      "high_risk_count": 2,
      "pending_approval_count": 2,
      "waiting_delivery_count": 1,
      "by_priority": {
        "high": 2,
        "medium": 2,
        "low": 1
      }
    }
  }
}
```

### Reporte de Usuarios/Clientes
```
GET /api/reports/users?role=client&assigned_cobrador_id=1&format=json
Authorization: Bearer {token}

Parámetros:
- role (opcional): Filtrar por rol (client, cobrador, manager, admin)
- assigned_cobrador_id (opcional): Filtrar por cobrador asignado
- assigned_manager_id (opcional): Filtrar por manager asignado
- client_category (opcional): A|B|C
- format (opcional): json|pdf|html|excel (default: json)
```

### Estadísticas de Categorías de Clientes
```
GET /api/reports/users/category-stats
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": {
    "categories": [
      {
        "category": "A",
        "count": 30,
        "percentage": 35.00,
        "active_credits": 25,
        "completed_credits": 50,
        "total_balance": 15000.00,
        "total_lent": 75000.00
      }
    ],
    "total_clients": 85,
    "summary": {
      "category_A": 30,
      "category_B": 40,
      "category_C": 15
    }
  }
}
```

### Reporte de Balances/Cajas
```
GET /api/reports/balances?cobrador_id=1&start_date=2024-01-01&end_date=2024-12-31&format=json
Authorization: Bearer {token}

Parámetros:
- cobrador_id (opcional): Filtrar por cobrador
- status (opcional): open|closed|reconciled
- start_date (opcional): Fecha inicial
- end_date (opcional): Fecha final
- format (opcional): json|pdf|html|excel (default: json)
```

### Conciliación de Efectivo
```
GET /api/reports/balances/reconciliation?date=2024-10-21
Authorization: Bearer {token}

Response:
{
  "success": true,
  "data": {
    "reconciliation": [
      {
        "balance_id": 1,
        "cobrador": {
          "id": 1,
          "name": "Juan Pérez"
        },
        "date": "2024-10-21",
        "status": "closed",
        "initial_amount": 1000.00,
        "collected_amount": 2500.00,
        "lent_amount": 1500.00,
        "expected_final": 2000.00,
        "actual_final": 2000.00,
        "difference": 0.00,
        "has_discrepancy": false,
        "discrepancy_type": "none"
      }
    ],
    "summary": {
      "date": "2024-10-21",
      "total_balances": 5,
      "total_with_discrepancies": 1,
      "total_surplus": 50.00,
      "total_shortage": 30.00,
      "net_difference": 20.00,
      "by_status": {
        "open": 0,
        "closed": 4,
        "reconciled": 1
      }
    }
  }
}
```

### Reporte de Mora
```
GET /api/reports/overdue?cobrador_id=1&client_category=A&min_days_overdue=7&format=json
Authorization: Bearer {token}

Parámetros:
- cobrador_id (opcional): Filtrar por cobrador
- client_id (opcional): Filtrar por cliente
- client_category (opcional): A|B|C
- min_days_overdue (opcional): Mínimo de días de mora
- max_days_overdue (opcional): Máximo de días de mora
- min_overdue_amount (opcional): Mínimo monto en mora
- format (opcional): json|pdf|html|excel (default: json)
```

### Reporte de Rendimiento
```
GET /api/reports/performance?cobrador_id=1&start_date=2024-01-01&end_date=2024-12-31&format=json
Authorization: Bearer {token}

Parámetros:
- cobrador_id (opcional): Filtrar por cobrador
- start_date (opcional): Fecha inicial
- end_date (opcional): Fecha final
- format (opcional): json|pdf|html|excel (default: json)
```

### Reporte de Flujo de Efectivo Proyectado
```
GET /api/reports/cash-flow-forecast?days=30&include_overdue=true&format=json
Authorization: Bearer {token}

Parámetros:
- days (opcional): Número de días a proyectar (default: 30)
- include_overdue (opcional): Incluir pagos vencidos (default: true)
- format (opcional): json|pdf|html|excel (default: json)
```

### Reporte de Lista de Espera
```
GET /api/reports/waiting-list?status=pending_approval&format=json
Authorization: Bearer {token}

Parámetros:
- status (opcional): pending_approval|waiting_delivery|all
- format (opcional): json|pdf|html|excel (default: json)
```

### Reporte de Actividad Diaria
```
GET /api/reports/daily-activity?cobrador_id=1&date=2024-10-21&format=json
Authorization: Bearer {token}

Parámetros:
- cobrador_id (opcional): Filtrar por cobrador
- date (opcional): Fecha específica (default: hoy)
- format (opcional): json|pdf|html|excel (default: json)
```

### Reporte de Cartera
```
GET /api/reports/portfolio?cobrador_id=1&format=json
Authorization: Bearer {token}

Parámetros:
- cobrador_id (opcional): Filtrar por cobrador
- format (opcional): json|pdf|html|excel (default: json)
```

### Reporte de Comisiones
```
GET /api/reports/commissions?cobrador_id=1&start_date=2024-01-01&end_date=2024-12-31&commission_rate=10&format=json
Authorization: Bearer {token}

Parámetros:
- cobrador_id (opcional): Filtrar por cobrador
- start_date (opcional): Fecha inicial (default: inicio del mes)
- end_date (opcional): Fecha final (default: fin del mes)
- commission_rate (opcional): Porcentaje de comisión (default: 10)
- format (opcional): json|pdf|html|excel (default: json)
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