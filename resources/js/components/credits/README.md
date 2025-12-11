# 🎨 Sistema de Iconos y Colores para Créditos

Guía de uso del sistema estandarizado de estados, colores e iconos para créditos.

---

## 📋 Tabla de Contenidos

- [Instalación](#instalación)
- [Uso Básico](#uso-básico)
- [Componentes](#componentes)
- [Helpers](#helpers)
- [Ejemplos](#ejemplos)

---

## 🚀 Instalación

Los archivos ya están creados en:
- `/resources/js/types/credit.ts` - Tipos TypeScript
- `/resources/js/utils/creditHelpers.ts` - Funciones helper
- `/resources/js/components/credits/CreditSeverityBadge.tsx` - Componente reutilizable

---

## 📖 Uso Básico

### 1. Importar tipos y helpers

```tsx
import type { Credit, OverdueSeverity } from '@/types/credit';
import {
  getSeverityColorClass,
  getSeverityLabel,
  getSeverityIconName,
  formatCurrency,
} from '@/utils/creditHelpers';
```

### 2. Usar en componentes

```tsx
import { CreditSeverityBadge } from '@/components/credits/CreditSeverityBadge';

function MiComponente({ credit }: { credit: Credit }) {
  return (
    <div>
      <h3>{credit.client?.name}</h3>
      <p>{formatCurrency(credit.amount)}</p>

      {/* Badge de severidad */}
      <CreditSeverityBadge
        severity={credit.overdue_severity}
        daysOverdue={credit.days_overdue}
      />
    </div>
  );
}
```

---

## 🧩 Componentes

### `<CreditSeverityBadge />`

Badge reutilizable que muestra la severidad del retraso.

**Props:**

| Prop | Tipo | Default | Descripción |
|------|------|---------|-------------|
| `severity` | `OverdueSeverity` | Required | Severidad: 'none', 'light', 'moderate', 'critical' |
| `showIcon` | `boolean` | `true` | Mostrar icono |
| `showLabel` | `boolean` | `true` | Mostrar texto |
| `daysOverdue` | `number` | - | Días de retraso (opcional, sobreescribe label) |
| `className` | `string` | `''` | Clases CSS adicionales |

**Ejemplos:**

```tsx
// Básico
<CreditSeverityBadge severity="critical" />

// Con días
<CreditSeverityBadge severity="moderate" daysOverdue={5} />

// Solo icono
<CreditSeverityBadge severity="light" showLabel={false} />

// Personalizado
<CreditSeverityBadge
  severity="critical"
  className="text-lg"
/>
```

---

## 🛠️ Helpers

### Helpers de Severidad

```tsx
import {
  getSeverityColorClass,
  getSeverityBgClass,
  getSeverityBorderClass,
  getSeverityIconName,
  getSeverityLabel,
} from '@/utils/creditHelpers';

// Color del texto
const textClass = getSeverityColorClass('critical');
// → 'text-red-600 dark:text-red-400'

// Background
const bgClass = getSeverityBgClass('moderate');
// → 'bg-orange-50 dark:bg-orange-900/20'

// Border
const borderClass = getSeverityBorderClass('light');
// → 'border-amber-200 dark:border-amber-800'

// Icono
const iconName = getSeverityIconName('none');
// → 'CheckCircle'

// Label
const label = getSeverityLabel('critical');
// → 'Crítico'
```

### Helpers de Estado de Pago

```tsx
import {
  getPaymentStatusColorClass,
  getPaymentStatusLabel,
} from '@/utils/creditHelpers';

const colorClass = getPaymentStatusColorClass('at_risk');
// → 'text-amber-600 dark:text-amber-400'

const label = getPaymentStatusLabel('critical');
// → 'Crítico'
```

### Helpers de Estado del Crédito

```tsx
import {
  getCreditStatusColorClass,
  getCreditStatusLabel,
} from '@/utils/creditHelpers';

const colorClass = getCreditStatusColorClass('active');
// → 'text-green-600 dark:text-green-400'

const label = getCreditStatusLabel('pending_approval');
// → 'Pendiente de aprobación'
```

### Helpers de Formato

```tsx
import {
  formatCurrency,
  formatDate,
  getFrequencyLabel,
} from '@/utils/creditHelpers';

formatCurrency(5000);
// → 'Bs. 5.000,00'

formatDate('2024-12-01');
// → '1 dic 2024'

getFrequencyLabel('weekly');
// → 'Semanal'
```

---

## 💡 Ejemplos

### Ejemplo 1: Tabla de Créditos

```tsx
import { Credit } from '@/types/credit';
import { CreditSeverityBadge } from '@/components/credits/CreditSeverityBadge';
import { formatCurrency, getCreditStatusLabel } from '@/utils/creditHelpers';

function CreditTable({ credits }: { credits: Credit[] }) {
  return (
    <table>
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Monto</th>
          <th>Balance</th>
          <th>Estado</th>
          <th>Retraso</th>
        </tr>
      </thead>
      <tbody>
        {credits.map((credit) => (
          <tr key={credit.id}>
            <td>{credit.client?.name}</td>
            <td>{formatCurrency(credit.amount)}</td>
            <td>{formatCurrency(credit.balance)}</td>
            <td>{getCreditStatusLabel(credit.status)}</td>
            <td>
              <CreditSeverityBadge
                severity={credit.overdue_severity}
                daysOverdue={credit.days_overdue}
              />
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
```

### Ejemplo 2: Card de Crédito

```tsx
import { Credit } from '@/types/credit';
import { CreditSeverityBadge } from '@/components/credits/CreditSeverityBadge';
import {
  formatCurrency,
  getSeverityBgClass,
  getSeverityBorderClass,
} from '@/utils/creditHelpers';

function CreditCard({ credit }: { credit: Credit }) {
  return (
    <div
      className={`rounded-lg p-4 border-2 ${getSeverityBgClass(
        credit.overdue_severity
      )} ${getSeverityBorderClass(credit.overdue_severity)}`}
    >
      <div className="flex justify-between items-start mb-4">
        <div>
          <h3 className="font-bold text-lg">{credit.client?.name}</h3>
          <p className="text-sm text-gray-600">
            CI: {credit.client?.ci}
          </p>
        </div>
        <CreditSeverityBadge
          severity={credit.overdue_severity}
          daysOverdue={credit.days_overdue}
        />
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <p className="text-sm text-gray-600">Monto</p>
          <p className="font-semibold">{formatCurrency(credit.amount)}</p>
        </div>
        <div>
          <p className="text-sm text-gray-600">Balance</p>
          <p className="font-semibold">{formatCurrency(credit.balance)}</p>
        </div>
      </div>

      {credit.requires_attention && (
        <div className="mt-4 p-2 bg-red-50 border border-red-200 rounded">
          <p className="text-sm text-red-600 font-medium">
            ⚠️ Requiere atención inmediata
          </p>
        </div>
      )}
    </div>
  );
}
```

### Ejemplo 3: Badge Personalizado (sin componente)

```tsx
import { AlertCircle } from 'lucide-react';
import {
  getSeverityColorClass,
  getSeverityBgClass,
  getSeverityLabel,
} from '@/utils/creditHelpers';

function CustomBadge({ credit }: { credit: Credit }) {
  return (
    <span
      className={`inline-flex items-center gap-2 px-3 py-1 rounded-full ${getSeverityBgClass(
        credit.overdue_severity
      )} ${getSeverityColorClass(credit.overdue_severity)}`}
    >
      <AlertCircle className="h-4 w-4" />
      <span className="font-medium">
        {getSeverityLabel(credit.overdue_severity)}
      </span>
      {credit.days_overdue > 0 && (
        <span className="text-xs">
          ({credit.days_overdue} días)
        </span>
      )}
    </span>
  );
}
```

### Ejemplo 4: Lista de Alertas

```tsx
import { Credit } from '@/types/credit';
import { CreditSeverityBadge } from '@/components/credits/CreditSeverityBadge';

function AlertList({ credits }: { credits: Credit[] }) {
  // Filtrar solo créditos que requieren atención
  const alertCredits = credits.filter((c) => c.requires_attention);

  return (
    <div className="space-y-2">
      <h2 className="text-lg font-bold">
        Créditos que requieren atención ({alertCredits.length})
      </h2>

      {alertCredits.map((credit) => (
        <div
          key={credit.id}
          className="flex items-center justify-between p-3 border rounded-lg"
        >
          <div className="flex items-center gap-3">
            <CreditSeverityBadge
              severity={credit.overdue_severity}
              showLabel={false}
            />
            <div>
              <p className="font-medium">{credit.client?.name}</p>
              <p className="text-sm text-gray-600">
                {credit.overdue_installments} cuotas atrasadas
              </p>
            </div>
          </div>
          <button className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Ver detalles
          </button>
        </div>
      ))}
    </div>
  );
}
```

---

## 🎨 Mapeo de Estados

### Severidad de Retraso

| Severidad | Color | Icono | Label | Condición |
|-----------|-------|-------|-------|-----------|
| `none` | 🟢 Verde | `CheckCircle` | "Al día" | 0 días |
| `light` | 🟡 Amarillo | `AlertTriangle` | "Alerta leve" | 1-3 días |
| `moderate` | 🟠 Naranja | `AlertCircle` | "Alerta moderada" | 4-7 días |
| `critical` | 🔴 Rojo | `XCircle` | "Crítico" | >7 días |

---

## 📝 Notas Importantes

1. **Backend es la fuente de verdad**: Los estados (`overdue_severity`, `payment_status`, etc.) vienen calculados desde el backend.

2. **Fallback incluido**: Los getters tienen fallback para compatibilidad si el backend no envía los campos.

3. **Dark mode**: Todos los helpers soportan modo oscuro automáticamente con Tailwind CSS.

4. **Accesibilidad**: Siempre usar icono + color + texto para cumplir WCAG 2.1.

5. **Consistencia**: Usar siempre los mismos helpers en toda la aplicación para mantener consistencia visual.

---

## 🔗 Referencias

- Documentación completa: `/SISTEMA-ESTANDARIZADO-ESTADOS.md`
- Tipos TypeScript: `/resources/js/types/credit.ts`
- Helpers: `/resources/js/utils/creditHelpers.ts`
- Componentes: `/resources/js/components/credits/`

---

**Última actualización:** 2025-12-10
