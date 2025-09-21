# Documentación de Endpoints API - Sistema de Cobros

Esta documentación describe los endpoints principales de la API para el sistema de control de cajas y cobros. Incluye los datos JSON que el backend recibe (request) y entrega (response).

## Autenticación

Todos los endpoints requieren autenticación mediante token Bearer en el header `Authorization: Bearer {token}`.

## Estructura General de Respuestas

Las respuestas exitosas siguen el formato:

```json
{
  "success": true,
  "data": { ... },
  "message": "Operación exitosa"
}
```

Las respuestas de error:

```json
{
  "success": false,
  "message": "Descripción del error",
  "errors": { ... }
}
```

---

## 1. CashBalanceController - Control de Balances de Efectivo

### GET /api/cash-balances

**Listar balances de efectivo**

**Request Query Parameters:**

```json
{
  "cobrador_id": "integer (opcional)",
  "date_from": "YYYY-MM-DD (opcional)",
  "date_to": "YYYY-MM-DD (opcional)",
  "page": "integer (opcional)",
  "per_page": "integer (opcional)"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "cobrador_id": 2,
        "date": "2025-09-20",
        "initial_amount": 1000.00,
        "collected_amount": 500.00,
        "lent_amount": 300.00,
        "final_amount": 1200.00,
        "cobrador": {
          "id": 2,
          "name": "Juan Pérez"
        }
      }
    ],
    "total": 1
  }
}
```

### POST /api/cash-balances

**Crear balance de efectivo**

**Request Body:**

```json
{
  "cobrador_id": "integer (requerido)",
  "date": "YYYY-MM-DD (requerido)",
  "initial_amount": "numeric min:0 (requerido)",
  "collected_amount": "numeric min:0 (requerido)",
  "lent_amount": "numeric min:0 (requerido)",
  "final_amount": "numeric min:0 (requerido)"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "cobrador_id": 2,
    "date": "2025-09-20",
    "initial_amount": 1000.00,
    "collected_amount": 500.00,
    "lent_amount": 300.00,
    "final_amount": 1200.00,
    "cobrador": {
      "id": 2,
      "name": "Juan Pérez"
    }
  },
  "message": "Balance de efectivo creado exitosamente"
}
```

### GET /api/cash-balances/{id}

**Mostrar balance específico**

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "cobrador_id": 2,
    "date": "2025-09-20",
    "initial_amount": 1000.00,
    "collected_amount": 500.00,
    "lent_amount": 300.00,
    "final_amount": 1200.00,
    "cobrador": {
      "id": 2,
      "name": "Juan Pérez"
    }
  }
}
```

### PUT /api/cash-balances/{id}

**Actualizar balance**

**Request Body:** (mismos campos que POST)

**Response:** (mismo formato que POST)

### DELETE /api/cash-balances/{id}

**Eliminar balance**

**Response:**

```json
{
  "success": true,
  "data": [],
  "message": "Balance de efectivo eliminado exitosamente"
}
```

### GET /api/cash-balances/cobrador/{cobrador}

**Obtener balances por cobrador**

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "date": "2025-09-20",
      "initial_amount": 1000.00,
      "collected_amount": 500.00,
      "lent_amount": 300.00,
      "final_amount": 1200.00
    }
  ]
}
```

### GET /api/cash-balances/cobrador/{cobrador}/summary

**Resumen de balances por cobrador**

**Response:**

```json
{
  "success": true,
  "data": {
    "total_initial": 1000.00,
    "total_collected": 500.00,
    "total_lent": 300.00,
    "total_final": 1200.00
  }
}
```

### GET /api/cash-balances/{id}/detailed

**Balance detallado con reconciliación**

**Response:**

```json
{
  "success": true,
  "data": {
    "cash_balance": { ... },
    "payments": [ ... ],
    "credits": [ ... ],
    "reconciliation": {
      "expected_final": 1200.00,
      "actual_final": 1200.00,
      "difference": 0.00,
      "is_balanced": true
    }
  },
  "message": "Balance detallado obtenido exitosamente"
}
```

### POST /api/cash-balances/auto-calculate

**Crear balance con cálculo automático**

**Request Body:**

```json
{
  "cobrador_id": "integer (requerido)",
  "date": "YYYY-MM-DD (requerido)",
  "initial_amount": "numeric min:0 (requerido)",
  "final_amount": "numeric min:0 (requerido)"
}
```

**Response:** (mismo formato que POST normal)

---

## 2. CreditController - Gestión de Créditos

### GET /api/credits

**Listar créditos**

**Request Query Parameters:**

```json
{
  "client_id": "integer (opcional)",
  "status": "string (opcional)",
  "search": "string (opcional)",
  "cobrador_id": "integer (opcional)",
  "page": "integer (opcional)",
  "per_page": "integer (opcional)"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "client_id": 3,
        "created_by": 2,
        "amount": 1000.00,
        "balance": 800.00,
        "frequency": "daily",
        "start_date": "2025-09-20",
        "end_date": "2025-10-20",
        "status": "active",
        "total_paid": 200.00,
        "pending_installments": 5,
        "is_overdue": false,
        "client": {
          "id": 3,
          "name": "María García"
        },
        "payments": [ ... ]
      }
    ],
    "total": 1
  }
}
```

### POST /api/credits

**Crear crédito**

**Request Body:**

```json
{
  "client_id": "integer (requerido)",
  "cobrador_id": "integer (opcional)",
  "amount": "numeric min:0 (requerido)",
  "balance": "numeric min:0 (requerido)",
  "frequency": "daily|weekly|biweekly|monthly (requerido)",
  "start_date": "YYYY-MM-DD (requerido)",
  "end_date": "YYYY-MM-DD (requerido)",
  "status": "pending_approval|waiting_delivery|active|completed (opcional)",
  "scheduled_delivery_date": "YYYY-MM-DD (opcional)",
  "immediate_delivery_requested": "boolean (opcional)",
  "interest_rate_id": "integer (opcional)",
  "total_installments": "integer min:1 (opcional)",
  "latitude": "numeric -90 to 90 (opcional)",
  "longitude": "numeric -180 to 180 (opcional)"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "client_id": 3,
    "amount": 1000.00,
    "balance": 1000.00,
    "frequency": "daily",
    "start_date": "2025-09-20",
    "end_date": "2025-10-20",
    "status": "active",
    "client": {
      "id": 3,
      "name": "María García"
    },
    "payments": [],
    "createdBy": {
      "id": 2,
      "name": "Juan Pérez"
    }
  },
  "message": "Crédito creado exitosamente"
}
```

### GET /api/credits/{id}

**Mostrar crédito específico**

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "client_id": 3,
    "amount": 1000.00,
    "balance": 800.00,
    "frequency": "daily",
    "start_date": "2025-09-20",
    "end_date": "2025-10-20",
    "status": "active",
    "total_paid": 200.00,
    "pending_installments": 5,
    "client": {
      "id": 3,
      "name": "María García"
    },
    "payments": [ ... ],
    "createdBy": {
      "id": 2,
      "name": "Juan Pérez"
    }
  }
}
```

### PUT /api/credits/{id}

**Actualizar crédito**

**Request Body:** (mismos campos que POST, opcionales según permisos)

**Response:** (mismo formato que GET)

### DELETE /api/credits/{id}

**Eliminar crédito**

**Response:**

```json
{
  "success": true,
  "data": [],
  "message": "Crédito eliminado exitosamente"
}
```

### GET /api/credits/client/{client}

**Créditos por cliente**

**Response:** Array de créditos (mismo formato que GET /api/credits)

### GET /api/credits/cobrador/{cobrador}

**Créditos por cobrador**

**Response:** Array de créditos con paginación

### GET /api/credits/cobrador/{cobrador}/stats

**Estadísticas de cobrador**

**Response:**

```json
{
  "success": true,
  "data": {
    "total_credits": 10,
    "total_amount": 10000.00,
    "total_balance": 5000.00,
    "active_credits": 8,
    "completed_credits": 2
  }
}
```

---

## 3. PaymentController - Gestión de Pagos

### GET /api/payments

**Listar pagos**

**Request Query Parameters:**

```json
{
  "credit_id": "integer (opcional)",
  "received_by": "integer (opcional)",
  "payment_method": "cash|transfer|check|other (opcional)",
  "date_from": "YYYY-MM-DD (opcional)",
  "date_to": "YYYY-MM-DD (opcional)",
  "amount_min": "numeric (opcional)",
  "amount_max": "numeric (opcional)",
  "page": "integer (opcional)"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "credit_id": 1,
        "client_id": 3,
        "cobrador_id": 2,
        "amount": 50.00,
        "payment_method": "cash",
        "payment_date": "2025-09-20",
        "received_by": 2,
        "status": "completed",
        "cobrador": {
          "id": 2,
          "name": "Juan Pérez"
        },
        "credit": {
          "id": 1,
          "client": {
            "id": 3,
            "name": "María García"
          }
        }
      }
    ],
    "total": 1
  }
}
```

### POST /api/payments

**Crear pago**

**Request Body:**

```json
{
  "credit_id": "integer (requerido)",
  "amount": "numeric min:0.01 (requerido)",
  "payment_method": "cash|transfer|check|other (requerido)",
  "payment_date": "YYYY-MM-DD (opcional)",
  "latitude": "numeric -90 to 90 (opcional)",
  "longitude": "numeric -180 to 180 (opcional)"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "credit_id": 1,
    "amount": 50.00,
    "payment_method": "cash",
    "payment_date": "2025-09-20",
    "received_by": 2,
    "status": "completed",
    "credit": {
      "id": 1,
      "client": {
        "id": 3,
        "name": "María García"
      }
    }
  },
  "message": "Pago registrado exitosamente"
}
```

### GET /api/payments/{id}

**Mostrar pago específico**

**Response:** Objeto de pago individual (mismo formato que en lista)

### PUT /api/payments/{id}

**Actualizar pago**

**Request Body:**

```json
{
  "amount": "numeric min:0.01 (opcional)",
  "payment_method": "cash|transfer|check|other (opcional)",
  "payment_date": "YYYY-MM-DD (opcional)",
  "notes": "string max:1000 (opcional)"
}
```

**Response:** (mismo formato que GET)

### DELETE /api/payments/{id}

**Eliminar pago**

**Response:**

```json
{
  "success": true,
  "data": [],
  "message": "Pago eliminado exitosamente"
}
```

### GET /api/payments/credit/{credit}

**Pagos por crédito**

**Response:** Array de pagos

### GET /api/payments/cobrador/{cobrador}

**Pagos por cobrador**

**Response:** Array de pagos con paginación

### GET /api/payments/cobrador/{cobrador}/stats

**Estadísticas de pagos por cobrador**

**Response:**

```json
{
  "success": true,
  "data": {
    "total_payments": 20,
    "total_amount": 1000.00,
    "average_payment": 50.00,
    "payments_today": 5,
    "payments_this_month": 15
  }
}
```

### GET /api/payments/recent

**Pagos recientes**

**Response:** Array de pagos recientes

### GET /api/payments/today-summary

**Resumen de pagos del día**

**Response:**

```json
{
  "success": true,
  "data": {
    "total_payments": 5,
    "total_amount": 250.00,
    "payments_by_method": {
      "cash": 3,
      "transfer": 2
    },
    "payments_by_cobrador": [ ... ]
  }
}
```

---

## 4. ReportController - Reportes

### GET /api/reports/payments

**Reporte de pagos**

**Request Query Parameters:**

```json
{
  "start_date": "YYYY-MM-DD (opcional)",
  "end_date": "YYYY-MM-DD (opcional)",
  "cobrador_id": "integer (opcional)",
  "format": "pdf|html|json|excel (opcional)"
}
```

**Response (JSON):**

```json
{
  "success": true,
  "data": {
    "payments": [ ... ],
    "summary": {
      "total_payments": 20,
      "total_amount": 1000.00,
      "average_payment": 50.00,
      "date_range": {
        "start": "2025-09-01",
        "end": "2025-09-20"
      }
    },
    "generated_at": "2025-09-20T10:00:00Z",
    "generated_by": "Juan Pérez"
  },
  "message": "Datos del reporte de pagos obtenidos exitosamente"
}
```

### GET /api/reports/credits

**Reporte de créditos**

**Request Query Parameters:**

```json
{
  "status": "active|completed|pending_approval|waiting_delivery (opcional)",
  "cobrador_id": "integer (opcional)",
  "client_id": "integer (opcional)",
  "format": "pdf|html|json|excel (opcional)"
}
```

**Response (JSON):**

```json
{
  "success": true,
  "data": {
    "credits": [ ... ],
    "summary": {
      "total_credits": 10,
      "total_amount": 10000.00,
      "active_credits": 8,
      "completed_credits": 2
    },
    "generated_at": "2025-09-20T10:00:00Z",
    "generated_by": "Juan Pérez"
  },
  "message": "Datos del reporte de créditos obtenidos exitosamente"
}
```

### GET /api/reports/balances

**Reporte de balances**

**Request Query Parameters:**

```json
{
  "start_date": "YYYY-MM-DD (opcional)",
  "end_date": "YYYY-MM-DD (opcional)",
  "cobrador_id": "integer (opcional)",
  "format": "pdf|html|json|excel (opcional)"
}
```

**Response (JSON):**

```json
{
  "success": true,
  "data": {
    "balances": [ ... ],
    "summary": {
      "total_balances": 5,
      "total_initial": 5000.00,
      "total_collected": 2500.00,
      "total_lent": 1500.00,
      "total_final": 6000.00
    },
    "generated_at": "2025-09-20T10:00:00Z",
    "generated_by": "Juan Pérez"
  },
  "message": "Datos del reporte de balances obtenidos exitosamente"
}
```

### GET /api/reports/types

**Tipos de reportes disponibles**

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "type": "payments",
      "name": "Reporte de Pagos",
      "description": "Pagos realizados en un período",
      "formats": ["pdf", "html", "json", "excel"]
    },
    {
      "type": "credits",
      "name": "Reporte de Créditos",
      "description": "Créditos activos y completados",
      "formats": ["pdf", "html", "json", "excel"]
    },
    {
      "type": "balances",
      "name": "Reporte de Balances",
      "description": "Balances de efectivo por cobrador",
      "formats": ["pdf", "html", "json", "excel"]
    },
    {
      "type": "users",
      "name": "Reporte de Usuarios",
      "description": "Usuarios del sistema por roles",
      "formats": ["pdf", "html", "json", "excel"]
    }
  ]
}
```

---

## Notas Importantes

1. **Permisos por Roles:**
   - **Cobrador**: Solo puede ver/modificar sus propios datos
   - **Manager**: Puede ver datos de sus cobradores asignados
   - **Admin**: Acceso completo

2. **Validaciones Comunes:**
   - Fechas en formato YYYY-MM-DD
   - Montos numéricos con hasta 2 decimales
   - IDs deben existir en la base de datos

3. **Paginación:**
   - Parámetro `per_page` para controlar elementos por página
   - Respuestas incluyen metadata de paginación

4. **Formatos de Reportes:**
   - `json`: Datos estructurados
   - `html`: Vista para navegador
   - `pdf`: Archivo descargable
   - `excel`: Hoja de cálculo

5. **Coordenadas GPS:**
   - Latitude: -90 a 90
   - Longitude: -180 a 180

Esta documentación cubre los endpoints principales para control de cajas. Para endpoints adicionales o detalles específicos, consulta la documentación del código fuente.
