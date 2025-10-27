# ⚡ Referencia Rápida: API Reportes Actualizada

**TL;DR - El único cambio importante:**

```javascript
// ❌ VIEJO - No funciona
response.data.payments
response.data.credits
response.data.users
response.data.balances
response.data.overdue
response.data.performance
response.data.activities
response.data.commissions
response.data.waiting_list
response.data.forecast

// ✅ NUEVO - Usa ESTO
response.data.items
```

---

## 🗺️ Mapa de Cambios por Endpoint

```
GET /api/reports/payments
  ANTES: data.payments       → AHORA: data.items
  TIPO: Payment[]            → TIPO: Payment[]
  CAMBIO: Clave renombrada

GET /api/reports/credits
  ANTES: data.credits        → AHORA: data.items
  TIPO: Credit[]             → TIPO: Credit[]
  CAMBIO: Clave renombrada

GET /api/reports/users
  ANTES: data.users          → AHORA: data.items
  TIPO: User[]               → TIPO: User[]
  CAMBIO: Clave renombrada

GET /api/reports/balances
  ANTES: data.balances       → AHORA: data.items
  TIPO: Balance[]            → TIPO: Balance[]
  CAMBIO: Clave renombrada

GET /api/reports/overdue
  ANTES: data.credits        → AHORA: data.items
  TIPO: Credit[]             → TIPO: Credit[]
  CAMBIO: Clave renombrada

GET /api/reports/performance
  ANTES: data.performance    → AHORA: data.items
  TIPO: Performance[]        → TIPO: Performance[]
  CAMBIO: Clave renombrada

GET /api/reports/daily-activity
  ANTES: data.activities     → AHORA: data.items
  TIPO: Activity[]           → TIPO: Activity[]
  CAMBIO: Clave renombrada

GET /api/reports/portfolio
  ANTES: data.credits        → AHORA: data.items
  TIPO: Credit[]             → TIPO: Credit[]
  CAMBIO: Clave renombrada

GET /api/reports/commissions
  ANTES: data.commissions    → AHORA: data.items
  TIPO: Commission[]         → TIPO: Commission[]
  CAMBIO: Clave renombrada

GET /api/reports/cash-flow-forecast
  ANTES: data.forecast       → AHORA: data.items
  TIPO: Forecast[]           → TIPO: Forecast[]
  CAMBIO: Clave renombrada

GET /api/reports/waiting-list
  ANTES: data.waiting_list   → AHORA: data.items
  TIPO: WaitingItem[]        → TIPO: WaitingItem[]
  CAMBIO: Clave renombrada
```

---

## 📋 Find & Replace Rápido

### VS Code

**Find:** `data\.data\.(payments|credits|users|balances|performance|activities|commissions|waiting_list|forecast)`

**Replace:** `data.data.items`

**Con Regex ON** ✅

---

## 🔍 Estructura Completa de Respuesta

```json
{
  "success": true,
  "data": {
    "items": [],                    // ← NUEVA CLAVE GENÉRICA
    "summary": {                    // Sin cambios
      "total_payments": 10,
      "total_amount": 5000.00,
      ...
    },
    "generated_at": "2024-10-26...", // Sin cambios
    "generated_by": "Admin"          // Sin cambios
  },
  "message": "..."                   // Sin cambios
}
```

---

## 🚨 Campos Removidos (Si se necesitan)

### Reporte de Pagos
Campos que ya NO vienen en `items`:
- ❌ `principal_portion` → Usar `summary.total_amount`
- ❌ `interest_portion` → Calcular si es necesario
- ❌ `remaining_for_installment` → Usar `amount`

### Otros Reportes
Todos los Items vienen con los campos básicos. Si necesitas campos adicionales, están en el `summary`.

---

## 🛠️ Ejemplos Rápidos

### JavaScript Vanilla
```javascript
fetch('/api/reports/payments?format=json')
  .then(r => r.json())
  .then(data => {
    // ✅ Usar data.data.items
    console.log(data.data.items);
  });
```

### React
```jsx
const [items, setItems] = useState([]);

useEffect(() => {
  fetch('/api/reports/payments?format=json')
    .then(r => r.json())
    .then(data => setItems(data.data.items)); // ✅ .items
}, []);

return (
  <div>
    {items.map(item => <div key={item.id}>{item.id}</div>)}
  </div>
);
```

### Vue
```vue
<template>
  <div v-for="item in data.items" :key="item.id">
    {{ item.id }}
  </div>
</template>

<script setup>
const { data } = useReport('payments');
</script>
```

---

## ✅ Verificación Rápida

1. **Abre DevTools** (F12)
2. **Console tab**
3. **Copia esto:**

```javascript
fetch('/api/reports/payments?format=json')
  .then(r => r.json())
  .then(data => {
    console.table(data.data.items);
    console.log('✅ Items:', data.data.items.length);
    console.log('📊 Summary:', data.data.summary);
  })
  .catch(e => console.error('❌', e));
```

4. **Enter**
5. Si ves los items en la tabla → ✅ Funciona

---

## 📝 Patrón Genérico para Todos

```javascript
async function getReport(type) {
  const response = await fetch(`/api/reports/${type}?format=json`);
  const json = await response.json();

  return {
    items: json.data.items,          // ← SIEMPRE "items"
    summary: json.data.summary,      // ← Sin cambios
    generatedAt: json.data.generated_at,
    generatedBy: json.data.generated_by
  };
}

// Uso
const payments = await getReport('payments');
const credits = await getReport('credits');
const users = await getReport('users');
// Todos tienen la misma estructura ✅
```

---

## 🎯 Cambios Resumidos

| Aspecto | Cambio | Impacto |
|---------|--------|---------|
| **Clave de datos** | `{type}` → `items` | 🔴 Breaking |
| **Estructura items** | Básica | 🟡 Algunos campos removidos |
| **Summary** | Sin cambios | 🟢 Igual |
| **Endpoints** | Sin cambios | 🟢 URLs iguales |
| **Parámetros** | Sin cambios | 🟢 Filtros iguales |
| **Formatos** | Sin cambios | 🟢 HTML/PDF/Excel igual |
| **Cache JSON** | Sin cambios | 🟢 Sigue cacheado |

---

## 💡 Pro Tips

1. **Usa TypeScript** para tipos seguros
2. **Crea un hook/composable** reutilizable
3. **Centraliza la lógica** en un servicio API
4. **Testa con Postman** primero
5. **Usa console.table()** para ver arrays fácilmente

---

## 🔗 Documentación Completa

- **GUIA_CAMBIOS_API_REPORTES.md** - Guía completa con ejemplos detallados
- **EJEMPLOS_CODIGO_API_ACTUALIZADO.md** - Código para copiar/pegar
- **COMPARATIVA_BREAKING_CHANGES.md** - Análisis detallado de cambios

---

## 📞 Soporte Rápido

**Pregunta**: ¿Dónde está el campo X?
**Respuesta**: Probablemente en `response.data.summary`

**Pregunta**: ¿Por qué cambió la estructura?
**Respuesta**: Centralización de reportes (-73% código duplicado)

**Pregunta**: ¿Qué no cambió?
**Respuesta**: Endpoints, parámetros, formatos, cache, todo lo demás

---

**Último actualizado**: 2024-10-26
**Commit**: 70d7d69
**Versión**: API 2.0

