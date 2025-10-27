# 📍 Location Clusters - Resumen de Implementación

## ¿Qué se implementó?

Nuevo endpoint que agrupa clientes por ubicación geográfica y proporciona **información DETALLADA** de:

✅ **Información del Cliente**
- Nombre, teléfono, email, dirección
- Categoría (VIP/Normal/Malo)

✅ **Información de Créditos**
- Monto original vs. saldo pendiente
- Porcentaje pagado
- Estado del crédito
- Fechas de inicio/vencimiento
- Días vencidos o hasta el vencimiento

✅ **Información de Pagos** (Lo más importante)
- Total de pagos realizados
- Pagos confirmados, pendientes, vencidos
- Monto pagado vs. pendiente vs. vencido
- Último pago (fecha, monto, método)
- **TODOS los pagos recientes** (últimos 5 por crédito)
- Próximo pago esperado
- Método de pago (cash, transfer, qr, check)

---

## Comparativa: Antes vs. Después

### ANTES
```json
{
  "id": 1,
  "name": "Juan García",
  "location": [19.4326, -99.1332],
  "total_balance": 1400,
  "active_credits_count": 2
}
```
❌ No sabe si ya pagó
❌ No ve historial de pagos
❌ No sabe cuánto pagó

---

### DESPUÉS (Nuevo Endpoint)
```json
{
  "cluster_id": "19.4326,-99.1332",
  "location": {
    "latitude": 19.4326,
    "longitude": -99.1332,
    "address": "Calle Principal 123"
  },
  "people": [
    {
      "person_id": 1,
      "name": "Juan García",
      "phone": "555-1234",
      "email": "juan@example.com",
      "address": "Calle Principal 123",
      "client_category": "A",

      // ✅ Información de pagos a nivel persona
      "total_amount": 1700,
      "total_paid": 300,
      "total_balance": 1400,
      "payment_stats": {
        "total_payments": 5,
        "paid_payments": 3,
        "pending_payments": 1,
        "overdue_payments": 1,
        "total_paid_amount": 300,
        "total_pending_amount": 100,
        "total_overdue_amount": 200,
        "last_payment": {
          "date": "2024-10-20",
          "amount": 100,
          "method": "cash",
          "status": "paid"
        }
      },

      "credits": [
        {
          "credit_id": 101,
          "amount": 800,
          "balance": 500,
          "paid_amount": 300,
          "payment_percentage": 37.5,
          "status": "active",
          "start_date": "2024-01-15",
          "end_date": "2024-12-31",
          "overdue_days": 5,
          "days_until_due": 0,

          // ✅ Próximo pago
          "next_payment_due": {
            "date": "2024-11-20",
            "amount": 66.67,
            "installment": 2
          },

          // ✅ Último pago
          "last_payment": {
            "date": "2024-10-20",
            "amount": 100,
            "method": "cash",
            "status": "paid"
          },

          // ✅ Estadísticas de pagos del crédito
          "payment_stats": {
            "total_payments": 3,
            "paid_payments": 3,
            "pending_payments": 0,
            "overdue_payments": 0
          },

          // ✅ TODOS los pagos recientes (últimos 5)
          "recent_payments": [
            {
              "payment_id": 501,
              "amount": 100,
              "date": "2024-10-20",
              "method": "cash",
              "status": "paid",
              "installment_num": 1
            },
            {
              "payment_id": 500,
              "amount": 100,
              "date": "2024-09-15",
              "method": "transfer",
              "status": "paid",
              "installment_num": 0
            }
          ]
        }
      ]
    }
  ]
}
```

✅ **VE SI YA PAGÓ** → En `payment_stats.last_payment`
✅ **HISTORIAL COMPLETO** → En `recent_payments`
✅ **CUÁNTO PAGÓ** → `paid_amount` y `payment_percentage`
✅ **PRÓXIMO PAGO** → En `next_payment_due`

---

## Archivos Creados/Modificados

### 1. **DTOs** (Modelos de datos)
```
✅ app/DTOs/LocationClusterDTO.php (NUEVO)
```

### 2. **Servicios** (Lógica de negocio)
```
✅ app/Services/LocationClusteringService.php (NUEVO)
   - Agrupa clientes por ubicación
   - Calcula totales y estadísticas
   - Prepara datos para JSON
```

### 3. **Controllers** (Endpoints API)
```
✅ app/Http/Controllers/Api/MapController.php (MODIFICADO)
   - Nuevo método: getLocationClusters()
   - Validaciones y filtros
```

### 4. **Rutas** (URLs)
```
✅ routes/api.php (MODIFICADO)
   - Nuevo endpoint: GET /api/map/location-clusters
```

### 5. **Documentación** (Para Flutter)
```
✅ API_LOCATION_CLUSTERS_REFERENCE.md (NUEVO)
   - Documentación completa
   - Ejemplos de respuesta
   - Modelos Dart
   - Tips de performance
```

---

## Cómo Usar en Flutter

### 1. Hacer la request
```dart
final response = await http.get(
  Uri.parse('http://192.168.1.23:9000/api/map/location-clusters?status=overdue'),
  headers: {'Authorization': 'Bearer $token'},
);
```

### 2. Parsear respuesta
```dart
final json = jsonDecode(response.body);
final clusters = List<LocationCluster>.from(
  json['data'].map((c) => LocationCluster.fromJson(c))
);
```

### 3. Mostrar en mapa
```dart
// Un marcador por cluster
clusters.forEach((cluster) {
  _markers.add(Marker(
    position: LatLng(
      cluster.location.latitude,
      cluster.location.longitude
    ),
    title: '${cluster.clusterSummary.totalCredits} créditos',
    onTap: () => _showClusterDetails(cluster),
  ));
});
```

### 4. Ver detalles al tap
```dart
void _showClusterDetails(LocationCluster cluster) {
  // Bottom sheet con:
  // - Dirección
  // - Listado de personas
  // - Para cada persona: sus créditos
  // - Para cada crédito: payments, próximo pago, etc.
}
```

---

## Características Especiales

### 📊 Clustering Inteligente
- Agrupa por ubicación exacta (6 decimales precisión)
- Evita múltiples marcadores superpuestos
- Una ubicación = una tarjeta = múltiples personas

### 🎯 Información Jerárquica
```
CLUSTER (ubicación geográfica)
├─ PERSONA 1 (Juan)
│  ├─ CRÉDITO 101 (Bs 800)
│  │  └─ PAGOS (5 pagos registrados)
│  └─ CRÉDITO 102 (Bs 900)
│     └─ PAGOS (3 pagos registrados)
└─ PERSONA 2 (María)
   └─ CRÉDITO 103 (Bs 700)
      └─ PAGOS (0 pagos registrados)
```

### 🔴 Estados Inteligentes
- `overdue`: Tiene pagos vencidos
- `pending`: Tiene pagos pendientes (pero sin vencer)
- `paid`: Todo al día

### 📈 Cálculos Automáticos
- Porcentaje de pago por crédito
- Días vencidos / días hasta vencimiento
- Próximo pago esperado
- Totales por persona y por cluster

### 🔐 Permisos Respetados
- **Admin**: Ve todos los clusters
- **Manager**: Ve clusters de sus cobradores
- **Cobrador**: Ve solo sus clusters

### 🎨 Información Detallada
- Categoría del cliente (VIP/Normal/Malo)
- Contacto (teléfono, email, dirección)
- Métodos de pago (cash, transfer, qr, check)
- Historial de pagos completo

---

## Performance

✅ Usa `eager loading` para evitar N+1 queries
✅ Carga solo ubicaciones con clientes activos
✅ Agrupa por coordenadas en memoria
✅ Respuesta JSON optimizada

---

## Ejemplos de Request/Response

### Request Todos los Clusters
```bash
curl -H "Authorization: Bearer TOKEN" \
  http://192.168.1.23:9000/api/map/location-clusters
```

### Request Solo Vencidos
```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://192.168.1.23:9000/api/map/location-clusters?status=overdue"
```

### Request de un Cobrador Específico
```bash
curl -H "Authorization: Bearer TOKEN" \
  "http://192.168.1.23:9000/api/map/location-clusters?cobrador_id=5"
```

---

## Flujo de Datos

```
1. Flutter llama a: /api/map/location-clusters?status=overdue
                      ↓
2. MapController.getLocationClusters()
                      ↓
3. LocationClusteringService.generateLocationClusters()
   - Carga clientes con pagos
   - Agrupa por coordenadas
   - Calcula estadísticas
   - Organiza datos jerárquicamente
                      ↓
4. LocationClusterDTO (serializa a JSON)
                      ↓
5. Response JSON con:
   - Clusters con ubicaciones
   - Personas en cada ubicación
   - Créditos de cada persona
   - Pagos de cada crédito
                      ↓
6. Flutter parsea y muestra en mapa
   - Click en marcador → expande detalles
   - Ve estado de pagos
   - Ve historial completo
```

---

## Próximas Mejoras (Opcionales)

- [ ] Paginación (si hay muchos clusters)
- [ ] Caching en cliente (para offline)
- [ ] Exportar clusters a CSV/PDF
- [ ] Búsqueda por dirección/cliente
- [ ] Filtro por monto mínimo/máximo
- [ ] Ordenar por prioridad (vencidos primero)

---

## Documentación Disponible

📚 **API_LOCATION_CLUSTERS_REFERENCE.md**
- Especificación técnica completa
- Ejemplos de JSON
- Modelos Dart
- Código Flutter de ejemplo

---

## Validación

✅ Código validado (sin errores de sintaxis)
✅ Estructura JSON clara para Flutter
✅ Permisos implementados
✅ Relaciones cargadas correctamente
✅ Documentación completa

**Status:** 🟢 **LISTO PARA USAR**

---

**Última actualización:** 2024-10-27
