# 🔐 Flujo de Autorización - De Frontend a Respuesta

## 📊 Diagrama del Flujo Completo

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          FRONTEND (Flutter)                             │
│                                                                         │
│  User: Manager (ID: 42)                                               │
│  Request: GET /api/reports/payments?format=json                       │
└──────────────────────────────────┬──────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                       CONTROLLER                                         │
│            ReportController@paymentsReport()                            │
│                                                                         │
│  1. Valida request                                                      │
│  2. Obtiene Auth::user() = Manager (ID: 42)                            │
│  3. Crea PaymentReportService                                          │
│  4. Llama: $service->generateReport(                                   │
│       filters: [...],                                                  │
│       currentUser: Manager (ID: 42)  ← ¡USUARIO AUTENTICADO!          │
│     )                                                                   │
└──────────────────────────────────┬──────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        SERVICE LAYER                                    │
│                 PaymentReportService                                    │
│                                                                         │
│  use AuthorizeReportAccessTrait;                                       │
│                                                                         │
│  private function buildQuery($filters, $currentUser) {                 │
│    $query = Payment::with([...]);                                      │
│                                                                         │
│    // ✅ AUTORIZACIÓN CENTRALIZADA AQUÍ                               │
│    $this->authorizeUserAccess($query, $currentUser, 'cobrador_id');   │
│                                                                         │
│    // El trait verifica:                                               │
│    // - ¿Es Admin? → Retorna todos                                     │
│    // - ¿Es Cobrador? → where('cobrador_id', $currentUser->id)        │
│    // - ¿Es Manager? → whereHas('cobrador',                           │
│    //     where('assigned_manager_id', $currentUser->id))             │
│  }                                                                      │
│                                                                         │
│  return PaymentReportDTO {                                             │
│    payments: [filtrados por autorización],                            │
│    summary: {...},                                                     │
│    generated_by: 'MANAGER'                                             │
│  }                                                                      │
│                                                                         │
│  Ejemplo Manager (ID: 42):                                             │
│  - Cobradores asignados: [43, 46, 47, 48]                             │
│  - Solo retorna pagos donde cobrador_id IN (43, 46, 47, 48)           │
│  - Los datos ya están filtrados                                        │
└──────────────────────────────────┬──────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    CONTROLLER (FORMAT HANDLER)                          │
│         ReportController@respondWithReport()                            │
│                                                                         │
│  Recibe los datos YA FILTRADOS del service:                           │
│  $data = collect($reportDTO->getPayments())                            │
│         = Solo pagos de cobradores [43, 46, 47, 48]                   │
│                                                                         │
│  Según formato solicitado:                                             │
│                                                                         │
│  if ($format === 'json') {                                             │
│    return response()->json([                                           │
│      'items' => $data,  ← Datos ya autorizados                         │
│      'summary' => $summary,                                            │
│      ...                                                               │
│    ]);                                                                  │
│  }                                                                      │
│                                                                         │
│  if ($format === 'pdf') {                                              │
│    return Pdf::loadView('reports.payments', [                         │
│      'data' => $data,  ← Datos ya autorizados                         │
│      'summary' => $summary,                                            │
│      ...                                                               │
│    ])->download(...);                                                  │
│  }                                                                      │
│                                                                         │
│  if ($format === 'excel') {                                            │
│    return Excel::download(                                             │
│      new PaymentsExport($data, $summary),  ← Datos ya autorizados     │
│      ...                                                               │
│    );                                                                   │
│  }                                                                      │
└──────────────────────────────────┬──────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│              RESPUESTA AL FRONTEND (Según Formato)                      │
│                                                                         │
│  JSON:   200 OK                                                         │
│  {                                                                      │
│    "success": true,                                                     │
│    "data": {                                                            │
│      "items": [                                                         │
│        {"id": 21, "cobrador_id": 43, "amount": 25},    ← De su cobrador
│        {"id": 15, "cobrador_id": 43, "amount": 300},   ← De su cobrador
│        ...SOLO pagos de cobradores 43, 46, 47, 48...                  │
│      ],                                                                 │
│      "summary": {...}                                                  │
│    }                                                                    │
│  }                                                                      │
│                                                                         │
│  PDF:    reporte-payments-2025-10-27-10-30-45.pdf                     │
│          (Contiene SOLO pagos de cobradores 43, 46, 47, 48)           │
│                                                                         │
│  EXCEL:  reporte-payments-2025-10-27-10-30-45.xlsx                    │
│          (Contiene SOLO pagos de cobradores 43, 46, 47, 48)           │
│                                                                         │
│  HTML:   <html>...(Datos autorizados)...</html>                       │
└─────────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    FRONTEND RECIBE RESPUESTA                            │
│                                                                         │
│  Manager verá en la app:                                               │
│  - Reportes de sus 4 cobradores                                        │
│  - NO verá datos de otros managers                                     │
│  - NO verá datos de cobradores sin asignar                             │
│                                                                         │
│  ✅ SEGURIDAD GARANTIZADA EN TODOS LOS FORMATOS                        │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 🎯 Ejemplos Prácticos por Rol

### Escenario: GET /api/reports/payments?format=json

#### Cobrador (ID: 43)
```json
{
  "success": true,
  "data": {
    "items": [
      {"id": 21, "cobrador_id": 43, "amount": 25},
      {"id": 15, "cobrador_id": 43, "amount": 300}
    ],
    "summary": {
      "total_payments": 2,
      "total_amount": 325
    }
  }
}
```
✅ **VE**: Solo sus propios 2 pagos

---

#### Manager (ID: 42) - Asignó cobradores [43, 46, 47, 48]
```json
{
  "success": true,
  "data": {
    "items": [
      {"id": 21, "cobrador_id": 43, "amount": 25},
      {"id": 15, "cobrador_id": 43, "amount": 300},
      {"id": 25, "cobrador_id": 46, "amount": 500},
      {"id": 28, "cobrador_id": 47, "amount": 150},
      {"id": 32, "cobrador_id": 48, "amount": 200}
    ],
    "summary": {
      "total_payments": 5,
      "total_amount": 1175
    }
  }
}
```
✅ **VE**: Pagos de sus 4 cobradores asignados

---

#### Admin (ID: 41)
```json
{
  "success": true,
  "data": {
    "items": [
      // TODOS los pagos del sistema
      {"id": 1, "cobrador_id": 40, "amount": 100},
      {"id": 2, "cobrador_id": 40, "amount": 150},
      {"id": 21, "cobrador_id": 43, "amount": 25},
      {"id": 15, "cobrador_id": 43, "amount": 300},
      {"id": 25, "cobrador_id": 46, "amount": 500},
      // ... más pagos ...
    ],
    "summary": {
      "total_payments": 50,
      "total_amount": 15000
    }
  }
}
```
✅ **VE**: TODOS los pagos del sistema

---

## 📄 Exportaciones (PDF, Excel, HTML)

### PDF Export - Manager
```
El PDF generado contiene:

┌──────────────────────────────────────┐
│  REPORTE DE PAGOS                    │
│  Generado por: MANAGER               │
│  Fecha: 2025-10-27                   │
├──────────────────────────────────────┤
│  Cobrador    │ Monto   │ Fecha       │
├──────────────────────────────────────┤
│  APP COBRADOR│ Bs 25   │ 2025-10-27  │
│  APP COBRADOR│ Bs 300  │ 2025-10-28  │
│  USER_46     │ Bs 500  │ 2025-10-27  │
│  USER_47     │ Bs 150  │ 2025-10-26  │
│  USER_48     │ Bs 200  │ 2025-10-25  │
├──────────────────────────────────────┤
│  Total: Bs 1,175                     │
└──────────────────────────────────────┘
```
✅ **Contiene**: SOLO pagos de sus cobradores

### Excel Export - Manager
```
archivo: reporte-payments-2025-10-27-10-30-45.xlsx

Columnas:
- Cobrador Name
- Payment Date
- Amount
- Status
- ...

Filas: Solo pagos de sus 4 cobradores
```
✅ **Contiene**: SOLO pagos de sus cobradores

### HTML Export - Manager
```html
<table>
  <tr>
    <th>Cobrador</th>
    <th>Monto</th>
    <th>Fecha</th>
  </tr>
  <!-- SOLO rows de sus cobradores -->
  <tr><td>APP COBRADOR</td><td>Bs 25</td><td>2025-10-27</td></tr>
  <tr><td>APP COBRADOR</td><td>Bs 300</td><td>2025-10-28</td></tr>
  <tr><td>USER_46</td><td>Bs 500</td><td>2025-10-27</td></tr>
  ...
</table>
```
✅ **Contiene**: SOLO datos autorizados

---

## 🔄 Resumen del Flujo de Autorización

| Paso | Qué Sucede | Dónde Ocurre | Resultado |
|------|-----------|-------------|----------|
| 1 | Frontend envía request | Cliente | Request con auth token |
| 2 | Controller recibe request | API | Extrae `Auth::user()` |
| 3 | **Service filtra datos** | **SERVICE** | **✅ AUTORIZACIÓN AQUÍ** |
| 4 | Datos ya filtrados | DTO | Solo datos autorizados |
| 5 | Controller formatea | API | Aplica formato (JSON/PDF/Excel) |
| 6 | Frontend recibe | Cliente | Respuesta autorizada |

---

## ⚠️ PUNTOS CRÍTICOS DE SEGURIDAD

### ✅ ANTES de la Autorización (En el Service)
- Se calcula quién puede ver qué
- Los datos se filtran **EN LA BD**
- La query original se modifica para incluir WHERE clause de autorización

### ✅ DURANTE la Exportación
- Los datos **YA FILTRADOS** se usan para PDF/Excel/HTML
- No hay segundo nivel de autorización
- Imposible "saltarse" la seguridad en la exportación

### ✅ Lo que el Frontend NUNCA recibe:
- ❌ Datos de otros managers
- ❌ Datos de cobradores sin asignar
- ❌ Datos que el usuario no tiene permiso de ver
- ❌ Información sensible de otros usuarios

---

## 🧪 Test: Intentar Bypass

### Escenario: Manager intenta ver pagos de cobrador no asignado

```json
Request:
GET /api/reports/payments?cobrador_id=99

Respuesta:
{
  "success": true,
  "data": {
    "items": [],  ← VACÍO porque cobrador_id=99 no está en sus cobradores
    "summary": {
      "total_payments": 0
    }
  }
}
```
✅ **La autorización previene el acceso**

---

## 📋 Comparación: Antes vs Después

### ANTES (Vulnerable)
```
Manager A solicita reportes
  ↓
Service retorna TODOS los datos
  ↓
Controller formatea
  ↓
Frontend recibe DATOS DE OTROS MANAGERS
  ❌ SECURITY BREACH!
```

### DESPUÉS (Seguro)
```
Manager A solicita reportes
  ↓
Service aplica: WHERE assigned_manager_id = 42
  ↓
Service retorna SOLO sus datos
  ↓
Controller formatea datos autorizados
  ↓
Frontend recibe SOLO sus datos
  ✅ SECURE!
```

---

## 🎯 Conclusión

La autorización implementada con `AuthorizeReportAccessTrait`:

1. ✅ **Se aplica ANTES de obtener los datos** (en el Service)
2. ✅ **Afecta TODOS los formatos** (JSON, PDF, Excel, HTML)
3. ✅ **No se puede eludir** (se aplica en la query de BD)
4. ✅ **Es consistente** en los 6 servicios de reportes
5. ✅ **Es centralized** (un único trait, un único lugar para cambiar)

**Resultado**: Manager solo ve datos de sus cobradores en TODOS los formatos y endpoints.
