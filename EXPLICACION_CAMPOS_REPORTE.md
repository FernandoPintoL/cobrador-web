# 📊 Explicación de Campos en Reporte de Créditos

**Fecha:** 2025-12-11

---

## 🎯 Campos del Reporte - Explicación Completa

### **Ejemplo Real para Entender:**
Imaginemos un crédito típico:
- Cliente solicita: **Bs 1,000**
- Tasa de interés: **10%**
- Frecuencia: **Semanal**
- Total de cuotas: **5 semanas**

---

## 📋 Explicación Campo por Campo

### **1. Monto** ✅
**Lo que solicita el cliente (capital inicial)**

```
Valor: Bs 1,000
```

**✅ Tu comprensión es CORRECTA**
- Es el dinero que el cliente pide prestado
- El capital inicial antes de intereses

---

### **2. Interés** ✅
**El costo del crédito (intereses a pagar)**

```
Cálculo: Total - Monto
       = Bs 1,100 - Bs 1,000
       = Bs 100
```

**✅ Tu comprensión es CORRECTA**
- Es lo que gana el negocio
- Se calcula: `interest_rate * amount`
- En el ejemplo: 10% de Bs 1,000 = Bs 100

---

### **3. Total (Monto Total)** ✅
**Capital + Intereses (deuda total)**

```
Cálculo: Monto + Interés
       = Bs 1,000 + Bs 100
       = Bs 1,100
```

**✅ Tu comprensión es CORRECTA**
- Es el total que el cliente debe pagar
- Campo en BD: `total_amount`

---

### **4. Por Cuota** ✅
**Valor fijo de cada cuota**

```
Cálculo: Total / Número de Cuotas
       = Bs 1,100 / 5
       = Bs 220 por cuota
```

**✅ Tu comprensión es CORRECTA**
- Cada pago debe ser de este monto
- Campo en BD: `installment_amount`
- **IMPORTANTE**: Es un valor FIJO

---

### **5. Pagado** ⚠️ (IMPORTANTE - HAY CONFUSIÓN)
**Total REALMENTE pagado (suma de todos los pagos)**

```
Código real:
$calculatedTotalPaid = $this->payments()
    ->whereIn('status', ['completed', 'partial'])
    ->sum('amount');
```

**❌ Tu comprensión NO es del todo correcta**

**Tu idea:** `Pagado = Completadas × Por Cuota`

**Realidad:** `Pagado = SUMA de todos los pagos (completed + partial)`

#### **¿Por qué NO es `Completadas × Por Cuota`?**

**Ejemplo 1: Pagos Normales**
```
Cuota 1: Bs 220 ✅ (pago completo)
Cuota 2: Bs 220 ✅ (pago completo)
Cuota 3: Bs 220 ✅ (pago completo)

Completadas = 3 pagos
Por Cuota = Bs 220
Tu fórmula: 3 × 220 = Bs 660
Pagado real: Bs 660

✅ En este caso SÍ coincide
```

**Ejemplo 2: Pagos Parciales (AQUÍ ESTÁ LA DIFERENCIA)**
```
Cuota 1: Bs 220 ✅ (pago completo)
Cuota 2: Bs 220 ✅ (pago completo)
Cuota 3: Bs 100 ⚠️ (pago PARCIAL, falta Bs 120)

Completadas = 2 pagos (solo los 'completed')
Por Cuota = Bs 220
Tu fórmula: 2 × 220 = Bs 440
Pagado real: 220 + 220 + 100 = Bs 540

❌ NO coincide! El real es Bs 540, no Bs 440
```

**Ejemplo 3: Adelantos (Cliente paga más de la cuota)**
```
Cuota 1: Bs 220 ✅
Cuota 2: Bs 440 ✅ (pagó doble, adelantó una cuota)

Completadas = 2 pagos
Por Cuota = Bs 220
Tu fórmula: 2 × 220 = Bs 440
Pagado real: 220 + 440 = Bs 660

❌ NO coincide! El real es Bs 660, no Bs 440
```

#### **Conclusión sobre "Pagado":**
✅ **Pagado = SUMA REAL de todos los pagos registrados**
- Incluye pagos completos (`status = 'completed'`)
- Incluye pagos parciales (`status = 'partial'`)
- Puede ser mayor o menor que `Completadas × Por Cuota`

---

### **6. Balance** ⚠️ (NECESITA ACLARACIÓN)
**Lo que FALTA por pagar**

```
Cálculo: Total - Pagado
       = Bs 1,100 - Bs 540
       = Bs 560
```

**Código real:**
```php
$calculatedBalance = $this->total_amount - $calculatedTotalPaid;
```

#### **Ejemplo Visual:**
```
Total a pagar:  Bs 1,100 ████████████████████
Pagado:         Bs 540   ██████████░░░░░░░░░░
Balance:        Bs 560           ██████████ ← LO QUE FALTA
```

#### **Estados del Balance:**
```
Balance = 0     → Crédito COMPLETADO ✅
Balance > 0     → Crédito ACTIVO ⏳
Balance < 0     → ERROR (cliente pagó de más) ⚠️
```

---

### **7. Completadas** ⚠️ (HAY CONFUSIÓN IMPORTANTE)
**Número de PAGOS con status 'completed'**

**❌ Tu comprensión NO es correcta**

**Tu idea:** "Cuotas pagadas/registradas en tabla payments"

**Realidad:** "PAGOS con status = 'completed' (no cuotas completas)"

```
Código real:
$calculatedPaidInstallments = $this->payments()
    ->where('status', 'completed')
    ->count();
```

#### **Diferencia Clave: PAGOS vs CUOTAS**

**Escenario 1: Un pago por cuota (normal)**
```
Tabla payments:
ID | Cuota | Monto  | Status
1  | 1     | Bs 220 | completed
2  | 2     | Bs 220 | completed
3  | 3     | Bs 220 | completed

Completadas = 3
✅ Coincide con número de cuotas pagadas
```

**Escenario 2: Múltiples pagos para una cuota (pagos fraccionados)**
```
Tabla payments:
ID | Cuota | Monto  | Status
1  | 1     | Bs 220 | completed
2  | 2     | Bs 100 | partial   ← primer pago de cuota 2
3  | 2     | Bs 120 | completed ← segundo pago de cuota 2
4  | 3     | Bs 220 | completed

Completadas = 3 (IDs: 1, 3, 4)
Pero solo 3 CUOTAS están pagadas
```

**Escenario 3: Un pago cubre múltiples cuotas (adelanto)**
```
Tabla payments:
ID | Cuota | Monto  | Status
1  | 1     | Bs 440 | completed ← paga cuotas 1 y 2 juntas

Completadas = 1 (solo 1 pago)
Pero cubre 2 cuotas
```

#### **Conclusión sobre "Completadas":**
⚠️ **Completadas = Número de PAGOS con status 'completed'**
- NO es número de cuotas completas
- Es el COUNT de registros en `payments` con `status = 'completed'`
- Puede haber múltiples pagos para una cuota
- Un pago puede cubrir múltiples cuotas

---

### **8. Esperadas** ⚠️ (NECESITA ACLARACIÓN)
**Cuotas que DEBERÍAN estar pagadas según el cronograma**

```
Código real:
public function getExpectedInstallments(): int
{
    $schedule = $this->getPaymentSchedule();
    $currentDate = Carbon::now()->startOfDay();

    $expectedCount = 0;
    foreach ($schedule as $installment) {
        $dueDate = Carbon::parse($installment['due_date']);

        if ($dueDate->lte($currentDate)) {
            $expectedCount++;
        }
    }

    return $expectedCount;
}
```

#### **Ejemplo con Fechas:**

**Crédito creado:** 01/12/2025
**Frecuencia:** Semanal
**Total cuotas:** 5

**Cronograma:**
```
Cuota 1: Vence 01/12/2025 (lunes)
Cuota 2: Vence 08/12/2025 (lunes)
Cuota 3: Vence 15/12/2025 (lunes)
Cuota 4: Vence 22/12/2025 (lunes)
Cuota 5: Vence 29/12/2025 (lunes)
```

**Si HOY es 11/12/2025:**
```
✅ Cuota 1: Vence 01/12 (ya pasó)
✅ Cuota 2: Vence 08/12 (ya pasó)
⏰ Cuota 3: Vence 15/12 (futuro)
⏰ Cuota 4: Vence 22/12 (futuro)
⏰ Cuota 5: Vence 29/12 (futuro)

Esperadas = 2
```

**Si HOY es 20/12/2025:**
```
✅ Cuota 1: Vence 01/12 (ya pasó)
✅ Cuota 2: Vence 08/12 (ya pasó)
✅ Cuota 3: Vence 15/12 (ya pasó)
⏰ Cuota 4: Vence 22/12 (futuro)
⏰ Cuota 5: Vence 29/12 (futuro)

Esperadas = 3
```

#### **Conclusión sobre "Esperadas":**
✅ **Esperadas = Cuotas cuya fecha de vencimiento ya pasó**
- Se calcula según el cronograma de pagos
- Considera la fecha actual
- NO importa si están pagadas o no
- Es lo que "DEBERÍA" estar pagado según el plan

---

## 🔍 Comparando Completadas vs Esperadas

### **Escenario 1: Cliente al día**
```
Esperadas = 3 (deberían estar pagadas)
Completadas = 3 (pagos registrados)

Estado: ✅ AL DÍA
```

### **Escenario 2: Cliente con retraso**
```
Esperadas = 5 (deberían estar pagadas)
Completadas = 2 (solo 2 pagos registrados)

Estado: ⚠️ RETRASO de 3 cuotas
```

### **Escenario 3: Cliente adelantado**
```
Esperadas = 2 (deberían estar pagadas)
Completadas = 4 (pagó más de lo esperado)

Estado: ✅ ADELANTADO 2 cuotas
```

---

## 📊 Ejemplo Completo con Todos los Campos

### **Datos del Crédito:**
```
Cliente: Juan Pérez
Monto: Bs 1,000
Interés: 10%
Total: Bs 1,100
Frecuencia: Semanal
Total Cuotas: 5
Por Cuota: Bs 220
Fecha inicio: 01/12/2025
```

### **Cronograma:**
```
Cuota 1: Vence 01/12/2025 → Bs 220
Cuota 2: Vence 08/12/2025 → Bs 220
Cuota 3: Vence 15/12/2025 → Bs 220
Cuota 4: Vence 22/12/2025 → Bs 220
Cuota 5: Vence 29/12/2025 → Bs 220
```

### **Pagos Registrados (hoy es 20/12/2025):**
```
ID | Fecha      | Cuota | Monto  | Status
1  | 01/12/2025 | 1     | Bs 220 | completed
2  | 08/12/2025 | 2     | Bs 220 | completed
3  | 15/12/2025 | 3     | Bs 100 | partial
```

### **Cálculo de Campos:**
```
1. Monto:       Bs 1,000  (capital solicitado)
2. Interés:     Bs 100    (10% de 1,000)
3. Total:       Bs 1,100  (1,000 + 100)
4. Por Cuota:   Bs 220    (1,100 / 5)
5. Pagado:      Bs 540    (220 + 220 + 100) ← SUMA REAL
6. Balance:     Bs 560    (1,100 - 540)
7. Completadas: 2         (2 pagos 'completed')
8. Esperadas:   3         (cuotas con vencimiento <= hoy)
```

### **Interpretación:**
```
✅ Debería haber pagado: 3 cuotas (Esperadas)
⚠️ Solo ha pagado: 2 cuotas completas (Completadas)

Estado: RETRASO de 1 cuota
- Falta completar cuota 3: Bs 120
- Falta pagar cuotas 4 y 5: Bs 440
- Balance total: Bs 560
```

---

## 🎯 Resumen de Correcciones

### ✅ **Correctas:**
1. **Monto** = Lo que solicita el cliente
2. **Interés** = Costo del crédito
3. **Total** = Monto + Interés
4. **Por Cuota** = Valor fijo de cada cuota

### ⚠️ **Necesitan Corrección:**

5. **Pagado**
   - ❌ NO es: `Completadas × Por Cuota`
   - ✅ ES: Suma REAL de todos los pagos (completed + partial)

6. **Balance**
   - ✅ ES: `Total - Pagado` (lo que falta por pagar)

7. **Completadas**
   - ❌ NO es: Número de cuotas pagadas
   - ✅ ES: Número de PAGOS con status 'completed'

8. **Esperadas**
   - ✅ ES: Número de cuotas cuyo vencimiento ya pasó (según cronograma)

---

## 💡 Conclusiones Clave

### **1. Pagos ≠ Cuotas**
- Un pago NO es lo mismo que una cuota
- Puede haber múltiples pagos para una cuota (parciales)
- Un pago puede cubrir múltiples cuotas (adelantos)

### **2. Completadas ≠ Cuotas Pagadas**
- "Completadas" cuenta PAGOS, no cuotas
- Un cliente puede tener 3 pagos pero solo 2 cuotas completas
- O tener 1 pago que cubre 2 cuotas

### **3. Esperadas = Cronograma Teórico**
- No importa si están pagadas o no
- Solo importa la fecha de vencimiento
- Sirve para detectar retrasos

### **4. Balance = Realidad Financiera**
- Es el cálculo más importante
- `Total - Pagado = Balance`
- Cuando Balance = 0 → Crédito completado

---

## 📝 Campos en el Código

```php
// app/Models/Credit.php

// 1. Monto
$credit->amount

// 2. Interés
$interest = $credit->total_amount - $credit->amount

// 3. Total
$credit->total_amount

// 4. Por Cuota
$credit->installment_amount

// 5. Pagado (línea 1074-1076)
$totalPaid = $credit->payments()
    ->whereIn('status', ['completed', 'partial'])
    ->sum('amount');

// 6. Balance (línea 1084)
$balance = $credit->total_amount - $totalPaid;

// 7. Completadas (línea 1079-1081)
$completed = $credit->payments()
    ->where('status', 'completed')
    ->count();

// 8. Esperadas (línea 322-346)
$expected = $credit->getExpectedInstallments();
```

---

Espero que ahora esté todo claro! 🎉
