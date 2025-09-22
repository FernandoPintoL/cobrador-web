# Contrato API para Frontend: Reportes y Pagos

Fecha: 2025-09-22 10:55
Autor: Equipo de desarrollo

Objetivo: Este documento resume, en un solo lugar y orientado a frontend, cómo consumir los endpoints de Reportes y de Pagos. Incluye contratos (request/response), enums válidos, ejemplos de uso con axios/fetch y tipos TypeScript sugeridos.

---

## 1) Autenticación y encabezados

- Autenticación: Sanctum con token Bearer.
- Header: `Authorization: Bearer <token>`
- JSON por defecto: todas las respuestas exitosas usan el envoltorio común (ver siguiente sección).
- Fechas en JSON: Laravel envía datetimes en ISO 8601 (ej. `2025-09-22T10:30:00.000000Z`).
- Decimales: por casts de Laravel, montos pueden venir como string ("123.45"). En el UI, conviértelos a número si es necesario.

---

## 2) Convenciones de respuesta

Respuestas exitosas:

```json
{
  "success": true,
  "data": {},
  "message": "Texto opcional"
}
```

Respuestas de error:

```json
{
  "success": false,
  "message": "Descripción del error",
  "data": {}
}
```

---

## 3) Enums y constantes relevantes

- payment_method (unificado y validado por backend): `"cash" | "transfer" | "card" | "mobile_payment"`
- payment.status: `"pending" | "completed" | "failed" | "cancelled" | "partial"`
- credit.status (usados en reportes): `"pending_approval" | "waiting_delivery" | "active" | "completed"`
- credit.frequency: `"daily" | "weekly" | "monthly"`
- client_category: `"A" | "B" | "C"`

---

## 4) Reportes

Todos los reportes comparten:
- Método: GET
- Formatos: `format=html|pdf|json|excel`
- Por defecto (sin format) devuelve PDF. Para UI, usa `format=json` para previsualización y luego `format=excel`/`pdf` para descarga.

### 4.1) Reporte de Pagos

Endpoint: `GET /api/reports/payments`

Query params:
- `start_date?: string (YYYY-MM-DD)`
- `end_date?: string (YYYY-MM-DD)`, debe ser >= `start_date` si ambos existen
- `cobrador_id?: number`
- `format?: "json" | "pdf" | "html" | "excel"`

Visibilidad por rol:
- Cobrador: solo pagos del propio cobrador.
- Manager: sus cobradores y alcance de su equipo.

Respuesta (format=json):
```json
{
  "success": true,
  "data": {
    "payments": [
      {
        "id": 1,
        "credit_id": 10,
        "client_id": 21,
        "cobrador_id": 5,
        "amount": "100.00",
        "payment_method": "cash",
        "payment_date": "2025-09-20T12:00:00.000000Z",
        "status": "completed",
        "installment_number": 3,
        "received_by": 5,
        "created_at": "2025-09-20T12:05:01.000000Z",
        "updated_at": "2025-09-20T12:05:01.000000Z",
        "cobrador": { "id": 5, "name": "COBRADOR X" },
        "credit": { "id": 10, "client": { "id": 21, "name": "CLIENTE Y" } }
      }
    ],
    "summary": {
      "total_payments": 25,
      "total_amount": 2500,
      "average_payment": 100,
      "total_without_interest": 0,
      "total_interest": 0,
      "total_remaining_to_finish_installments": 0,
      "date_range": { "start": "2025-09-01", "end": "2025-09-21" }
    },
    "generated_at": "2025-09-21T10:00:00.000000Z",
    "generated_by": "USUARIO"
  },
  "message": "Datos del reporte de pagos obtenidos exitosamente"
}
```
Notas:
- Los campos calculados por registro (porción de capital/interés) no se incluyen en JSON por defecto; sus acumulados están en `summary`.

TypeScript sugerido:
```ts
export type PaymentMethod = 'cash' | 'transfer' | 'card' | 'mobile_payment';
export type PaymentStatus = 'pending' | 'completed' | 'failed' | 'cancelled' | 'partial';

export interface PaymentRelation {
  id: number;
  name: string;
}

export interface PaymentReportItem {
  id: number;
  credit_id: number;
  client_id: number;
  cobrador_id: number;
  amount: string; // decimal como string
  payment_method: PaymentMethod;
  payment_date: string; // ISO
  status: PaymentStatus;
  installment_number: number; // 0 si no aplica
  received_by: number | null;
  created_at: string;
  updated_at: string;
  cobrador?: PaymentRelation | null;
  credit?: { id: number; client?: PaymentRelation | null } | null;
}

export interface PaymentsReportSummary {
  total_payments: number;
  total_amount: number;
  average_payment: number;
  total_without_interest?: number;
  total_interest?: number;
  total_remaining_to_finish_installments?: number;
  date_range?: { start?: string | null; end?: string | null };
}

export interface PaymentsReportResponse {
  success: true;
  data: {
    payments: PaymentReportItem[];
    summary: PaymentsReportSummary;
    generated_at: string;
    generated_by: string;
  };
  message?: string;
}
```

Descargas:
- Excel: `GET /api/reports/payments?format=excel` (responseType: 'blob')
- PDF: `GET /api/reports/payments?format=pdf` (responseType: 'blob')

Ejemplo axios descarga:
```ts
const downloadPayments = async (params: Record<string, any>, format: 'pdf'|'excel') => {
  const { data } = await axios.get('/api/reports/payments', { params: { ...params, format }, responseType: 'blob' });
  const blob = new Blob([data]);
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `reporte-pagos.${format === 'excel' ? 'xlsx' : 'pdf'}`;
  link.click();
};
```

---

### 4.2) Reporte de Créditos

Endpoint: `GET /api/reports/credits`

Query params:
- `status?: "active"|"completed"|"pending_approval"|"waiting_delivery"`
- `cobrador_id?: number`
- `client_id?: number`
- `format?: "json"|"pdf"|"html"|"excel"`

Visibilidad por rol:
- Cobrador: créditos de sus clientes asignados.
- Manager: clientes directos y de sus cobradores.

Respuesta (json) - claves relevantes en cada crédito: `id, client_id, cobrador_id, amount, total_amount, balance, status, created_at, end_date` + relaciones `client`, `cobrador`.

`summary` incluye: `total_credits, total_amount, active_credits, completed_credits, total_balance, pending_amount` (donde `pending_amount = sum(balance)`).

TypeScript sugerido:
```ts
export interface CreditRelation {
  id: number;
  name?: string;
}

export interface CreditReportItem {
  id: number;
  client_id: number;
  cobrador_id: number | null;
  amount: string;
  total_amount: string;
  balance: string;
  status: 'pending_approval'|'waiting_delivery'|'active'|'completed';
  created_at: string;
  end_date: string | null;
  client?: CreditRelation | null;
  cobrador?: CreditRelation | null;
}

export interface CreditsReportSummary {
  total_credits: number;
  total_amount: number;
  active_credits: number;
  completed_credits: number;
  total_balance: number;
  pending_amount: number;
}
```

---

### 4.3) Reporte de Usuarios

Endpoint: `GET /api/reports/users`

Query params:
- `role?: string` (ej. "cobrador", "manager", "client")
- `client_category?: "A"|"B"|"C"`
- `format?: "json"|"pdf"|"html"|"excel"`

Visibilidad por rol:
- Cobrador: se ve a sí mismo y a sus clientes asignados.
- Manager: se ve a sí mismo, a sus cobradores y a clientes directos e indirectos.

`summary` incluye: `total_users`, `by_role` (mapa), `by_category` (mapa), `cobradores_count`, `managers_count`.

---

### 4.4) Reporte de Balances de Caja

Endpoint: `GET /api/reports/balances`

Query params:
- `start_date?: string (YYYY-MM-DD)`
- `end_date?: string (YYYY-MM-DD)`
- `cobrador_id?: number`
- `format?: "json"|"pdf"|"html"|"excel"`

`summary` incluye: totales de columnas (`initial`, `collected`, `lent`, `final`) y `average_difference`.

---

### 4.5) Catálogo de tipos de reporte

Endpoint: `GET /api/reports/types` → devuelve metadatos: nombre, descripción, filtros, formatos.

---

## 5) Pagos (API operativa)

### 5.1) Crear pago con asignación automática de cuota

Endpoint: `POST /api/payments`

Body:
```json
{
  "credit_id": 10,
  "amount": 150.0,
  "payment_method": "cash",
  "payment_date": "2025-09-22",
  "latitude": -17.78,
  "longitude": -63.18,
  "installment_number": 3
}
```

Reglas clave:
- Valida que el crédito esté activo y que el monto no exceda el balance.
- Si el pago cubre más de una cuota, el backend crea múltiples registros `Payment` (uno por cuota o fracción), todos devueltos en la respuesta.
- `installment_number` se asigna automáticamente si no se envía.
- Si el usuario autenticado es cobrador, debe tener caja abierta el día del pago.

Respuesta:
```json
{
  "success": true,
  "data": {
    "payments": [
      {
        "id": 101,
        "credit_id": 10,
        "amount": "100.00",
        "payment_method": "cash",
        "payment_date": "2025-09-22",
        "installment_number": 3,
        "status": "completed",
        "received_by": 5
      },
      {
        "id": 102,
        "credit_id": 10,
        "amount": "50.00",
        "payment_method": "cash",
        "payment_date": "2025-09-22",
        "installment_number": 4,
        "status": "partial",
        "received_by": 5
      }
    ],
    "total_paid": 150.0
  },
  "message": "Pagos registrados exitosamente"
}
```

TS sugerido:
```ts
export interface CreatedPaymentItem {
  id: number;
  credit_id: number;
  amount: string; // decimal
  payment_method: PaymentMethod;
  payment_date: string; // YYYY-MM-DD
  installment_number: number;
  status: PaymentStatus;
  received_by: number;
}

export interface CreatePaymentsResponse {
  success: true;
  data: { payments: CreatedPaymentItem[]; total_paid: number };
  message?: string;
}
```

### 5.2) Listar pagos (paginado)

Endpoint: `GET /api/payments`

Query params soportados: `credit_id, received_by, payment_method, date_from, date_to, amount_min, amount_max, page`

Visibilidad por rol: similar a reportes; cobrador ve lo que registró (`received_by`), manager ve los pagos de su equipo.

Respuesta (paginada):
- Atributos típicos: `current_page, data: Payment[], total, per_page, last_page, from, to, links...`

---

### 5.3) Flujos de caja (Caja) integrados con pagos

Reglas y flujo cuando un cobrador registra pagos:
- Precondición: el cobrador DEBE tener una caja abierta (CashBalance) para la fecha del pago.
- La fecha usada para la caja es payment_date (YYYY-MM-DD). Si no envías payment_date, se usa la fecha actual del servidor.
- Al crear el pago, si hay caja abierta, el backend vincula automáticamente el pago con ese CashBalance (campo cash_balance_id) para conciliación.
- Si no existe caja abierta, el backend responde 400 con el mensaje "Caja no abierta" y no registra el pago.

Abrir caja (idempotente):
- Endpoint: POST /api/cash-balances/open
- Comportamiento: si ya existe una caja "open" para cobrador+fecha, la devuelve; si no, la crea.

Ejemplos de request:
- Cobrador autenticado abre su propia caja de hoy con monto inicial 100:
```http
POST /api/cash-balances/open
{
  "initial_amount": 100,
  "date": "2025-09-22"
}
```
- Manager/Admin abre caja para un cobrador específico:
```http
POST /api/cash-balances/open
{
  "cobrador_id": 5,
  "date": "2025-09-22",
  "initial_amount": 0
}
```

Respuesta de error típica cuando no hay caja abierta al intentar pagar:
```json
{
  "success": false,
  "message": "Caja no abierta",
  "data": "No existe una caja abierta para la fecha del pago. Abre la caja antes de registrar pagos."
}
```

Sugerencia de flujo en el UI para cobradores:
1) Al iniciar la jornada (o antes del primer pago), llamar a POST /api/cash-balances/open.
2) Registrar pagos normalmente con POST /api/payments.
3) Al final del día, mostrar reporte y usar GET /api/cash-balances/{id}/detailed para conciliación.

---

## 6) Ejemplos rápidos (React + axios)

Previsualizar pagos en tabla:
```tsx
const loadPaymentsReport = async (filters: Record<string, any>) => {
  const { data } = await axios.get<PaymentsReportResponse>('/api/reports/payments', { params: { ...filters, format: 'json' } });
  return data.data; // { payments, summary, generated_at, generated_by }
};
```

Descargar Excel de créditos:
```ts
await downloadBlob('/api/reports/credits', { format: 'excel', status: 'active' }, 'reporte-creditos.xlsx');
```

Helper genérico para blobs:
```ts
async function downloadBlob(url: string, params: any, filename: string) {
  const res = await axios.get(url, { params, responseType: 'blob' });
  const blob = new Blob([res.data]);
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = filename;
  a.click();
}
```

---

## 7) Reglas de visibilidad por rol (resumen)

- Cobrador: limitado a sus propios datos (pagos y balances propios; clientes asignados para créditos/usuarios).
- Manager: ve datos propios y de su equipo: cobradores con `assigned_manager_id = manager` y clientes directos/indirectos.

---

## 8) Buenas prácticas de UI

- Usa `format=json` para mostrar vista previa y permitir al usuario descargar luego en Excel/PDF.
- Usa skeletons/cargas cuando `summary` sea diferido (si se implementa lazy/deferred props en el futuro).
- Convierte montos string a número al mostrar totales si necesitas operaciones matemáticas en el UI.
- Para tablas largas, agrega filtros locales y paginación en listados operativos (por ejemplo, `/api/payments` ya es paginado), mientras que los reportes generalmente devuelven el conjunto filtrado completo.

---

## 9) Errores comunes y manejo

- 400: validación o reglas de negocio (por ejemplo, "Caja no abierta", "Monto excede cuotas").
- 403: no autorizado por rol o alcance.
- 404: recurso no encontrado.
- La clave `message` explica la razón; la clave `data` puede incluir detalles.

---

## 10) Rutas con nombre (para referencia)

- Reportes: `api.reports.payments`, `api.reports.credits`, `api.reports.users`, `api.reports.balances`, `api.reports.types`
- Pagos: `api.payments.index|store|show|update|destroy`, extras: `api.payments.by-credit`, `api.payments.by-cobrador`, `api.payments.cobrador.stats`, `api.payments.recent`, `api.payments.today-summary`

---

## 11) Cambios recientes que afectan al Frontend

- `payment_method` unificado: solo se aceptan `cash|transfer|card|mobile_payment` en creación/actualización de pagos.
- `installment_number` se guarda correctamente (y se divide el pago en varias cuotas si corresponde), ya devuelto en la respuesta de `POST /api/payments`.
- Reportes JSON de pagos incluyen `installment_number`, `payment_method`, `status`, relaciones mínimas (`cobrador`, `credit.client`).

---

Si necesitas ejemplos adicionales (p.ej., Postman collection), indica los escenarios y lo añadimos a este documento.
