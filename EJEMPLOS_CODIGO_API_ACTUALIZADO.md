# 💻 Ejemplos de Código: Integración API Actualizada

**Propósito**: Ejemplos listos para copiar/pegar en tu frontend
**Lenguajes**: JavaScript, React, Vue, TypeScript

---

## 🔑 Cambio Principal Resumen

```javascript
// ❌ VIEJO
const items = response.data.payments;
const items = response.data.credits;
const items = response.data.users;

// ✅ NUEVO - TODOS USAN "items"
const items = response.data.items;
```

---

## 📦 Vanilla JavaScript

### Patrón Base (Copiar/Pegar)

```javascript
/**
 * Función genérica para obtener reportes
 * @param {string} reportType - Tipo de reporte (payments, credits, users, etc)
 * @param {object} filters - Filtros a aplicar
 * @returns {Promise<Array>} Array de items del reporte
 */
async function fetchReport(reportType, filters = {}) {
  try {
    // Construir query string
    const params = new URLSearchParams({
      format: 'json',
      ...filters
    });

    // Hacer petición
    const response = await fetch(
      `/api/reports/${reportType}?${params}`,
      {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      }
    );

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    // ✅ IMPORTANTE: Usar data.data.items
    return {
      items: data.data.items,
      summary: data.data.summary,
      generatedAt: data.data.generated_at,
      generatedBy: data.data.generated_by
    };

  } catch (error) {
    console.error('Error fetching report:', error);
    throw error;
  }
}

// Uso:
fetchReport('payments', {
  start_date: '2024-01-01',
  end_date: '2024-10-31'
})
  .then(report => {
    console.log(report.items); // Array de pagos
    console.log(report.summary); // Sumario
  })
  .catch(error => console.error(error));
```

### Ejemplo: Tabla de Pagos

```javascript
async function displayPaymentsTable() {
  try {
    const report = await fetchReport('payments', {
      cobrador_id: 1
    });

    const tableBody = document.querySelector('#payments-table tbody');
    tableBody.innerHTML = '';

    // ✅ Iterar sobre items
    report.items.forEach(payment => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${payment.id}</td>
        <td>${payment.amount}</td>
        <td>${payment.payment_date}</td>
        <td>${payment.credit_id}</td>
      `;
      tableBody.appendChild(row);
    });

    // Mostrar sumario
    document.querySelector('#total-amount').textContent =
      report.summary.total_amount || '0.00';
    document.querySelector('#total-payments').textContent =
      report.summary.total_payments || '0';

  } catch (error) {
    alert('Error cargando reportes: ' + error.message);
  }
}

// Llamar cuando carga la página
document.addEventListener('DOMContentLoaded', displayPaymentsTable);
```

### Ejemplo: Gráfico con Chart.js

```javascript
async function displayPaymentsChart() {
  try {
    const report = await fetchReport('payments');

    const ctx = document.querySelector('#payments-chart').getContext('2d');

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: report.items.map(p => p.id),
        datasets: [{
          label: 'Montos de Pagos',
          data: report.items.map(p => p.amount),
          backgroundColor: 'rgba(75, 192, 192, 0.6)',
          borderColor: 'rgba(75, 192, 192, 1)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: { beginAtZero: true }
        }
      }
    });

  } catch (error) {
    console.error('Error en gráfico:', error);
  }
}
```

---

## ⚛️ React

### Hook Personalizado (Recomendado)

```javascript
import { useState, useEffect } from 'react';

/**
 * Hook para obtener reportes
 * @param {string} reportType - Tipo de reporte
 * @param {object} filters - Filtros
 * @param {array} dependencies - Dependencies para re-fetch
 */
function useReport(reportType, filters = {}, dependencies = []) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchReport = async () => {
      try {
        setLoading(true);
        const params = new URLSearchParams({
          format: 'json',
          ...filters
        });

        const response = await fetch(
          `/api/reports/${reportType}?${params}`
        );

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const json = await response.json();

        // ✅ Desestructurar correctamente
        setData({
          items: json.data.items,
          summary: json.data.summary,
          generatedAt: json.data.generated_at,
          generatedBy: json.data.generated_by
        });

        setError(null);

      } catch (err) {
        setError(err.message);
        setData(null);
      } finally {
        setLoading(false);
      }
    };

    fetchReport();
  }, dependencies);

  return { data, loading, error };
}

export default useReport;
```

### Componente: Tabla de Reportes

```jsx
import useReport from './useReport';

export function PaymentsTable({ cobradorId }) {
  const { data, loading, error } = useReport('payments', {
    cobrador_id: cobradorId
  }, [cobradorId]);

  if (loading) return <div>Cargando...</div>;
  if (error) return <div className="error">Error: {error}</div>;
  if (!data) return <div>No hay datos</div>;

  return (
    <div>
      <table className="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Monto</th>
            <th>Fecha</th>
            <th>Crédito</th>
          </tr>
        </thead>
        <tbody>
          {data.items.map(payment => (
            <tr key={payment.id}>
              <td>{payment.id}</td>
              <td>${parseFloat(payment.amount).toFixed(2)}</td>
              <td>{payment.payment_date}</td>
              <td>{payment.credit_id}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <div className="summary">
        <p>Total Pagos: <strong>{data.summary.total_payments}</strong></p>
        <p>Monto Total: <strong>${data.summary.total_amount}</strong></p>
        <p>Promedio: <strong>${data.summary.average_payment}</strong></p>
      </div>

      <small className="text-muted">
        Generado por: {data.generatedBy} el {new Date(data.generatedAt).toLocaleString()}
      </small>
    </div>
  );
}
```

### Componente: Dashboard con Múltiples Reportes

```jsx
import useReport from './useReport';
import { PaymentsTable } from './PaymentsTable';

export function ReportsDashboard() {
  const [activeTab, setActiveTab] = useState('payments');
  const [filters, setFilters] = useState({});

  const payments = useReport('payments', filters, [filters]);
  const credits = useReport('credits', filters, [filters]);
  const overdue = useReport('overdue', filters, [filters]);

  const renderContent = () => {
    switch (activeTab) {
      case 'payments':
        if (payments.loading) return <div>Cargando pagos...</div>;
        if (payments.error) return <div>Error: {payments.error}</div>;
        return (
          <div>
            <h3>Reporte de Pagos</h3>
            <p>Total: ${payments.data?.summary?.total_amount || 0}</p>
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Monto</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody>
                {payments.data?.items?.map(item => (
                  <tr key={item.id}>
                    <td>{item.id}</td>
                    <td>{item.amount}</td>
                    <td>{item.payment_date}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        );

      case 'credits':
        if (credits.loading) return <div>Cargando créditos...</div>;
        if (credits.error) return <div>Error: {credits.error}</div>;
        return (
          <div>
            <h3>Reporte de Créditos</h3>
            <p>Total: {credits.data?.summary?.total_credits || 0}</p>
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Cliente</th>
                  <th>Monto</th>
                </tr>
              </thead>
              <tbody>
                {credits.data?.items?.map(item => (
                  <tr key={item.id}>
                    <td>{item.id}</td>
                    <td>{item.client?.name || 'N/A'}</td>
                    <td>{item.amount}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        );

      case 'overdue':
        if (overdue.loading) return <div>Cargando mora...</div>;
        if (overdue.error) return <div>Error: {overdue.error}</div>;
        return (
          <div>
            <h3>Reporte de Mora</h3>
            <p className="alert alert-warning">
              Créditos en mora: {overdue.data?.summary?.total_overdue_credits || 0}
            </p>
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Cliente</th>
                  <th>Días Vencidos</th>
                  <th>Monto</th>
                </tr>
              </thead>
              <tbody>
                {overdue.data?.items?.map(item => (
                  <tr key={item.id}>
                    <td>{item.id}</td>
                    <td>{item.client?.name || 'N/A'}</td>
                    <td>{item.days_overdue}</td>
                    <td>{item.overdue_amount}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        );

      default:
        return null;
    }
  };

  return (
    <div>
      <div className="tabs">
        <button
          onClick={() => setActiveTab('payments')}
          className={activeTab === 'payments' ? 'active' : ''}
        >
          Pagos
        </button>
        <button
          onClick={() => setActiveTab('credits')}
          className={activeTab === 'credits' ? 'active' : ''}
        >
          Créditos
        </button>
        <button
          onClick={() => setActiveTab('overdue')}
          className={activeTab === 'overdue' ? 'active' : ''}
        >
          Mora
        </button>
      </div>

      <div className="content">
        {renderContent()}
      </div>
    </div>
  );
}
```

---

## 🖖 Vue 3

### Composable (Recomendado)

```javascript
// composables/useReport.js
import { ref, watch } from 'vue';

export function useReport(reportType, filters = {}, immediate = true) {
  const data = ref(null);
  const loading = ref(false);
  const error = ref(null);

  const fetchReport = async () => {
    try {
      loading.value = true;
      const params = new URLSearchParams({
        format: 'json',
        ...filters
      });

      const response = await fetch(
        `/api/reports/${reportType}?${params}`
      );

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const json = await response.json();

      // ✅ Estructura actualizada
      data.value = {
        items: json.data.items,
        summary: json.data.summary,
        generatedAt: json.data.generated_at,
        generatedBy: json.data.generated_by
      };
      error.value = null;

    } catch (err) {
      error.value = err.message;
      data.value = null;
    } finally {
      loading.value = false;
    }
  };

  if (immediate) {
    fetchReport();
  }

  watch(() => filters, () => {
    fetchReport();
  }, { deep: true });

  return { data, loading, error, fetchReport };
}
```

### Componente Vue

```vue
<template>
  <div class="reports-container">
    <div v-if="loading" class="alert alert-info">Cargando...</div>
    <div v-else-if="error" class="alert alert-danger">Error: {{ error }}</div>
    <div v-else-if="data">
      <h3>Reporte de Pagos</h3>

      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Monto</th>
            <th>Fecha</th>
            <th>Crédito ID</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="payment in data.items" :key="payment.id">
            <td>{{ payment.id }}</td>
            <td>${{ parseFloat(payment.amount).toFixed(2) }}</td>
            <td>{{ payment.payment_date }}</td>
            <td>{{ payment.credit_id }}</td>
          </tr>
        </tbody>
      </table>

      <div class="summary">
        <h4>Sumario</h4>
        <ul>
          <li>Total Pagos: {{ data.summary.total_payments }}</li>
          <li>Monto Total: ${{ data.summary.total_amount }}</li>
          <li>Promedio: ${{ data.summary.average_payment }}</li>
        </ul>
      </div>

      <small>Generado por: {{ data.generatedBy }}</small>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue';
import { useReport } from '@/composables/useReport';

const filters = reactive({
  cobrador_id: null,
  start_date: null,
  end_date: null
});

const { data, loading, error } = useReport('payments', filters);
</script>

<style scoped>
.reports-container {
  padding: 20px;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  border: 1px solid #ddd;
  padding: 8px;
  text-align: left;
}

th {
  background-color: #f4f4f4;
}

.summary {
  margin-top: 20px;
  padding: 10px;
  background-color: #f9f9f9;
  border-radius: 4px;
}
</style>
```

---

## 🔷 TypeScript

### Tipos Definidos

```typescript
// types/api.ts

export interface ApiResponse<T> {
  success: boolean;
  data: {
    items: T[];
    summary: Record<string, any>;
    generated_at: string;
    generated_by: string;
  };
  message: string;
}

export interface Payment {
  id: number;
  amount: string;
  credit_id: number;
  payment_date: string;
  created_at: string;
}

export interface Credit {
  id: number;
  reference: string;
  client_id: number;
  cobrador_id: number;
  amount: string;
  status: string;
  created_at: string;
}

export interface Overdue extends Credit {
  days_overdue: number;
  overdue_amount: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  role: string;
}

export interface ReportSummary {
  [key: string]: any;
}

export interface ReportData<T> {
  items: T[];
  summary: ReportSummary;
  generatedAt: string;
  generatedBy: string;
}
```

### Servicio API Tipado

```typescript
// services/reportService.ts

import {
  ApiResponse,
  Payment,
  Credit,
  Overdue,
  User,
  ReportData
} from '@/types/api';

class ReportService {
  private baseUrl = '/api/reports';

  async fetchPayments(filters?: Record<string, any>): Promise<ReportData<Payment>> {
    return this.fetch<Payment>('payments', filters);
  }

  async fetchCredits(filters?: Record<string, any>): Promise<ReportData<Credit>> {
    return this.fetch<Credit>('credits', filters);
  }

  async fetchOverdue(filters?: Record<string, any>): Promise<ReportData<Overdue>> {
    return this.fetch<Overdue>('overdue', filters);
  }

  async fetchUsers(filters?: Record<string, any>): Promise<ReportData<User>> {
    return this.fetch<User>('users', filters);
  }

  private async fetch<T>(
    reportType: string,
    filters?: Record<string, any>
  ): Promise<ReportData<T>> {
    const params = new URLSearchParams({
      format: 'json',
      ...filters
    });

    const response = await fetch(
      `${this.baseUrl}/${reportType}?${params.toString()}`
    );

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const apiResponse: ApiResponse<T> = await response.json();

    // ✅ Desestructurar correctamente
    return {
      items: apiResponse.data.items,
      summary: apiResponse.data.summary,
      generatedAt: apiResponse.data.generated_at,
      generatedBy: apiResponse.data.generated_by
    };
  }
}

export default new ReportService();
```

### Uso en Componente React Tipado

```typescript
import { useState, useEffect } from 'react';
import reportService from '@/services/reportService';
import { Payment, ReportData } from '@/types/api';

interface PaymentsTableProps {
  cobradorId?: number;
}

export const PaymentsTable: React.FC<PaymentsTableProps> = ({ cobradorId }) => {
  const [report, setReport] = useState<ReportData<Payment> | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadPayments = async () => {
      try {
        setLoading(true);
        const data = await reportService.fetchPayments({
          cobrador_id: cobradorId
        });
        setReport(data);
        setError(null);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Unknown error');
        setReport(null);
      } finally {
        setLoading(false);
      }
    };

    loadPayments();
  }, [cobradorId]);

  if (loading) return <div>Cargando...</div>;
  if (error) return <div className="error">Error: {error}</div>;
  if (!report) return <div>No hay datos</div>;

  return (
    <>
      <table className="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Monto</th>
            <th>Fecha</th>
            <th>Crédito</th>
          </tr>
        </thead>
        <tbody>
          {report.items.map((payment: Payment) => (
            <tr key={payment.id}>
              <td>{payment.id}</td>
              <td>${parseFloat(payment.amount).toFixed(2)}</td>
              <td>{payment.payment_date}</td>
              <td>{payment.credit_id}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <div className="summary">
        <h4>Sumario</h4>
        <p>Total Pagos: {report.summary.total_payments}</p>
        <p>Monto Total: ${report.summary.total_amount}</p>
        <p>Promedio: ${report.summary.average_payment}</p>
      </div>
    </>
  );
};
```

---

## 🎯 Checklist de Migración

- [ ] Cambiar todas las referencias de `data.payments` a `data.items`
- [ ] Cambiar todas las referencias de `data.credits` a `data.items`
- [ ] Cambiar todas las referencias de `data.users` a `data.items`
- [ ] Cambiar todas las referencias de `data.balances` a `data.items`
- [ ] Cambiar todas las referencias de `data.performance` a `data.items`
- [ ] Cambiar todas las referencias de `data.activities` a `data.items`
- [ ] Cambiar todas las referencias de `data.commissions` a `data.items`
- [ ] Cambiar todas las referencias de `data.waiting_list` a `data.items`
- [ ] Cambiar todas las referencias de `data.forecast` a `data.items`
- [ ] Verificar que se accede a `summary` correctamente
- [ ] Testar con datos reales
- [ ] Verificar en consola de navegador que response.data.items existe

---

## 🧪 Testing en Consola

```javascript
// Copiar en consola del navegador:

// Test 1: Payments
fetch('/api/reports/payments?format=json')
  .then(r => r.json())
  .then(data => {
    console.log('✅ Pagos:', data.data.items);
    console.log('📊 Sumario:', data.data.summary);
  });

// Test 2: Credits
fetch('/api/reports/credits?format=json')
  .then(r => r.json())
  .then(data => {
    console.log('✅ Créditos:', data.data.items);
    console.log('📊 Sumario:', data.data.summary);
  });

// Test 3: Overdue
fetch('/api/reports/overdue?format=json')
  .then(r => r.json())
  .then(data => {
    console.log('✅ Mora:', data.data.items);
    console.log('⚠️ Total en mora:', data.data.summary.total_overdue_amount);
  });
```

---

**Documentación**: 2024-10-26
**Última actualización**: Commit 70d7d69
**Versión API**: 2.0

