# 📊 ENDPOINTS COMPLETOS PARA REPORTES - COBRADOR SYSTEM

## 🎯 **ENDPOINTS IMPLEMENTADOS Y DISPONIBLES**

### **1. ENDPOINTS BÁSICOS DE REPORTES**

#### **Obtener Tipos de Reportes Disponibles**

```http
GET /api/reports/types
```

**Respuesta:**

```json
{
  "success": true,
  "data": {
    "payments": {
      "name": "Reporte de Pagos",
      "description": "Historial de pagos con filtros por fecha y cobrador",
      "filters": ["start_date", "end_date", "cobrador_id"],
      "formats": ["pdf", "html", "json", "excel"]
    },
    "credits": {
      "name": "Reporte de Créditos",
      "description": "Lista de créditos con estado y asignaciones",
      "filters": ["status", "cobrador_id", "client_id"],
      "formats": ["pdf", "html", "json", "excel"]
    },
    "users": {
      "name": "Reporte de Usuarios",
      "description": "Lista de usuarios con roles y categorías",
      "filters": ["role", "client_category"],
      "formats": ["pdf", "html", "json", "excel"]
    },
    "balances": {
      "name": "Reporte de Balances",
      "description": "Balances de efectivo por cobrador",
      "filters": ["start_date", "end_date", "cobrador_id"],
      "formats": ["pdf", "html", "json", "excel"]
    }
  }
}
```

---

## 📈 **1. REPORTES DE PAGOS**

### **1.1 Reporte General de Pagos**

```http
GET /api/reports/payments
```

**Parámetros de consulta:**

- `start_date` (opcional): Fecha inicio (YYYY-MM-DD)
- `end_date` (opcional): Fecha fin (YYYY-MM-DD)
- `cobrador_id` (opcional): ID del cobrador
- `format` (opcional): `pdf`, `html`, `json`, `excel`

**Respuesta JSON:**

```json
{
  "success": true,
  "data": {
    "payments": [
      {
        "id": 1,
        "payment_date": "2024-01-15",
        "amount": 500.00,
        "payment_type": "efectivo",
        "notes": "Pago semanal",
        "cobrador": {
          "id": 2,
          "name": "Juan Pérez"
        },
        "credit": {
          "id": 10,
          "client": {
            "id": 5,
            "name": "María García"
          }
        }
      }
    ],
    "summary": {
      "total_payments": 25,
      "total_amount": 12500.00,
      "average_payment": 500.00,
      "date_range": {
        "start": "2024-01-01",
        "end": "2024-01-31"
      }
    },
    "generated_at": "2024-01-15T10:30:00Z",
    "generated_by": "Admin User"
  }
}
```

### **1.2 Estadísticas de Pagos por Cobrador**

```http
GET /api/dashboard/stats-by-cobrador?cobrador_id={id}
```

**Respuesta:**

```json
{
  "success": true,
  "data": {
    "cobrador": {
      "id": 2,
      "name": "Juan Pérez"
    },
    "stats": {
      "total_clients": 15,
      "total_credits": 12,
      "total_payments": 45,
      "total_collected": 22500.00,
      "pending_payments": 3,
      "overdue_payments": 1,
      "monthly_goal": 20000.00,
      "monthly_progress": 112.5
    }
  }
}
```

### **1.3 Pagos Recientes (Hoy)**

```http
GET /api/reports/payments?start_date={today}&end_date={today}&format=json
```

### **1.4 Resumen Diario de Pagos**

```http
GET /api/reports/payments/daily-summary?date={YYYY-MM-DD}
```

*(Endpoint a implementar)*

### **1.5 Pagos por Crédito Específico**

```http
GET /api/reports/payments?credit_id={credit_id}&format=json
```

*(Endpoint a implementar)*

---

## 💰 **2. REPORTES DE CRÉDITOS**

### **2.1 Reporte General de Créditos**

```http
GET /api/reports/credits
```

**Parámetros:**

- `status` (opcional): `active`, `completed`, `pending_approval`, `waiting_delivery`
- `cobrador_id` (opcional): ID del cobrador
- `client_id` (opcional): ID del cliente
- `format` (opcional): `pdf`, `html`, `json`, `excel`

**Respuesta JSON:**

```json
{
  "success": true,
  "data": {
    "credits": [
      {
        "id": 10,
        "client": {
          "id": 5,
          "name": "María García"
        },
        "cobrador": {
          "id": 2,
          "name": "Juan Pérez"
        },
        "total_amount": 5000.00,
        "paid_amount": 2500.00,
        "balance": 2500.00,
        "status": "active",
        "created_at": "2024-01-01",
        "due_date": "2024-02-01"
      }
    ],
    "summary": {
      "total_credits": 25,
      "total_amount": 125000.00,
      "active_credits": 20,
      "completed_credits": 5,
      "total_balance": 75000.00
    }
  }
}
```

### **2.2 Créditos que Requieren Atención**

```http
GET /api/reports/credits/attention-needed
```

*(Endpoint a implementar)*

**Respuesta:**

```json
{
  "success": true,
  "data": {
    "overdue_credits": [...],
    "high_risk_credits": [...],
    "pending_approvals": [...],
    "waiting_delivery": [...]
  }
}
```

### **2.3 Créditos por Cliente**

```http
GET /api/reports/credits?client_id={client_id}&format=json
```

### **2.4 Estadísticas de Créditos por Manager**

```http
GET /api/dashboard/manager-stats?manager_id={manager_id}
```

*(Endpoint a implementar)*

---

## 👥 **3. REPORTES DE USUARIOS Y CLIENTES**

### **3.1 Reporte General de Usuarios**

```http
GET /api/reports/users
```

**Parámetros:**

- `role` (opcional): `cobrador`, `manager`, `client`, `admin`
- `client_category` (opcional): `A`, `B`, `C`
- `format` (opcional): `pdf`, `html`, `json`, `excel`

### **3.2 Clientes por Cobrador**

```http
GET /api/reports/users?role=client&cobrador_id={cobrador_id}&format=json
```

### **3.3 Estadísticas de Categorías de Clientes**

```http
GET /api/reports/users/category-stats
```

*(Endpoint a implementar)*

**Respuesta:**

```json
{
  "success": true,
  "data": {
    "categories": {
      "A": { "count": 15, "percentage": 25.0 },
      "B": { "count": 30, "percentage": 50.0 },
      "C": { "count": 15, "percentage": 25.0 }
    },
    "total_clients": 60
  }
}
```

### **3.4 Usuarios por Roles**

```http
GET /api/reports/users?role={role}&format=json
```

---

## 💵 **4. REPORTES FINANCIEROS**

### **4.1 Reporte de Balances de Efectivo**

```http
GET /api/reports/balances
```

**Parámetros:**

- `start_date` (opcional): Fecha inicio
- `end_date` (opcional): Fecha fin
- `cobrador_id` (opcional): ID del cobrador
- `format` (opcional): `pdf`, `html`, `json`, `excel`

### **4.2 Conciliación de Efectivo**

```http
GET /api/reports/balances/reconciliation?date={YYYY-MM-DD}
```

*(Endpoint a implementar)*

### **4.3 Resumen Financiero Consolidado**

```http
GET /api/dashboard/financial-summary
```

*(Endpoint a implementar)*

---

## 📊 **5. REPORTES OPERATIVOS**

### **5.1 Estadísticas del Dashboard**

```http
GET /api/dashboard/stats
```

**Respuesta:**

```json
{
  "success": true,
  "data": {
    "total_clients": 150,
    "total_cobradores": 12,
    "total_credits": 89,
    "total_payments": 450,
    "overdue_payments": 5,
    "pending_payments": 15,
    "total_balance": 125000.00,
    "today_collections": 2500.00
  }
}
```

### **5.2 Estadísticas del Mapa**

```http
GET /api/dashboard/map-stats
```

*(Endpoint a implementar)*

### **5.3 Lista de Espera de Créditos**

```http
GET /api/reports/credits/waiting-list
```

*(Endpoint a implementar)*

---

## 🔧 **ENDPOINTS ADICIONALES RECOMENDADOS**

### **Filtros Avanzados**

```http
GET /api/reports/payments/advanced-filters
GET /api/reports/credits/advanced-filters
GET /api/reports/users/advanced-filters
```

### **Exportación Masiva**

```http
POST /api/reports/bulk-export
Content-Type: application/json

{
  "reports": [
    {
      "type": "payments",
      "filters": { "start_date": "2024-01-01", "end_date": "2024-01-31" },
      "format": "excel"
    },
    {
      "type": "credits",
      "filters": { "status": "active" },
      "format": "pdf"
    }
  ]
}
```

### **Reportes Programados**

```http
GET /api/reports/scheduled
POST /api/reports/schedule
PUT /api/reports/schedule/{id}
DELETE /api/reports/schedule/{id}
```

---

## 📱 **INTEGRACIÓN CON FRONTEND**

### **Ejemplo de Componente React para Reportes**

```javascript
// components/Reports.jsx
import React, { useState, useEffect } from 'react';
import axios from 'axios';

const Reports = () => {
  const [reportTypes, setReportTypes] = useState({});
  const [selectedReport, setSelectedReport] = useState('');
  const [filters, setFilters] = useState({});
  const [format, setFormat] = useState('json');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchReportTypes();
  }, []);

  const fetchReportTypes = async () => {
    try {
      const response = await axios.get('/api/reports/types');
      setReportTypes(response.data.data);
    } catch (error) {
      console.error('Error fetching report types:', error);
    }
  };

  const generateReport = async () => {
    setLoading(true);
    try {
      const params = {
        ...filters,
        format: format
      };

      const response = await axios.get(`/api/reports/${selectedReport}`, {
        params,
        responseType: format === 'json' ? 'json' : 'blob'
      });

      if (format === 'json') {
        // Mostrar datos en pantalla
        console.log('Report data:', response.data);
      } else {
        // Descargar archivo
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `reporte-${selectedReport}.${format}`);
        document.body.appendChild(link);
        link.click();
        link.remove();
      }
    } catch (error) {
      console.error('Error generating report:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="reports-container">
      <h2>Generador de Reportes</h2>

      <div className="report-selector">
        <label>Tipo de Reporte:</label>
        <select
          value={selectedReport}
          onChange={(e) => setSelectedReport(e.target.value)}
        >
          <option value="">Seleccionar reporte...</option>
          {Object.entries(reportTypes).map(([key, report]) => (
            <option key={key} value={key}>
              {report.name}
            </option>
          ))}
        </select>
      </div>

      {selectedReport && (
        <div className="filters-section">
          <h3>Filtros Disponibles</h3>
          {reportTypes[selectedReport]?.filters.map(filter => (
            <div key={filter} className="filter-input">
              <label>{filter.replace('_', ' ').toUpperCase()}:</label>
              <input
                type={filter.includes('date') ? 'date' : 'text'}
                value={filters[filter] || ''}
                onChange={(e) => setFilters({
                  ...filters,
                  [filter]: e.target.value
                })}
              />
            </div>
          ))}
        </div>
      )}

      <div className="format-selector">
        <label>Formato:</label>
        <select
          value={format}
          onChange={(e) => setFormat(e.target.value)}
        >
          {reportTypes[selectedReport]?.formats.map(fmt => (
            <option key={fmt} value={fmt}>
              {fmt.toUpperCase()}
            </option>
          ))}
        </select>
      </div>

      <button
        onClick={generateReport}
        disabled={!selectedReport || loading}
      >
        {loading ? 'Generando...' : 'Generar Reporte'}
      </button>
    </div>
  );
};

export default Reports;
```

---

## 🔄 **IMPLEMENTACIÓN EN FLUTTER**

### **Servicio de Reportes para Flutter**

```dart
// services/reports_service.dart
class ReportsService {
  final Dio _dio = Dio();

  Future<Map<String, dynamic>> getReportTypes() async {
    final response = await _dio.get('/api/reports/types');
    return response.data;
  }

  Future<Map<String, dynamic>> generateReport(
    String reportType,
    Map<String, dynamic> filters,
    String format
  ) async {
    final params = {...filters, 'format': format};
    final response = await _dio.get(
      '/api/reports/$reportType',
      queryParameters: params,
    );
    return response.data;
  }

  Future<void> downloadReport(
    String reportType,
    Map<String, dynamic> filters,
    String format
  ) async {
    final params = {...filters, 'format': format};
    final response = await _dio.get(
      '/api/reports/$reportType',
      queryParameters: params,
      options: Options(responseType: ResponseType.bytes),
    );

    // Guardar archivo en dispositivo
    final directory = await getApplicationDocumentsDirectory();
    final file = File('${directory.path}/reporte-$reportType.$format');
    await file.writeAsBytes(response.data);
  }
}
```

---

## 📋 **RESUMEN DE ENDPOINTS PRIORITARIOS**

### **✅ IMPLEMENTADOS**

- `/api/reports/types` - Tipos de reportes
- `/api/reports/payments` - Reporte de pagos
- `/api/reports/credits` - Reporte de créditos
- `/api/reports/users` - Reporte de usuarios
- `/api/reports/balances` - Reporte de balances
- `/api/dashboard/stats` - Estadísticas generales
- `/api/dashboard/stats-by-cobrador` - Estadísticas por cobrador

### **🔄 PENDIENTES DE IMPLEMENTAR**

- `/api/reports/payments/daily-summary` - Resumen diario
- `/api/reports/credits/attention-needed` - Créditos que requieren atención
- `/api/dashboard/manager-stats` - Estadísticas por manager
- `/api/reports/users/category-stats` - Estadísticas de categorías
- `/api/reports/balances/reconciliation` - Conciliación de efectivo
- `/api/dashboard/financial-summary` - Resumen financiero
- `/api/dashboard/map-stats` - Estadísticas del mapa
- `/api/reports/credits/waiting-list` - Lista de espera

Esta documentación proporciona una guía completa para integrar todos los reportes en tu frontend, tanto web como móvil.

---

## 👤 **CLASIFICACIÓN DE REPORTES POR ROL DE USUARIO**

### **🎯 REPORTES PARA COBRADOR**

Los cobradores necesitan reportes operativos enfocados en su trabajo diario, clientes asignados y rendimiento personal.

#### **Reportes Principales para Cobrador:**

1. **📊 Mis Estadísticas Personales**

   ```http
   GET /api/dashboard/stats-by-cobrador?cobrador_id={current_user_id}
   ```

   - Clientes asignados
   - Créditos activos
   - Pagos realizados hoy
   - Meta mensual y progreso
   - Pagos pendientes

2. **💰 Mis Pagos del Día**

   ```http
   GET /api/reports/payments?start_date={today}&end_date={today}&cobrador_id={current_user_id}&format=json
   ```

   - Lista de pagos realizados hoy
   - Total recolectado
   - Método de pago utilizado

3. **📋 Mis Créditos Activos**

   ```http
   GET /api/reports/credits?status=active&cobrador_id={current_user_id}&format=json
   ```

   - Créditos asignados
   - Saldos pendientes
   - Fechas de vencimiento
   - Información de clientes

4. **👥 Mis Clientes**

   ```http
   GET /api/reports/users?role=client&cobrador_id={current_user_id}&format=json
   ```

   - Lista de clientes asignados
   - Categorías de clientes
   - Información de contacto

5. **💵 Mi Balance de Efectivo**

   ```http
   GET /api/reports/balances?cobrador_id={current_user_id}&format=json
   ```

   - Efectivo disponible
   - Movimientos del día
   - Conciliación pendiente

#### **Reportes Adicionales para Cobrador:**

- **Historial de Pagos Personal** (últimos 30 días)
- **Créditos Vencidos** (de sus clientes)
- **Pagos Pendientes** (de sus créditos)

---

### **👔 REPORTES PARA MANAGER**

Los managers necesitan reportes estratégicos para supervisar el rendimiento del equipo, tomar decisiones y gestionar el negocio.

#### **Reportes Principales para Manager:**

1. **📊 Dashboard Ejecutivo**

   ```http
   GET /api/dashboard/stats
   ```

   - Visión general del negocio
   - Total de clientes, cobradores, créditos
   - Cobranza total del día
   - Alertas importantes

2. **📈 Rendimiento por Cobrador**

   ```http
   GET /api/dashboard/stats-by-cobrador?cobrador_id={cobrador_id}
   ```

   - Para cada cobrador asignado
   - Comparación de metas vs. realidad
   - Eficiencia de cobranza
   - Clientes atendidos

3. **💰 Reporte Consolidado de Pagos**

   ```http
   GET /api/reports/payments?start_date={period}&end_date={period}&format=excel
   ```

   - Todos los pagos del período
   - Análisis por cobrador
   - Tendencias de cobranza
   - Exportación para análisis

4. **📋 Estado General de Créditos**

   ```http
   GET /api/reports/credits?format=json
   ```

   - Créditos activos, completados, pendientes
   - Saldos totales por cobrar
   - Créditos que requieren atención
   - Distribución por estatus

5. **👥 Gestión de Usuarios**

   ```http
   GET /api/reports/users?format=json
   ```

   - Todos los usuarios del sistema
   - Distribución por roles
   - Estadísticas de categorías de clientes
   - Usuarios activos/inactivos

6. **💵 Balances Financieros**

   ```http
   GET /api/reports/balances?format=json
   ```

   - Balances de todos los cobradores
   - Conciliación general
   - Resumen financiero consolidado

#### **Reportes Estratégicos para Manager:**

- **Análisis de Tendencias** (comparativo mensual/anual)
- **Eficiencia de Cobranza** (porcentaje de recuperación)
- **Cartera de Créditos** (riesgo y rentabilidad)
- **Mapa de Cobertura** (ubicación de cobradores y clientes)
- **Reportes de Productividad** (metas vs. logros)

---

### **🔐 CONTROL DE ACCESO POR ROL**

#### **Permisos de Cobrador:**

```json
{
  "reports": {
    "allowed": [
      "personal_stats",
      "daily_payments",
      "my_credits",
      "my_clients",
      "my_balance"
    ],
    "filters": {
      "cobrador_id": "current_user_only",
      "client_id": "assigned_clients_only"
    }
  }
}
```

#### **Permisos de Manager:**

```json
{
  "reports": {
    "allowed": [
      "all_reports",
      "bulk_export",
      "advanced_filters",
      "scheduled_reports"
    ],
    "filters": {
      "cobrador_id": "all_assigned_cobradores",
      "client_id": "all_clients",
      "date_range": "unlimited"
    }
  }
}
```

---

### **📱 INTERFAZ RECOMENDADA POR ROL**

#### **App Móvil para Cobrador:**

- **Pantalla Principal:** Estadísticas del día + metas
- **Menú Reportes:** Mis pagos, Mis clientes, Mis créditos
- **Notificaciones:** Recordatorios de cobranza, pagos vencidos
- **Offline:** Sincronización de datos básicos

#### **Panel Web para Manager:**

- **Dashboard:** KPIs principales + gráficos
- **Reportes:** Filtros avanzados, exportación múltiple
- **Gestión:** Asignación de cobradores, configuración de metas
- **Análisis:** Tendencias históricas, comparativos

---

### **⚡ OPTIMIZACIONES POR ROL**

#### **Para Cobrador (Movilidad):**

- Datos optimizados para conexiones lentas
- Caché local de información crítica
- Sincronización automática al conectarse
- Interfaz simple y rápida

#### **Para Manager (Análisis):**

- Datos en tiempo real cuando posible
- Filtros avanzados y personalizables
- Exportación a múltiples formatos
- Integración con herramientas de BI

Esta clasificación asegura que cada usuario tenga acceso únicamente a la información relevante para su rol, optimizando tanto la experiencia como la seguridad del sistema.
