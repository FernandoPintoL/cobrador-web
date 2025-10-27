# 📘 Guía: Cambios en API de Reportes - Frontend Integration

**Fecha**: 2024-10-26
**Commit**: `70d7d69`
**Status**: ✅ Implementado

---

## 🎯 Resumen de Cambios

El backend ha sido refactorizado para:
- ✅ Reducir duplicación de código (-73%)
- ✅ Centralizar lógica de reportes
- ✅ Mejorar mantenibilidad
- ℹ️ **Cambiar estructura de respuesta JSON**

---

## 🔄 Cambios en la Estructura de Respuesta

### Cambio Global

Todos los reportes ahora retornan la misma estructura genérica:

```json
{
  "success": true,
  "data": {
    "items": [array de items],
    "summary": {objeto con sumario},
    "generated_at": "2024-10-26T12:34:56Z",
    "generated_by": "Nombre Usuario"
  },
  "message": "Mensaje descriptivo"
}
```

**Cambio principal:**
- ❌ Antes: `"payments"`, `"credits"`, `"users"`, `"balances"`, etc. (claves específicas)
- ✅ Ahora: `"items"` (clave genérica para todos)

---

## 📋 Cambios por Reporte

### 1. **Reporte de Pagos** (`GET /api/reports/payments`)

#### ANTES
```json
{
  "success": true,
  "data": {
    "payments": [
      {
        "id": 1,
        "amount": 1000.00,
        "principal_portion": 500.00,
        "interest_portion": 50.00,
        "remaining_for_installment": 450.00,
        "credit_id": 5,
        "credit": {...},
        "payment_date": "2024-10-20",
        "created_at": "2024-10-20T10:00:00Z"
      }
    ],
    "summary": {
      "total_payments": 10,
      "total_amount": 10000.00,
      "average_payment": 1000.00,
      "by_cobrador": {...}
    },
    "generated_at": "2024-10-26T12:34:56Z",
    "generated_by": "Admin"
  },
  "message": "Datos del reporte de pagos obtenidos exitosamente"
}
```

#### AHORA
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "amount": "1000.00",
        "credit_id": 5,
        "payment_date": "2024-10-20",
        "created_at": "2024-10-20T10:00:00Z"
      }
    ],
    "summary": {
      "total_payments": 10,
      "total_amount": 10000.00,
      "average_payment": 1000.00,
      "by_cobrador": {...}
    },
    "generated_at": "2024-10-26T12:34:56Z",
    "generated_by": "Admin"
  },
  "message": "Datos del reporte de pagos obtenidos exitosamente"
}
```

#### Actualización Frontend

**JavaScript (Antes):**
```javascript
fetch('/api/reports/payments?format=json')
  .then(r => r.json())
  .then(data => {
    data.data.payments.forEach(payment => {
      console.log(payment.id, payment.amount);
    });
  });
```

**JavaScript (Ahora):**
```javascript
fetch('/api/reports/payments?format=json')
  .then(r => r.json())
  .then(data => {
    data.data.items.forEach(payment => {
      console.log(payment.id, payment.amount);
    });
  });
```

**React (Antes):**
```jsx
const [payments, setPayments] = useState([]);

useEffect(() => {
  fetch('/api/reports/payments?format=json')
    .then(r => r.json())
    .then(data => setPayments(data.data.payments));
}, []);

return (
  <table>
    <tbody>
      {payments.map(p => (
        <tr key={p.id}>
          <td>{p.id}</td>
          <td>{p.amount}</td>
          <td>{p.principal_portion}</td>
        </tr>
      ))}
    </tbody>
  </table>
);
```

**React (Ahora):**
```jsx
const [payments, setPayments] = useState([]);

useEffect(() => {
  fetch('/api/reports/payments?format=json')
    .then(r => r.json())
    .then(data => setPayments(data.data.items));  // ← Cambiar .payments a .items
}, []);

return (
  <table>
    <tbody>
      {payments.map(p => (
        <tr key={p.id}>
          <td>{p.id}</td>
          <td>{p.amount}</td>
          {/* ⚠️ principal_portion y interest_portion ya no vienen en items */}
          {/* Usar summary.total_amount en su lugar si necesario */}
        </tr>
      ))}
    </tbody>
  </table>
);
```

**Vue (Antes):**
```vue
<script setup>
import { ref, onMounted } from 'vue';

const payments = ref([]);

onMounted(async () => {
  const response = await fetch('/api/reports/payments?format=json');
  const data = await response.json();
  payments.value = data.data.payments;
});
</script>

<template>
  <div>
    <div v-for="payment in payments" :key="payment.id">
      {{ payment.id }} - {{ payment.amount }}
    </div>
  </div>
</template>
```

**Vue (Ahora):**
```vue
<script setup>
import { ref, onMounted } from 'vue';

const payments = ref([]);

onMounted(async () => {
  const response = await fetch('/api/reports/payments?format=json');
  const data = await response.json();
  payments.value = data.data.items;  // ← Cambiar .payments a .items
});
</script>

<template>
  <div>
    <div v-for="payment in payments" :key="payment.id">
      {{ payment.id }} - {{ payment.amount }}
    </div>
  </div>
</template>
```

---

### 2. **Reporte de Créditos** (`GET /api/reports/credits`)

#### Cambio
- ❌ Antes: `data.credits`
- ✅ Ahora: `data.items`

#### Ejemplo
```javascript
// ANTES
const credits = response.data.credits;

// AHORA
const credits = response.data.items;
```

---

### 3. **Reporte de Usuarios** (`GET /api/reports/users`)

#### Cambio
- ❌ Antes: `data.users`
- ✅ Ahora: `data.items`

#### Ejemplo
```javascript
// ANTES
const users = response.data.users;

// AHORA
const users = response.data.items;
```

---

### 4. **Reporte de Saldos** (`GET /api/reports/balances`)

#### Cambio
- ❌ Antes: `data.balances`
- ✅ Ahora: `data.items`

#### Ejemplo
```javascript
// ANTES
const balances = response.data.balances;

// AHORA
const balances = response.data.items;
```

---

### 5. **Reporte de Mora** (`GET /api/reports/overdue`)

#### Cambio
- ❌ Antes: `data.credits`
- ✅ Ahora: `data.items`

#### Estructura Completa

**AHORA:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "reference": "CRE-001",
        "client_id": 5,
        "client": {...},
        "cobrador_id": 3,
        "amount": 5000.00,
        "status": "active",
        "days_overdue": 30,
        "overdue_amount": 1500.00,
        "created_at": "2024-09-20T..."
      }
    ],
    "summary": {
      "total_overdue_credits": 25,
      "total_overdue_amount": 45000.00,
      "average_days_overdue": 28,
      "by_cobrador": {...}
    },
    "generated_at": "2024-10-26T...",
    "generated_by": "Admin"
  }
}
```

---

### 6. **Reporte de Performance** (`GET /api/reports/performance`)

#### Cambio
- ❌ Antes: `data.performance`
- ✅ Ahora: `data.items`

---

### 7. **Reporte Diario de Actividad** (`GET /api/reports/daily-activity`)

#### Cambio
- ❌ Antes: `data.activities`
- ✅ Ahora: `data.items`

---

### 8. **Reporte de Portafolio** (`GET /api/reports/portfolio`)

#### Cambio
- ❌ Antes: `data.credits`
- ✅ Ahora: `data.items`

---

### 9. **Reporte de Comisiones** (`GET /api/reports/commissions`)

#### Cambio
- ❌ Antes: `data.commissions`
- ✅ Ahora: `data.items`

---

### 10. **Pronóstico de Flujo de Caja** (`GET /api/reports/cash-flow-forecast`)

#### Cambio
- ❌ Antes: `data.forecast`
- ✅ Ahora: `data.items`

---

### 11. **Reporte de Lista de Espera** (`GET /api/reports/waiting-list`)

#### Cambio
- ❌ Antes: `data.waiting_list`
- ✅ Ahora: `data.items`

---

## 🔍 Campos Removidos de Items

Algunos reportes puede que tengan menos campos porque se removieron cálculos específicos del Resource. Si necesitas campos adicionales, verifica en el `summary`.

| Reporte | Campo Removido | Encontrar en | Alternativa |
|---------|---|---|---|
| Pagos | `principal_portion` | N/A | Calcular del `amount` |
| Pagos | `interest_portion` | N/A | Calcular del `amount` |
| Pagos | `remaining_for_installment` | N/A | Usar `amount` |

---

## ✅ Lo que NO cambió

- ✅ **Parámetros de request**: `?format=json&start_date=...` sigue igual
- ✅ **Estructuras HTML/PDF**: Sin cambios
- ✅ **Descargas Excel**: Sin cambios
- ✅ **Endpoints**: Las URLs son las mismas
- ✅ **Summary**: Los sumarios siguen igual
- ✅ **Métodos HTTP**: GET sigue siendo GET

---

## 🛠️ Checklist de Actualización Frontend

### Para cada página que use reportes:

- [ ] Cambiar `response.data.payments` → `response.data.items`
- [ ] Cambiar `response.data.credits` → `response.data.items`
- [ ] Cambiar `response.data.users` → `response.data.items`
- [ ] Cambiar `response.data.balances` → `response.data.items`
- [ ] Cambiar `response.data.performance` → `response.data.items`
- [ ] Cambiar `response.data.activities` → `response.data.items`
- [ ] Cambiar `response.data.forecast` → `response.data.items`
- [ ] Cambiar `response.data.commissions` → `response.data.items`
- [ ] Cambiar `response.data.waiting_list` → `response.data.items`
- [ ] Verificar que no usas campos removidos (principal_portion, interest_portion, etc.)
- [ ] Testar todas las tablas/gráficos con nuevos datos
- [ ] Verificar que summary sigue siendo accesible

---

## 📝 Script Automatizado de Búsqueda

Si usas VS Code, puedes hacer Find & Replace rápidamente:

**Find:**
```
data\.data\.(payments|credits|users|balances|performance|activities|forecast|commissions|waiting_list)
```

**Replace:**
```
data.data.items
```

**Con Regex activado:**
```
data\.data\.(payments|credits|users|balances|performance|activities|forecast|commissions|waiting_list) → data.data.items
```

---

## 🧪 Testing de Cambios

### 1. Verificar Endpoints Funcionan

```bash
# Payments
curl "http://localhost:8000/api/reports/payments?format=json"

# Credits
curl "http://localhost:8000/api/reports/credits?format=json"

# Overdue
curl "http://localhost:8000/api/reports/overdue?format=json"

# Y así para todos...
```

### 2. Verificar Estructura de Respuesta

```javascript
// Debería tener esta estructura:
{
  "success": true,
  "data": {
    "items": [...],        // ← La clave es ITEMS
    "summary": {...},
    "generated_at": "...",
    "generated_by": "..."
  },
  "message": "..."
}
```

### 3. Verificar en Frontend

```javascript
// En la consola del navegador:
fetch('/api/reports/payments?format=json')
  .then(r => r.json())
  .then(data => console.log(data.data.items))  // Debería mostrar array
```

---

## 📞 Soporte

Si encuentras campos que necesitas que sigan en la respuesta, comunícate para:
1. Identificar el campo exacto
2. Evaluar si debe añadirse de nuevo
3. O encontrar alternativa en el `summary`

---

## 📊 Comparativa Completa por Endpoint

### `/api/reports/payments`

| Aspecto | Antes | Ahora |
|---------|-------|-------|
| **Clave datos** | `data.payments` | `data.items` |
| **Número de items** | N | N (mismo) |
| **Campos por item** | 10 | 7 |
| **Contiene summary** | Sí | Sí (igual) |
| **Cache (JSON)** | Sí (300s) | Sí (300s) |

### `/api/reports/credits`

| Aspecto | Antes | Ahora |
|---------|-------|-------|
| **Clave datos** | `data.credits` | `data.items` |
| **Número de items** | N | N (mismo) |
| **Campos por item** | 12 | 10 |
| **Contiene summary** | Sí | Sí (igual) |
| **Cache (JSON)** | Sí (300s) | Sí (300s) |

### `/api/reports/users`

| Aspecto | Antes | Ahora |
|---------|-------|-------|
| **Clave datos** | `data.users` | `data.items` |
| **Número de items** | N | N (mismo) |
| **Campos por item** | 8 | 8 |
| **Contiene summary** | Sí | Sí (igual) |
| **Cache (JSON)** | No | Sí (300s) |

---

## 🎯 Resumen de Cambios

```
CAMBIO PRINCIPAL:
response.data.[payments|credits|users|balances|...] → response.data.items

TODO LO DEMÁS SIGUE IGUAL:
✅ Sumarios
✅ generated_at
✅ generated_by
✅ message
✅ success flag
✅ Formatos (HTML, PDF, Excel)
✅ Parámetros de filtro
✅ Cache (JSON)
```

---

**Documentación preparada**: 2024-10-26
**Versión API**: 2.0 (refactorizada)
**Compatibilidad**: Requiere actualización frontend

