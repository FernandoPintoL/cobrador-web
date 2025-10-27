# 📍 API Endpoint: Location Clusters (Mapas con Clustering)

## Overview

Este endpoint agrupa clientes que comparten la misma ubicación geográfica para evitar múltiples marcadores superpuestos en el mapa. Es ideal para Flutter mobile ya que proporciona toda la información necesaria en una única llamada.

## Endpoint

```
GET /api/map/location-clusters
```

## Query Parameters (Filtros opcionales)

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `status` | string | No | `overdue`, `pending`, `paid` - Filtrar por estado de pago |
| `cobrador_id` | integer | No | Solo para admin/manager: Filtrar por cobrador específico |

## Ejemplos de uso

```bash
# Obtener todos los clusters
GET /api/map/location-clusters

# Filtrar solo clientes con pagos vencidos
GET /api/map/location-clusters?status=overdue

# Filtrar clusters de un cobrador específico (solo admin/manager)
GET /api/map/location-clusters?cobrador_id=5

# Combinar filtros
GET /api/map/location-clusters?status=pending&cobrador_id=5
```

---

## Response Structure

### HTTP 200 - Success

```json
{
  "success": true,
  "data": [
    {
      "cluster_id": "19.4326,-99.1332",
      "location": {
        "latitude": 19.4326,
        "longitude": -99.1332,
        "address": "Calle Principal 123"
      },
      "cluster_summary": {
        "total_people": 3,
        "total_credits": 5,
        "total_amount": 5500.00,
        "total_balance": 3500.00,
        "overdue_count": 2,
        "overdue_amount": 1500.00,
        "active_count": 3,
        "active_amount": 2000.00,
        "completed_count": 0,
        "completed_amount": 0.00
      },
      "cluster_status": "overdue",
      "people": [
        {
          "person_id": 1,
          "name": "Juan García",
          "phone": "555-1234",
          "email": "juan@example.com",
          "address": "Calle Principal 123",
          "client_category": "A",
          "total_credits": 2,
          "total_amount": 1700.00,
          "total_paid": 300.00,
          "total_balance": 1400.00,
          "person_status": "overdue",
          "payment_stats": {
            "total_payments": 5,
            "paid_payments": 3,
            "pending_payments": 1,
            "overdue_payments": 1,
            "total_paid_amount": 300.00,
            "total_pending_amount": 100.00,
            "total_overdue_amount": 200.00,
            "last_payment": {
              "date": "2024-10-20",
              "amount": 100.00,
              "method": "cash",
              "status": "paid"
            }
          },
          "credits": [
            {
              "credit_id": 101,
              "amount": 800.00,
              "balance": 500.00,
              "paid_amount": 300.00,
              "payment_percentage": 37.50,
              "status": "active",
              "start_date": "2024-01-15",
              "end_date": "2024-12-31",
              "days_until_due": 0,
              "overdue_days": 5,
              "next_payment_due": {
                "date": "2024-11-20",
                "amount": 66.67,
                "installment": 2
              },
              "last_payment": {
                "date": "2024-10-20",
                "amount": 100.00,
                "method": "cash",
                "status": "paid"
              },
              "payment_stats": {
                "total_payments": 3,
                "paid_payments": 3,
                "pending_payments": 0,
                "overdue_payments": 0
              },
              "recent_payments": [
                {
                  "payment_id": 501,
                  "amount": 100.00,
                  "date": "2024-10-20",
                  "method": "cash",
                  "status": "paid",
                  "installment_num": 1
                },
                {
                  "payment_id": 500,
                  "amount": 100.00,
                  "date": "2024-09-15",
                  "method": "transfer",
                  "status": "paid",
                  "installment_num": 0
                }
              ]
            },
            {
              "credit_id": 102,
              "amount": 900.00,
              "balance": 900.00,
              "paid_amount": 0.00,
              "payment_percentage": 0.00,
              "status": "active",
              "start_date": "2024-09-01",
              "end_date": "2025-08-31",
              "days_until_due": 315,
              "overdue_days": 0,
              "next_payment_due": {
                "date": "2024-11-01",
                "amount": 75.00,
                "installment": 1
              },
              "last_payment": null,
              "payment_stats": {
                "total_payments": 0,
                "paid_payments": 0,
                "pending_payments": 0,
                "overdue_payments": 0
              },
              "recent_payments": []
            }
          ]
        },
        {
          "person_id": 2,
          "name": "María García",
          "phone": "555-5678",
          "total_credits": 2,
          "total_balance": 1600.00,
          "person_status": "overdue",
          "credits": [
            {
              "credit_id": 103,
              "amount": 700.00,
              "balance": 700.00,
              "status": "active",
              "start_date": "2024-02-20",
              "end_date": "2024-11-20",
              "overdue_days": 7,
              "last_payment_date": null,
              "last_payment_amount": null
            },
            {
              "credit_id": 104,
              "amount": 1200.00,
              "balance": 900.00,
              "status": "active",
              "start_date": "2024-08-10",
              "end_date": "2025-07-10",
              "overdue_days": 0,
              "last_payment_date": "2024-10-15",
              "last_payment_amount": 300.00
            }
          ]
        },
        {
          "person_id": 3,
          "name": "Carlos García",
          "phone": "555-9999",
          "total_credits": 1,
          "total_balance": 500.00,
          "person_status": "pending",
          "credits": [
            {
              "credit_id": 105,
              "amount": 2000.00,
              "balance": 500.00,
              "status": "active",
              "start_date": "2024-05-01",
              "end_date": "2025-04-30",
              "overdue_days": 0,
              "last_payment_date": "2024-10-18",
              "last_payment_amount": 1500.00
            }
          ]
        }
      ]
    }
  ],
  "message": "Clusters de ubicaciones obtenidos exitosamente"
}
```

### HTTP 500 - Error

```json
{
  "success": false,
  "message": "Error al obtener clusters de ubicaciones",
  "error": "Descripción del error"
}
```

---

## Field Definitions

### Root Level

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `success` | boolean | Indica si la operación fue exitosa |
| `data` | array | Array de clusters de ubicaciones |
| `message` | string | Mensaje descriptivo del resultado |

### Cluster Object (data[])

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `cluster_id` | string | ID único del cluster (formato: "lat,lng") |
| `location` | object | Información de ubicación |
| `cluster_summary` | object | Resumen agregado del cluster |
| `cluster_status` | string | Estado general: `overdue`, `pending`, `paid` |
| `people` | array | Array de personas en esta ubicación |

### location Object

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `latitude` | number | Latitud (decimal) |
| `longitude` | number | Longitud (decimal) |
| `address` | string | Dirección física |

### cluster_summary Object

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `total_people` | integer | Cantidad de personas en la ubicación |
| `total_credits` | integer | Total de créditos en la ubicación |
| `total_amount` | number | Monto total de créditos (Bs) |
| `total_balance` | number | Saldo pendiente total (Bs) |
| `overdue_count` | integer | Cantidad de créditos vencidos |
| `overdue_amount` | number | Monto vencido (Bs) |
| `active_count` | integer | Cantidad de créditos activos |
| `active_amount` | number | Monto activo (Bs) |
| `completed_count` | integer | Cantidad de créditos completados |
| `completed_amount` | number | Monto completado (Bs) |

### Person Object (people[])

#### Información Personal

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `person_id` | integer | ID del cliente/persona |
| `name` | string | Nombre completo |
| `phone` | string | Teléfono de contacto |
| `email` | string | Correo electrónico |
| `address` | string | Dirección física |
| `client_category` | string | Categoría: `A` (VIP), `B` (Normal), `C` (Mal) |

#### Información de Créditos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `total_credits` | integer | Cantidad de créditos activos |
| `total_amount` | number | Monto total de créditos (Bs) |
| `total_paid` | number | Total pagado en todos los créditos (Bs) |
| `total_balance` | number | Saldo pendiente total (Bs) |

#### Estado

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `person_status` | string | Estado general: `overdue`, `pending`, `paid` |
| `payment_stats` | object | Estadísticas de pagos (ver detalles abajo) |

#### Subobjeto: payment_stats

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `total_payments` | integer | Total de pagos realizados |
| `paid_payments` | integer | Cantidad de pagos confirmados |
| `pending_payments` | integer | Cantidad de pagos pendientes |
| `overdue_payments` | integer | Cantidad de pagos vencidos |
| `total_paid_amount` | number | Total pagado (Bs) |
| `total_pending_amount` | number | Total pendiente (Bs) |
| `total_overdue_amount` | number | Total vencido (Bs) |
| `last_payment` | object\|null | Información del último pago |

#### Subobjeto: payment_stats.last_payment

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `date` | string | Fecha de pago (YYYY-MM-DD) |
| `amount` | number | Monto pagado (Bs) |
| `method` | string | Método: `cash`, `transfer`, `qr`, `check` |
| `status` | string | Estado: `paid`, `pending`, `overdue` |

#### Créditos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `credits` | array | Array de créditos detallados |

---

### Credit Object (people[].credits[])

#### Información Básica

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `credit_id` | integer | ID único del crédito |
| `amount` | number | Monto original del crédito (Bs) |
| `balance` | number | Saldo pendiente actual (Bs) |
| `paid_amount` | number | Total pagado hasta ahora (Bs) |
| `payment_percentage` | number | Porcentaje del crédito pagado (0-100) |
| `status` | string | Estado: `active`, `completed`, `pending_approval`, `waiting_delivery` |

#### Fechas

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `start_date` | string | Fecha de inicio (YYYY-MM-DD) |
| `end_date` | string | Fecha de vencimiento (YYYY-MM-DD) |
| `days_until_due` | integer | Días hasta el vencimiento (0 si ya venció) |
| `overdue_days` | integer | Días vencidos (0 si está al día) |

#### Próximo Pago

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `next_payment_due` | object\|null | Información del próximo pago esperado |

#### Subobjeto: next_payment_due

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `date` | string | Fecha esperada del próximo pago (YYYY-MM-DD) |
| `amount` | number | Monto esperado del próximo pago (Bs) |
| `installment` | integer | Número de cuota/instalación |

#### Último Pago

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `last_payment` | object\|null | Detalles del último pago registrado |

#### Subobjeto: last_payment

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `date` | string | Fecha del pago (YYYY-MM-DD) |
| `amount` | number | Monto pagado (Bs) |
| `method` | string | Método: `cash`, `transfer`, `qr`, `check` |
| `status` | string | Estado: `paid`, `pending`, `overdue` |

#### Estadísticas de Pagos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `payment_stats` | object | Estadísticas generales de pagos del crédito |

#### Subobjeto: payment_stats

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `total_payments` | integer | Total de pagos registrados |
| `paid_payments` | integer | Cantidad de pagos confirmados |
| `pending_payments` | integer | Cantidad de pagos pendientes |
| `overdue_payments` | integer | Cantidad de pagos vencidos |

#### Pagos Recientes

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `recent_payments` | array | Últimos 5 pagos registrados (ordenado por fecha desc) |

#### Subobjeto: recent_payments[]

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `payment_id` | integer | ID del pago |
| `amount` | number | Monto del pago (Bs) |
| `date` | string | Fecha del pago (YYYY-MM-DD) |
| `method` | string | Método: `cash`, `transfer`, `qr`, `check` |
| `status` | string | Estado: `paid`, `pending`, `overdue` |
| `installment_num` | integer | Número de cuota |

---

## Status Values Reference

### cluster_status / person_status

- `overdue` - Tiene pagos vencidos
- `pending` - Tiene pagos pendientes pero sin vencer
- `paid` - Todos los pagos están al día

### credit status

- `active` - Crédito activo y en cobro
- `completed` - Crédito completamente pagado
- `pending_approval` - Crédito pendiente de aprobación
- `waiting_delivery` - Crédito esperando entrega
- `rejected` - Crédito rechazado

---

## How to Use in Flutter

### 1. Parse the JSON Response

```dart
// Define your models
class LocationCluster {
  final String clusterId;
  final LocationInfo location;
  final ClusterSummary clusterSummary;
  final String clusterStatus;
  final List<Person> people;

  LocationCluster.fromJson(Map<String, dynamic> json) :
    clusterId = json['cluster_id'],
    location = LocationInfo.fromJson(json['location']),
    clusterSummary = ClusterSummary.fromJson(json['cluster_summary']),
    clusterStatus = json['cluster_status'],
    people = List<Person>.from(json['people'].map((p) => Person.fromJson(p)));
}

class LocationInfo {
  final double latitude;
  final double longitude;
  final String address;

  LocationInfo.fromJson(Map<String, dynamic> json) :
    latitude = json['latitude'],
    longitude = json['longitude'],
    address = json['address'];
}

// ... rest of models
```

### 2. Display Clusters on Map

```dart
// For each cluster, create a map marker
clusters.forEach((cluster) {
  // Create marker at cluster location
  final marker = Marker(
    position: LatLng(
      cluster.location.latitude,
      cluster.location.longitude
    ),
    title: cluster.location.address,
    infoWindow: InfoWindow(
      title: '${cluster.clusterSummary.totalCredits} créditos',
      snippet: 'Bs ${cluster.clusterSummary.totalBalance}',
    ),
    onTap: () {
      // Show bottom sheet with people and credits
      showClusterDetails(cluster);
    },
  );
});
```

### 3. Show Cluster Details in Bottom Sheet

```dart
void showClusterDetails(LocationCluster cluster) {
  showModalBottomSheet(
    context: context,
    builder: (context) => ListView(
      children: [
        // Header
        Container(
          padding: EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                cluster.location.address,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              SizedBox(height: 8),
              Text(
                '${cluster.clusterSummary.totalPeople} personas | '
                '${cluster.clusterSummary.totalCredits} créditos',
              ),
            ],
          ),
        ),
        Divider(),

        // People List
        ...cluster.people.map((person) => ExpansionTile(
          title: Text(person.name),
          subtitle: Text(
            'Bs ${person.totalBalance} | ${person.totalCredits} créditos',
          ),
          children: [
            // Credits for this person
            ...person.credits.map((credit) => ListTile(
              title: Text('Crédito #${credit.creditId}'),
              subtitle: Text(
                'Saldo: Bs ${credit.balance} | '
                'Vencimiento: ${credit.endDate}',
              ),
              trailing: Chip(
                label: Text(credit.status),
                backgroundColor: _getStatusColor(credit.status),
              ),
            )),
          ],
        )),
      ],
    ),
  );
}

Color _getStatusColor(String status) {
  switch (status) {
    case 'overdue':
      return Colors.red;
    case 'pending':
      return Colors.orange;
    default:
      return Colors.green;
  }
}
```

---

## Performance Tips for Flutter

1. **Cache the clusters** locally after fetching
2. **Update only when needed** - Use `cluster_id` to identify changes
3. **Lazy load credit details** - Only expand/load details on tap
4. **Use pagination** if there are many clusters (future enhancement)
5. **Show loading indicator** while fetching from API

---

## Permissions

| Role | Access |
|------|--------|
| **Admin** | Ve todos los clusters |
| **Manager** | Ve clusters de sus cobradores asignados |
| **Cobrador** | Ve solo sus clusters asignados |

---

## Error Handling

```dart
try {
  final response = await http.get(
    Uri.parse('http://192.168.1.23:9000/api/map/location-clusters'),
    headers: {'Authorization': 'Bearer $token'},
  );

  if (response.statusCode == 200) {
    final json = jsonDecode(response.body);
    final clusters = List<LocationCluster>.from(
      json['data'].map((c) => LocationCluster.fromJson(c))
    );
    // Use clusters
  } else if (response.statusCode == 403) {
    // User doesn't have permission
  } else if (response.statusCode == 500) {
    // Server error
  }
} catch (e) {
  // Network error
}
```

---

## Example: Complete Dart Models

```dart
class ApiResponse<T> {
  final bool success;
  final List<T> data;
  final String message;

  ApiResponse.fromJson(Map<String, dynamic> json, T Function(Map<String, dynamic>) fromJsonT) :
    success = json['success'],
    data = List<T>.from(json['data'].map((item) => fromJsonT(item))),
    message = json['message'];
}

class LocationCluster {
  final String clusterId;
  final LocationInfo location;
  final ClusterSummary clusterSummary;
  final String clusterStatus;
  final List<Person> people;

  LocationCluster({
    required this.clusterId,
    required this.location,
    required this.clusterSummary,
    required this.clusterStatus,
    required this.people,
  });

  factory LocationCluster.fromJson(Map<String, dynamic> json) {
    return LocationCluster(
      clusterId: json['cluster_id'],
      location: LocationInfo.fromJson(json['location']),
      clusterSummary: ClusterSummary.fromJson(json['cluster_summary']),
      clusterStatus: json['cluster_status'],
      people: List<Person>.from(
        json['people'].map((p) => Person.fromJson(p))
      ),
    );
  }
}

class LocationInfo {
  final double latitude;
  final double longitude;
  final String address;

  LocationInfo({
    required this.latitude,
    required this.longitude,
    required this.address,
  });

  factory LocationInfo.fromJson(Map<String, dynamic> json) {
    return LocationInfo(
      latitude: json['latitude'],
      longitude: json['longitude'],
      address: json['address'],
    );
  }
}

class ClusterSummary {
  final int totalPeople;
  final int totalCredits;
  final double totalAmount;
  final double totalBalance;
  final int overdueCount;
  final double overdueAmount;
  final int activeCount;
  final double activeAmount;
  final int completedCount;
  final double completedAmount;

  ClusterSummary({
    required this.totalPeople,
    required this.totalCredits,
    required this.totalAmount,
    required this.totalBalance,
    required this.overdueCount,
    required this.overdueAmount,
    required this.activeCount,
    required this.activeAmount,
    required this.completedCount,
    required this.completedAmount,
  });

  factory ClusterSummary.fromJson(Map<String, dynamic> json) {
    return ClusterSummary(
      totalPeople: json['total_people'],
      totalCredits: json['total_credits'],
      totalAmount: (json['total_amount'] as num).toDouble(),
      totalBalance: (json['total_balance'] as num).toDouble(),
      overdueCount: json['overdue_count'],
      overdueAmount: (json['overdue_amount'] as num).toDouble(),
      activeCount: json['active_count'],
      activeAmount: (json['active_amount'] as num).toDouble(),
      completedCount: json['completed_count'],
      completedAmount: (json['completed_amount'] as num).toDouble(),
    );
  }
}

class Person {
  final int personId;
  final String name;
  final String phone;
  final int totalCredits;
  final double totalBalance;
  final String personStatus;
  final List<Credit> credits;

  Person({
    required this.personId,
    required this.name,
    required this.phone,
    required this.totalCredits,
    required this.totalBalance,
    required this.personStatus,
    required this.credits,
  });

  factory Person.fromJson(Map<String, dynamic> json) {
    return Person(
      personId: json['person_id'],
      name: json['name'],
      phone: json['phone'],
      totalCredits: json['total_credits'],
      totalBalance: (json['total_balance'] as num).toDouble(),
      personStatus: json['person_status'],
      credits: List<Credit>.from(
        json['credits'].map((c) => Credit.fromJson(c))
      ),
    );
  }
}

class Credit {
  final int creditId;
  final double amount;
  final double balance;
  final String status;
  final String startDate;
  final String endDate;
  final int overdueDays;
  final String? lastPaymentDate;
  final double? lastPaymentAmount;

  Credit({
    required this.creditId,
    required this.amount,
    required this.balance,
    required this.status,
    required this.startDate,
    required this.endDate,
    required this.overdueDays,
    this.lastPaymentDate,
    this.lastPaymentAmount,
  });

  factory Credit.fromJson(Map<String, dynamic> json) {
    return Credit(
      creditId: json['credit_id'],
      amount: (json['amount'] as num).toDouble(),
      balance: (json['balance'] as num).toDouble(),
      status: json['status'],
      startDate: json['start_date'],
      endDate: json['end_date'],
      overdueDays: json['overdue_days'],
      lastPaymentDate: json['last_payment_date'],
      lastPaymentAmount: json['last_payment_amount'] != null
        ? (json['last_payment_amount'] as num).toDouble()
        : null,
    );
  }
}
```

---

## Testing the Endpoint

### cURL

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://192.168.1.23:9000/api/map/location-clusters"

# With filters
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://192.168.1.23:9000/api/map/location-clusters?status=overdue&cobrador_id=5"
```

### Postman

1. Create new GET request
2. URL: `http://192.168.1.23:9000/api/map/location-clusters`
3. Headers: `Authorization: Bearer {token}`
4. Params:
   - `status=overdue` (optional)
   - `cobrador_id=5` (optional)
5. Send

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2024-10-27 | Initial release |

---

**Last Updated:** 2024-10-27
**Created for:** Flutter Mobile App
**Maintained by:** Development Team
