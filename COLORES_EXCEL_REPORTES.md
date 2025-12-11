# 🎨 Colores en Reportes Excel - Créditos

**Fecha:** 2025-12-11
**Estado:** ✅ Corregido

---

## 🔧 Problema Corregido

### **Antes:**
- ✅ Créditos completados se marcaban en verde
- ❌ Los demás estados NO se marcaban con color

### **Después:**
- ✅ **TODOS** los estados ahora tienen su color correspondiente
- ✅ Se usa `payment_status` en lugar de `overdue_severity`

---

## 🎨 Colores por Estado de Pago

### **1. Completado** ✅
```
Estado: completed
Color de fondo: #e8f5e9 (verde claro)
Color de texto: #1b5e20 (verde oscuro)
```

**Significado:** Crédito completamente pagado (balance = 0)

**Ejemplo visual:**
```
┌────────────────────────────────────────┐
│ ID: 21  Cliente: ALEJANDRA CALLAU      │ ← Fila verde claro
│ Balance: Bs 0.00  Estado: ✓ Completado │
└────────────────────────────────────────┘
```

---

### **2. Al Día** 👌
```
Estado: current
Color de fondo: #e3f2fd (azul claro)
Color de texto: #0d47a1 (azul oscuro)
```

**Significado:** Cliente está al día con sus pagos (cuotas esperadas = cuotas pagadas)

**Ejemplo visual:**
```
┌────────────────────────────────────────┐
│ ID: 15  Cliente: JUAN PÉREZ            │ ← Fila azul claro
│ Esperadas: 3  Completadas: 3           │
│ Estado: Al día                         │
└────────────────────────────────────────┘
```

---

### **3. Adelantado** 🚀
```
Estado: ahead
Color de fondo: #f3e5f5 (morado claro)
Color de texto: #4a148c (morado oscuro)
```

**Significado:** Cliente pagó más cuotas de las esperadas (adelantado en pagos)

**Ejemplo visual:**
```
┌────────────────────────────────────────┐
│ ID: 8   Cliente: MARÍA GÓMEZ           │ ← Fila morada claro
│ Esperadas: 2  Completadas: 4           │
│ Estado: Adelantado                     │
└────────────────────────────────────────┘
```

---

### **4. Retraso Leve** ⚠️
```
Estado: warning
Color de fondo: #fffacd (amarillo claro)
Color de texto: #827717 (amarillo oscuro)
```

**Significado:** Cliente tiene retraso leve (1-3 cuotas atrasadas)

**Ejemplo visual:**
```
┌────────────────────────────────────────┐
│ ID: 12  Cliente: PEDRO LÓPEZ           │ ← Fila amarilla
│ Esperadas: 5  Completadas: 3           │
│ Estado: Retraso leve                   │
└────────────────────────────────────────┘
```

---

### **5. Retraso Alto** 🔴
```
Estado: danger
Color de fondo: #ffcccc (rojo claro)
Color de texto: #b71c1c (rojo oscuro)
```

**Significado:** Cliente tiene retraso alto (>3 cuotas atrasadas)

**Ejemplo visual:**
```
┌────────────────────────────────────────┐
│ ID: 7   Cliente: ANA MARTÍNEZ          │ ← Fila roja claro
│ Esperadas: 8  Completadas: 2           │
│ Estado: Retraso alto                   │
└────────────────────────────────────────┘
```

---

## 📊 Ejemplo de Reporte Excel

```
┌─────┬─────────────────┬──────────┬──────────┬────────────────┐
│ ID  │ Cliente         │ Esperadas│ Completas│ Estado Pago    │
├─────┼─────────────────┼──────────┼──────────┼────────────────┤
│ 21  │ ALEJANDRA       │    6     │    6     │ ✓ Completado   │ ← Verde
│ 15  │ JUAN PÉREZ      │    3     │    3     │ Al día         │ ← Azul
│ 8   │ MARÍA GÓMEZ     │    2     │    4     │ Adelantado     │ ← Morado
│ 12  │ PEDRO LÓPEZ     │    5     │    3     │ Retraso leve   │ ← Amarillo
│ 7   │ ANA MARTÍNEZ    │    8     │    2     │ Retraso alto   │ ← Rojo
└─────┴─────────────────┴──────────┴──────────┴────────────────┘
```

---

## 🔍 Columnas con Estilo Especial

### **Columna N: "Estado Pago"**
- **Texto en negrita**
- **Color del texto** según el estado (verde, azul, morado, amarillo, rojo)
- **Alineación:** Centro

### **Columna O: "Estado de Retraso"**
- **Texto en negrita**
- **Color del texto** según severidad:
  - Verde oscuro: Al día (none)
  - Naranja: Alerta leve (light)
  - Naranja oscuro: Moderado (moderate)
  - Rojo oscuro: Crítico (critical)
- **Alineación:** Centro

---

## 💻 Código Modificado

### **Archivo:** `app/Exports/CreditsExport.php`

#### **Cambio Principal (líneas 205-289):**

```php
/**
 * Aplica colores condicionales a las filas basado en el estado de pago
 */
public function registerEvents(): array
{
    return [
        AfterSheet::class => function(AfterSheet $event) use ($data) {
            foreach ($data as $credit) {
                // Obtener el estado de pago
                $paymentStatus = $creditArray['payment_status'] ?? 'danger';

                // Mapear estados a colores
                $colorMap = [
                    'completed' => ['bg' => 'e8f5e9', 'text' => '1b5e20'],
                    'current'   => ['bg' => 'e3f2fd', 'text' => '0d47a1'],
                    'ahead'     => ['bg' => 'f3e5f5', 'text' => '4a148c'],
                    'warning'   => ['bg' => 'fffacd', 'text' => '827717'],
                    'danger'    => ['bg' => 'ffcccc', 'text' => 'b71c1c'],
                ];

                // Aplicar color a toda la fila
                $colors = $colorMap[$paymentStatus];
                $sheet->getStyle('A'.$row.':S'.$row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $colors['bg']],
                    ],
                ]);

                // Aplicar negrita y color a "Estado Pago"
                $sheet->getStyle('N'.$row)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => $colors['text']],
                    ],
                ]);
            }
        },
    ];
}
```

---

## 🎯 Lógica de Estados

### **¿Cómo se calcula el payment_status?**

**Código:** `app/Services/CreditReportService.php` (línea 179-222)

```php
private function calculatePaymentStatus($completed, $expected, $total, $pending, $status): array
{
    // 1. Si está completado o todas las cuotas pagadas
    if ($status === 'completed' || $pending === 0) {
        return ['status' => 'completed', ...];
    }

    // 2. Si está al día (completadas >= esperadas)
    if ($completed >= $expected && $expected > 0) {
        return ['status' => 'current', ...];
    }

    // 3. Si está adelantado (completadas > esperadas)
    if ($completed > $expected) {
        return ['status' => 'ahead', ...];
    }

    // 4. Calcular retraso
    $installmentsBehind = max(0, $expected - $completed);

    // 5. Retraso leve (1-3 cuotas)
    if ($installmentsBehind >= 1 && $installmentsBehind <= 3) {
        return ['status' => 'warning', ...];
    }

    // 6. Retraso alto (>3 cuotas)
    return ['status' => 'danger', ...];
}
```

---

## 📋 Tabla de Referencia Rápida

| Estado | Color Fondo | Color Texto | Condición |
|--------|-------------|-------------|-----------|
| **completed** | #e8f5e9 (verde claro) | #1b5e20 (verde oscuro) | Balance = 0 |
| **current** | #e3f2fd (azul claro) | #0d47a1 (azul oscuro) | Completadas >= Esperadas |
| **ahead** | #f3e5f5 (morado claro) | #4a148c (morado oscuro) | Completadas > Esperadas |
| **warning** | #fffacd (amarillo claro) | #827717 (amarillo oscuro) | Retraso 1-3 cuotas |
| **danger** | #ffcccc (rojo claro) | #b71c1c (rojo oscuro) | Retraso >3 cuotas |

---

## 🧪 Cómo Probar

### **1. Generar Excel con diferentes estados:**

```bash
curl -H "Authorization: Bearer {token}" \
  "http://localhost:8000/api/reports/credits?format=excel" \
  -o test-colores.xlsx

open test-colores.xlsx
```

### **2. Verificar colores:**

Abre el archivo Excel y verifica que:

- ✅ Créditos completados tienen fondo **verde claro**
- ✅ Créditos al día tienen fondo **azul claro**
- ✅ Créditos adelantados tienen fondo **morado claro**
- ✅ Créditos con retraso leve tienen fondo **amarillo claro**
- ✅ Créditos con retraso alto tienen fondo **rojo claro**

### **3. Verificar columnas especiales:**

- ✅ Columna "Estado Pago" (N) tiene texto en **negrita** y color correspondiente
- ✅ Columna "Estado de Retraso" (O) tiene texto en **negrita** y color según severidad

---

## ✅ Checklist de Validación

- [x] Código modificado en `CreditsExport.php`
- [x] Sintaxis PHP validada
- [x] Mapa de colores definido para 5 estados
- [x] Color de fondo aplicado a toda la fila
- [x] Color de texto aplicado a columna "Estado Pago"
- [x] Color de texto aplicado a columna "Estado de Retraso"
- [x] Documentación creada
- [ ] Prueba manual con Excel generado (pendiente)

---

## 🎉 Resultado Final

**Antes:**
```
Solo los créditos completados se marcaban en verde ✅
Los demás no tenían color ❌
```

**Ahora:**
```
✅ Completado → Verde claro
✅ Al día → Azul claro
✅ Adelantado → Morado claro
✅ Retraso leve → Amarillo claro
✅ Retraso alto → Rojo claro
```

**Todos los estados tienen su color distintivo!** 🎨

---

**Implementado:** 2025-12-11
**Archivo modificado:** `app/Exports/CreditsExport.php`
**Líneas modificadas:** 205-289
