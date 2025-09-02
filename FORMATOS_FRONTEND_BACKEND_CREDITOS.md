# 📋 **GUÍA COMPLETA: FORMATOS FRONTEND Y BACKEND PARA CONSULTAS DE CRÉDITOS**

## 🎯 **Resumen Ejecutivo**

Este documento define los formatos exactos que debe usar el frontend para comunicarse con el backend en las consultas de créditos, especialmente para cobradores.

---

## 🔧 **1. ENDPOINTS DISPONIBLES**

### **Endpoint Principal de Créditos**
```
GET /api/credits
```

### **Endpoints Adicionales**
```
GET /api/credits/{id}                           - Detalle de crédito específico
GET /api/credits/client/{client_id}            - Créditos por cliente
GET /api/credits/cobrador/{cobrador_id}/stats  - Estadísticas por cobrador
GET /api/credits-requiring-attention           - Créditos que requieren atención
GET /api/debug-cobrador-credits               - Endpoint de debug (temporal)
```

---

## 📤 **2. FORMATO DE REQUEST (FRONTEND → BACKEND)**

### **Headers Requeridos**
```javascript
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json",
  "Accept": "application/json"
}
```

### **Query Parameters Disponibles**

#### **🔍 Filtros Básicos**
```javascript
// URL de ejemplo:
// GET /api/credits?status=active&per_page=20&search=fernando

const queryParams = {
  // Filtros principales
  status: "active|pending_approval|waiting_delivery|completed|defaulted|cancelled",
  client_id: 123,                    // ID específico del cliente
  search: "nombre del cliente",       // Búsqueda por nombre del cliente
  cobrador_id: 456,                  // Solo para admins/managers
  
  // Paginación
  page: 1,                           // Página actual
  per_page: 15,                      // Elementos por página (default: 15)
  
  // Frecuencia de pago
  frequency: "daily,weekly,monthly", // Uno o varios separados por coma
  
  // Rangos de fechas
  start_date_from: "2025-01-01",     // Fecha inicio desde
  start_date_to: "2025-12-31",       // Fecha inicio hasta
  end_date_from: "2025-01-01",       // Fecha fin desde
  end_date_to: "2025-12-31",         // Fecha fin hasta
  
  // Rangos de montos
  amount_min: 100.00,                // Monto mínimo
  amount_max: 5000.00,               // Monto máximo
  total_amount_min: 150.00,          // Monto total mínimo
  total_amount_max: 6000.00,         // Monto total máximo
  balance_min: 50.00,                // Balance mínimo
  balance_max: 3000.00               // Balance máximo
}
```

#### **🌟 Ejemplos de URLs Completas**

```javascript
// 1. Cobrador consultando SUS créditos activos
"GET /api/credits?status=active&per_page=20"

// 2. Cobrador buscando cliente específico
"GET /api/credits?search=fernando&status=active"

// 3. Cobrador con filtros múltiples
"GET /api/credits?frequency=daily,weekly&amount_min=500&status=active"

// 4. Manager consultando créditos de un cobrador específico
"GET /api/credits?cobrador_id=3&status=active"

// 5. Créditos pendientes de aprobación
"GET /api/credits?status=pending_approval"
```

---

## 📥 **3. FORMATO DE RESPONSE (BACKEND → FRONTEND)**

### **✅ Respuesta Exitosa - Estructura Paginada**

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "client_id": 4,
        "created_by": 3,
        "amount": "500.00",
        "interest_rate": "5.00",
        "total_amount": "525.00",
        "balance": "425.00",
        "installment_amount": "17.50",
        "total_installments": 30,
        "frequency": "daily",
        "start_date": "2025-09-01",
        "end_date": "2025-10-01",
        "status": "active",
        "scheduled_delivery_date": null,
        "approved_by": 3,
        "approved_at": "2025-09-01T05:13:09.000000Z",
        "delivered_at": "2025-09-01T05:13:09.000000Z",
        "delivered_by": 3,
        "delivery_notes": null,
        "rejection_reason": null,
        "latitude": null,
        "longitude": null,
        "created_at": "2025-09-01T04:45:23.000000Z",
        "updated_at": "2025-09-01T05:13:09.000000Z",
        
        // Relaciones incluidas
        "client": {
          "id": 4,
          "name": "FERNANDO CLIENTE",
          "email": null,
          "phone": "73682145",
          "address": "AV. EJEMPLO 123",
          "profile_image": null,
          "client_category": "A",
          "assigned_cobrador_id": 3,
          "assigned_manager_id": null,
          "ci": "12345678",
          "latitude": null,
          "longitude": null
        },
        "payments": [
          {
            "id": 1,
            "credit_id": 1,
            "cobrador_id": 3,
            "amount": "50.00",
            "status": "completed",
            "payment_date": "2025-09-01T08:00:00.000000Z",
            "payment_type": "cash",
            "notes": "Pago parcial"
          }
        ],
        "created_by_user": {
          "id": 3,
          "name": "FERNANDO COBRADOR",
          "email": "fernando@cobrador.com",
          "phone": "73682144"
        }
      }
    ],
    "first_page_url": "http://localhost:8000/api/credits?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "http://localhost:8000/api/credits?page=1",
    "links": [
      {
        "url": null,
        "label": "&laquo; Previous",
        "active": false
      },
      {
        "url": "http://localhost:8000/api/credits?page=1",
        "label": "1",
        "active": true
      },
      {
        "url": null,
        "label": "Next &raquo;",
        "active": false
      }
    ],
    "next_page_url": null,
    "path": "http://localhost:8000/api/credits",
    "per_page": 15,
    "prev_page_url": null,
    "to": 1,
    "total": 1
  },
  "message": "Success"
}
```

### **❌ Respuesta de Error**

```json
{
  "success": false,
  "message": "No autorizado",
  "error": "No tienes acceso a este crédito",
  "code": 403
}
```

---

## 💻 **4. EJEMPLOS DE IMPLEMENTACIÓN FRONTEND**

### **🚀 JavaScript/Axios**

```javascript
// Servicio para consultar créditos
class CreditService {
  constructor(apiUrl = 'http://localhost:8000/api', token) {
    this.apiUrl = apiUrl;
    this.token = token;
  }

  // Headers base para todas las requests
  getHeaders() {
    return {
      'Authorization': `Bearer ${this.token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    };
  }

  // Consultar créditos con filtros
  async getCredits(filters = {}) {
    const queryString = new URLSearchParams(filters).toString();
    const url = `${this.apiUrl}/credits${queryString ? '?' + queryString : ''}`;
    
    try {
      const response = await axios.get(url, {
        headers: this.getHeaders()
      });
      
      return {
        success: true,
        data: response.data.data,
        pagination: {
          currentPage: response.data.data.current_page,
          totalPages: response.data.data.last_page,
          perPage: response.data.data.per_page,
          total: response.data.data.total
        }
      };
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || 'Error al obtener créditos',
        code: error.response?.status || 500
      };
    }
  }

  // Ejemplos de uso específicos
  async getActiveCredits(page = 1, perPage = 15) {
    return this.getCredits({
      status: 'active',
      page: page,
      per_page: perPage
    });
  }

  async searchCredits(searchTerm, status = 'active') {
    return this.getCredits({
      search: searchTerm,
      status: status
    });
  }

  async getCreditsByDateRange(startDate, endDate) {
    return this.getCredits({
      start_date_from: startDate,
      start_date_to: endDate,
      status: 'active'
    });
  }
}

// Ejemplo de uso
const creditService = new CreditService('http://localhost:8000/api', userToken);

// 1. Obtener créditos activos
const activeCredits = await creditService.getActiveCredits(1, 20);
console.log('Créditos activos:', activeCredits);

// 2. Buscar créditos por nombre de cliente
const searchResults = await creditService.searchCredits('fernando');
console.log('Resultados de búsqueda:', searchResults);

// 3. Filtros múltiples
const customFilters = await creditService.getCredits({
  status: 'active',
  frequency: 'daily',
  amount_min: 500,
  per_page: 10
});
```

### **📱 Flutter/Dart**

```dart
class CreditService {
  final String apiUrl;
  final String token;
  final http.Client client;

  CreditService({
    required this.apiUrl,
    required this.token,
    http.Client? client,
  }) : client = client ?? http.Client();

  // Headers base
  Map<String, String> get headers => {
    'Authorization': 'Bearer $token',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };

  // Consultar créditos
  Future<ApiResponse<PaginatedCredits>> getCredits({
    Map<String, dynamic>? filters,
  }) async {
    try {
      // Construir URL con filtros
      final uri = Uri.parse('$apiUrl/credits');
      final finalUri = filters != null 
        ? uri.replace(queryParameters: filters.map((k, v) => MapEntry(k, v.toString())))
        : uri;

      final response = await client.get(finalUri, headers: headers);
      
      if (response.statusCode == 200) {
        final jsonData = json.decode(response.body);
        return ApiResponse.success(
          PaginatedCredits.fromJson(jsonData['data'])
        );
      } else {
        final error = json.decode(response.body);
        return ApiResponse.error(
          error['message'] ?? 'Error al obtener créditos',
          response.statusCode
        );
      }
    } catch (e) {
      return ApiResponse.error('Error de conexión: $e');
    }
  }

  // Métodos específicos
  Future<ApiResponse<PaginatedCredits>> getActiveCredits({
    int page = 1,
    int perPage = 15,
  }) {
    return getCredits(filters: {
      'status': 'active',
      'page': page,
      'per_page': perPage,
    });
  }

  Future<ApiResponse<PaginatedCredits>> searchCredits(String searchTerm) {
    return getCredits(filters: {
      'search': searchTerm,
      'status': 'active',
    });
  }
}

// Modelos de datos
class Credit {
  final int id;
  final int clientId;
  final int createdBy;
  final double amount;
  final double totalAmount;
  final double balance;
  final String status;
  final String frequency;
  final DateTime startDate;
  final DateTime endDate;
  final Client client;
  final List<Payment> payments;

  Credit.fromJson(Map<String, dynamic> json)
    : id = json['id'],
      clientId = json['client_id'],
      createdBy = json['created_by'],
      amount = double.parse(json['amount']),
      totalAmount = double.parse(json['total_amount']),
      balance = double.parse(json['balance']),
      status = json['status'],
      frequency = json['frequency'],
      startDate = DateTime.parse(json['start_date']),
      endDate = DateTime.parse(json['end_date']),
      client = Client.fromJson(json['client']),
      payments = (json['payments'] as List)
          .map((p) => Payment.fromJson(p))
          .toList();
}

// Ejemplo de uso en Flutter
class CreditsScreen extends StatefulWidget {
  @override
  _CreditsScreenState createState() => _CreditsScreenState();
}

class _CreditsScreenState extends State<CreditsScreen> {
  final CreditService creditService = CreditService(
    apiUrl: 'http://localhost:8000/api',
    token: UserSession.token,
  );

  List<Credit> credits = [];
  bool isLoading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    loadCredits();
  }

  Future<void> loadCredits() async {
    setState(() {
      isLoading = true;
      error = null;
    });

    final response = await creditService.getActiveCredits();
    
    if (response.success) {
      setState(() {
        credits = response.data!.data;
        isLoading = false;
      });
    } else {
      setState(() {
        error = response.error;
        isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return Center(child: CircularProgressIndicator());
    }

    if (error != null) {
      return Center(child: Text('Error: $error'));
    }

    return ListView.builder(
      itemCount: credits.length,
      itemBuilder: (context, index) {
        final credit = credits[index];
        return CreditCard(credit: credit);
      },
    );
  }
}
```

---

## 🔒 **5. LÓGICA DE AUTORIZACIÓN EN EL BACKEND**

### **Para Cobradores:**
```php
// El backend automáticamente filtra solo los créditos que el cobrador puede ver:
// 1. Créditos creados por el cobrador (created_by = cobrador_id)
// 2. Créditos de clientes asignados al cobrador (client.assigned_cobrador_id = cobrador_id)

if ($currentUser->hasRole('cobrador')) {
    $query->where(function ($q) use ($currentUser) {
        $q->where('created_by', $currentUser->id)
            ->orWhereHas('client', function ($q2) use ($currentUser) {
                $q2->where('assigned_cobrador_id', $currentUser->id);
            });
    });
}
```

### **Para Managers:**
```php
// Los managers ven créditos de:
// 1. Clientes asignados directamente al manager
// 2. Clientes de cobradores bajo su supervisión

if ($currentUser->hasRole('manager')) {
    // Lógica compleja de clientes directos + indirectos
    $query->where(function ($q) use ($currentUser, $allClientIds) {
        $q->where('created_by', $currentUser->id)
            ->orWhereIn('client_id', $allClientIds);
    });
}
```

### **Para Admins:**
```php
// Los admins ven todos los créditos sin restricciones
// No se aplica ningún filtro adicional
```

---

## 🐛 **6. DEBUGGING Y TROUBLESHOOTING**

### **Endpoint de Debug (Temporal)**
```javascript
// Para diagnosticar problemas con créditos de cobrador
GET /api/debug-cobrador-credits?status=active

// Respuesta incluye información detallada de debug:
{
  "success": true,
  "debug": {
    "authenticated_user": {
      "id": 3,
      "name": "FERNANDO COBRADOR",
      "roles": ["cobrador"]
    },
    "query_results": {
      "total_found": 1,
      "credits": [...]
    },
    "cobrador_info": {
      "assigned_clients_count": 1,
      "credits_created_directly": 1
    }
  }
}
```

### **Estados de Créditos Importantes**

| Estado | Descripción | Cuándo aparece |
|--------|-------------|----------------|
| `pending_approval` | Pendiente de aprobación | Crédito recién creado |
| `waiting_delivery` | Esperando entrega | Aprobado pero no entregado |
| `active` | Activo | Entregado al cliente |
| `completed` | Completado | Totalmente pagado |
| `defaulted` | En mora | Pagos atrasados |
| `cancelled` | Cancelado | Cancelado por admin |

---

## ✅ **7. CHECKLIST DE IMPLEMENTACIÓN**

### **Frontend:**
- [ ] Implementar headers de autorización correctos
- [ ] Manejar paginación de resultados
- [ ] Implementar filtros por estado (especialmente `active`)
- [ ] Manejar respuestas de error apropiadamente
- [ ] Implementar búsqueda por nombre de cliente
- [ ] Mostrar información completa del cliente y pagos

### **Testing:**
- [ ] Probar autenticación como cobrador
- [ ] Verificar filtrado automático por rol
- [ ] Probar filtros múltiples
- [ ] Verificar paginación
- [ ] Probar manejo de errores
- [ ] Verificar diferentes estados de créditos

---

## 🚨 **PROBLEMA RESUELTO: Estado de Créditos**

**⚠️ IMPORTANTE:** El problema original era que los créditos creados por cobradores estaban en estado `pending_approval` pero el frontend probablemente filtraba solo por `active`.

**✅ SOLUCIÓN:**
1. **Frontend:** Consultar todos los estados relevantes o permitir filtrar por estado
2. **Backend:** Ya funciona correctamente - activé el crédito de prueba
3. **Flujo:** `pending_approval` → `waiting_delivery` → `active`

```javascript
// ✅ CORRECTO: Mostrar créditos activos
GET /api/credits?status=active

// ✅ CORRECTO: Mostrar todos los créditos del cobrador
GET /api/credits

// ✅ CORRECTO: Mostrar créditos pendientes
GET /api/credits?status=pending_approval
```

---

*📝 Documento generado: 2025-09-01*
*🔧 Versión del sistema: Laravel 10 + Sanctum*
*👨‍💻 Para el equipo de desarrollo del sistema de cobradores*
