# 🚀 Plan de Optimización Híbrida - Modelos Credit & Payment

## Análisis Completo de Datos Calculados vs Persistidos

---

## 1. Credit Model: Análisis Detallado

### 1.1 Campos OPTIMIZADOS (Ya persistidos correctamente)

| Campo | Tipo | Persistido | Cálculo | Estado | Riesgo |
|-------|------|-----------|---------|--------|--------|
| `total_amount` | DECIMAL | ✅ BD | Auto al crear | ✅ OK | ⚠️ BAJO |
| `installment_amount` | DECIMAL | ✅ BD | Auto al crear | ✅ OK | ⚠️ BAJO |
| `total_installments` | INT | ✅ BD | Fallback si vacío | ✅ OK | ⚠️ BAJO |
| `paid_installments` | INT | ✅ BD | Eventos | ✅ OK | ⚠️ BAJO |
| `balance` | DECIMAL | ✅ BD | Eventos | ✅ OK | ⚠️ BAJO |
| `total_paid` | DECIMAL | ✅ BD | Eventos | ✅ OK | ⚠️ BAJO |

**Conclusión**: ✅ Estos datos están correctamente optimizados. No requieren cambios.

---

### 1.2 Métodos NO OPTIMIZADOS (Se calculan dinámicamente)

#### ❌ getTotalPaidAmount()

```php
// Línea ~235 en Credit.php
public function getTotalPaidAmount()
{
    return $this->payments()->sum('amount');  // ← QUERY CADA VEZ
}
```

**Problema**:
- En un reporte de 100 créditos: 100 queries SELECT SUM
- No hay caché

**Solución Recomendada**:
```php
// OPCIÓN A: Usar campo persistido (ya existe!)
public function getTotalPaidAmount()
{
    return $this->total_paid;  // ← Ya existe en BD
}

// OPCIÓN B: Cache en memoria (si necesitas cálculo fresco)
private $cachedTotalPaid = null;

public function getTotalPaidAmount($fresh = false)
{
    if ($fresh || $this->cachedTotalPaid === null) {
        $this->cachedTotalPaid = $this->payments()->sum('amount');
    }
    return $this->cachedTotalPaid;
}
```

**Impacto**: De 100 queries → 0 queries (usa $total_paid persistido)

---

#### ❌ getExpectedInstallments()

```php
// Línea ~268
public function getExpectedInstallments()
{
    $today = now();
    $daysElapsed = $this->start_date->diffInDays($today);
    return ceil($daysElapsed / 30) + 1;  // ← CÁLCULO DINÁMICO
}
```

**Problema**:
- Depende de `now()` - cambia cada llamada
- No se puede cachear sin invalidar
- Diferente resultado si se ejecuta el mismo reporte a diferente hora

**Solución**: Crear tabla de referencia

```php
// En tabla MODIFICADA
CREATE TABLE credits (
    -- campos existentes...
    expected_installments_as_of_date DATE,  // última vez que se calculó
    expected_installments_cached INT,       // valor cacheado
);

// En modelo
public function getExpectedInstallments($recalculate = false)
{
    $today = now()->toDateString();

    // Si ya se calculó hoy, usar valor cacheado
    if (!$recalculate &&
        $this->expected_installments_as_of_date == $today &&
        $this->expected_installments_cached) {
        return $this->expected_installments_cached;
    }

    // Recalcular
    $daysElapsed = $this->start_date->diffInDays($today);
    $expected = ceil($daysElapsed / 30) + 1;

    // Actualizar caché
    $this->update([
        'expected_installments_cached' => $expected,
        'expected_installments_as_of_date' => $today,
    ]);

    return $expected;
}
```

**Ventaja**: Se calcula una sola vez por día
**Impacto**: En reportes, máximo 1 cálculo por crédito por día

---

#### ❌ getPendingInstallments()

```php
// Línea ~282
public function getPendingInstallments()
{
    return $this->getExpectedInstallments() - $this->getCompletedInstallmentsCount();
}
```

**Problema**: Depende de 2 métodos que se calculan dinámicamente

**Solución**: Cachear el resultado

```php
private $cachedPending = null;

public function getPendingInstallments($fresh = false)
{
    if ($fresh || $this->cachedPending === null) {
        $this->cachedPending = $this->getExpectedInstallments()
                              - $this->getCompletedInstallmentsCount();
    }
    return $this->cachedPending;
}
```

**En reportes**: Llamar UNA SOLA VEZ por crédito, cachea automáticamente

---

#### ❌ getOverdueAmount()

```php
// Línea ~313
public function getOverdueAmount()
{
    $pending = $this->getPendingInstallments();
    if ($pending <= 0) return 0;

    $overdueInstallments = max(0, $pending - 1);  // al menos 1 cuota "graciosa"
    return $overdueInstallments * $this->installment_amount;
}
```

**Problema**: Depende de `getPendingInstallments()` dinámico

**Solución**: Ya tiene caché indirecto a través de getPendingInstallments()

---

### 1.3 Resumen: Credit Model

| Elemento | Acción | Prioridad |
|----------|--------|-----------|
| `total_amount` | ✅ No cambiar | - |
| `paid_installments` | ✅ No cambiar | - |
| `getTotalPaidAmount()` | 🔄 Usar `$total_paid` | INMEDIATO |
| `getExpectedInstallments()` | 💾 Cachear con fecha | ALTA |
| `getPendingInstallments()` | 💾 Cachear en memoria | ALTA |

---

## 2. Payment Model: Análisis Detallado

### 2.1 Métodos CRÍTICOS

#### 🔴 CRÍTICO: principal_portion (Accessor)

```php
// Línea ~98-104
public function getPrincipalPortionAttribute()
{
    if ($this->credit) {
        $principalPerInstallment = $this->credit->amount / $this->credit->total_installments;
        $ratio = $principalPerInstallment / $this->credit->installment_amount;
        return $this->amount * $ratio;
    }
    return null;
}
```

**Problema**:
- Se ejecuta CADA VEZ que accedes a `$payment->principal_portion`
- En reporte de 100 pagos: 100 cálculos
- Además carga la relación `credit` cada vez

**Solución Recomendada**:

```php
// OPCIÓN 1: Cachear en memoria (RECOMENDADO para reportes)
protected $principalPortionCache = [];

public function getPrincipalPortion()
{
    if (!isset($this->principalPortionCache[$this->id])) {
        if ($this->credit) {
            $principalPerInstallment = $this->credit->amount / $this->credit->total_installments;
            $ratio = $principalPerInstallment / $this->credit->installment_amount;
            $this->principalPortionCache[$this->id] = $this->amount * $ratio;
        } else {
            return null;
        }
    }
    return $this->principalPortionCache[$this->id];
}
```

**En views**:
```blade
{{ $payment->getPrincipalPortion() }}  ← Cacheado automáticamente
```

---

#### 🔴 CRÍTICO: interest_portion (Accessor)

```php
// Línea ~111-117
public function getInterestPortionAttribute()
{
    if ($this->credit) {
        $totalWithInterest = $this->credit->total_amount;
        $principal = $this->principal_portion;  // ← Otro cálculo
        return $this->amount - $principal;
    }
    return null;
}
```

**Problema**: Depende de `principal_portion`, agravando el problema

**Solución**:

```php
public function getInterestPortion()
{
    $principal = $this->getPrincipalPortion();
    if ($principal === null) return null;
    return $this->amount - $principal;  // ← Ya está cacheado
}
```

---

#### 🔴 CRÍTICO: remaining_for_installment (Accessor)

```php
// Línea ~146-162
public function getRemainingForInstallmentAttribute()
{
    if ($this->credit) {
        $installmentAmount = $this->credit->installment_amount;
        $alreadyPaidInInstallment = $this->credit->payments()
            ->where('installment_number', $this->installment_number)
            ->where('id', '!=', $this->id)
            ->sum('amount');  // ← QUERY CON SUM

        return max(0, $installmentAmount - $this->amount - $alreadyPaidInInstallment);
    }
    return null;
}
```

**Problema**:
- **PELIGROSO**: Query SUM por cada pago
- En reporte de 100 pagos: 100 queries GROUP BY + SUM
- Datos pueden ser obsoletos entre pagos

**Solución Recomendada**:

```php
// OPCIÓN 1: Cachear en memoria
protected $remainingCache = [];

public function getRemaining ForInstallment()
{
    $key = $this->credit_id . '_' . $this->installment_number;

    if (!isset($this->remainingCache[$key])) {
        if ($this->credit) {
            $installmentAmount = $this->credit->installment_amount;
            $alreadyPaid = $this->credit->payments()
                ->where('installment_number', $this->installment_number)
                ->where('id', '!=', $this->id)
                ->sum('amount');

            $this->remainingCache[$key] = max(0,
                $installmentAmount - $this->amount - $alreadyPaid
            );
        }
    }

    return $this->remainingCache[$key] ?? null;
}
```

---

### 2.2 Resumen: Payment Model

| Accessor | Acción | Impacto |
|----------|--------|--------|
| `principal_portion` | 💾 Cachear en memoria | -100 cálculos/100 pagos |
| `interest_portion` | 💾 Cachear en memoria | -100 cálculos/100 pagos |
| `remaining_for_installment` | 💾 Cachear + Query | -100 queries/100 pagos |

---

## 3. Plan de Implementación: Fases

### Fase 1: Caché en Memoria (SIN cambios en BD) ✅ RECOMENDADO

**Tiempo**: 2-3 horas
**Costo**: 0 (sin migraciones)
**Riesgo**: Muy bajo

```php
// En app/Models/Payment.php

class Payment extends Model {
    protected static $principalPortionCache = [];
    protected static $remainingForInstallmentCache = [];

    public function getPrincipalPortion()
    {
        $key = $this->id;
        if (!isset(static::$principalPortionCache[$key])) {
            $principal = 0;
            if ($this->credit) {
                $principalPerInstallment = $this->credit->amount / $this->credit->total_installments;
                $ratio = $principalPerInstallment / $this->credit->installment_amount;
                $principal = $this->amount * $ratio;
            }
            static::$principalPortionCache[$key] = $principal;
        }
        return static::$principalPortionCache[$key];
    }

    public function getRemainingForInstallment()
    {
        $key = $this->credit_id . '_' . $this->installment_number . '_' . $this->id;

        if (!isset(static::$remainingForInstallmentCache[$key])) {
            $remaining = 0;
            if ($this->credit) {
                $installmentAmount = $this->credit->installment_amount;
                $alreadyPaid = $this->credit->payments()
                    ->where('installment_number', $this->installment_number)
                    ->where('id', '!=', $this->id)
                    ->sum('amount');

                $remaining = max(0, $installmentAmount - $this->amount - $alreadyPaid);
            }
            static::$remainingForInstallmentCache[$key] = $remaining;
        }

        return static::$remainingForInstallmentCache[$key];
    }
}
```

---

### Fase 2: Tabla de Caché Persistido (Opcional)

**Tiempo**: 4-5 horas
**Costo**: 1 migración nueva
**Riesgo**: Bajo (tabla separada, no modifica existentes)

```sql
CREATE TABLE payment_calculations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    payment_id BIGINT UNIQUE NOT NULL,
    principal_portion DECIMAL(12,2),
    interest_portion DECIMAL(12,2),
    remaining_for_installment DECIMAL(12,2),
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    INDEX idx_payment_id (payment_id)
);

-- Migración en Laravel
Schema::create('payment_calculations', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('payment_id')->unique();
    $table->decimal('principal_portion', 12, 2);
    $table->decimal('interest_portion', 12, 2);
    $table->decimal('remaining_for_installment', 12, 2);
    $table->timestamp('calculated_at')->useCurrent();

    $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
});
```

**Trigger automático**:
```php
// En PaymentObserver o Event Listener
public function created(Payment $payment)
{
    PaymentCalculation::create([
        'payment_id' => $payment->id,
        'principal_portion' => $payment->getPrincipalPortion(),
        'interest_portion' => $payment->getInterestPortion(),
        'remaining_for_installment' => $payment->getRemainingForInstallment(),
    ]);
}

public function updated(Payment $payment)
{
    if ($payment->isDirty('amount')) {
        $payment->calculation()->update([
            'principal_portion' => $payment->getPrincipalPortion(),
            'interest_portion' => $payment->getInterestPortion(),
            'remaining_for_installment' => $payment->getRemainingForInstallment(),
        ]);
    }
}
```

---

### Fase 3: Vistas Materializadas (Reportes rápidos)

**Tiempo**: 6-8 horas
**Costo**: 1 vista materializada
**Riesgo**: Bajo (datos pueden tener hasta 24h retraso)

```sql
CREATE MATERIALIZED VIEW mv_report_credits AS
SELECT
    c.id,
    c.client_id,
    c.amount,
    c.total_amount,
    c.balance,
    c.paid_installments,
    COUNT(p.id) as payment_count,
    SUM(p.amount) as total_paid_amount,
    MAX(p.payment_date) as last_payment_date
FROM credits c
LEFT JOIN payments p ON p.credit_id = c.id
GROUP BY c.id, c.client_id, c.amount, c.total_amount, c.balance, c.paid_installments;

-- Job (scheduleado nightly)
php artisan queue:work --queue=default
```

**En Laravel**:
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_report_credits');
    })->daily()->at('02:00');  // 2 AM
}
```

---

## 4. Checklist de Implementación

### Fase 1 (RECOMENDADO - Inmediato):
- [ ] Agregar método `getPrincipalPortion()` con caché
- [ ] Agregar método `getInterestPortion()` con caché
- [ ] Agregar método `getRemainingForInstallment()` con caché
- [ ] Actualizar reportes para usar nuevos métodos
- [ ] Tests para validar caché
- [ ] Performance tests: medir mejora

### Fase 2 (Opcional - 2-3 semanas):
- [ ] Crear migración `payment_calculations`
- [ ] Agregar PaymentObserver
- [ ] Setup listeners para eventos de creación/actualización
- [ ] Tests de sincronización

### Fase 3 (Opcional - 1 mes):
- [ ] Crear vista materializada
- [ ] Setup job diario para refresh
- [ ] Crear reporte que usa vista
- [ ] Tests de latencia aceptable

---

## 5. Validación: Antes vs Después

### Antes (Con accesos directos a accessors):

```php
// En Blade: Reporte de 100 pagos
@foreach($payments as $payment)
    {{ $payment->principal_portion }}      ← 100 cálculos
    {{ $payment->interest_portion }}       ← 100 cálculos adicionales
    {{ $payment->remaining_for_installment }} ← 100 queries SUM
@endforeach
```

**Resultado**: 200+ operaciones

### Después (Con métodos cacheados):

```php
// En Blade: Reporte de 100 pagos
@foreach($payments as $payment)
    {{ $payment->getPrincipalPortion() }}       ← 100 cálculos PERO cacheados
    {{ $payment->getInterestPortion() }}        ← 100 cálculos reutilizan caché
    {{ $payment->getRemainingForInstallment() }} ← 100 queries PERO cacheadas
@endforeach
```

**Resultado**: 100+ operaciones (máximo, con caché)

---

## 6. Riesgos Mitigados

| Riesgo | Solución | Validación |
|--------|----------|-----------|
| Desincronización | Events automáticos | Tests de sincronización |
| Cambio interest_rate | No permitir en activos | Validación en Update |
| Errores redondeo | bcmath en cálculos | Tests de precisión |
| Caché obsoleto | Invalidar en eventos | Listeners |
| Performance | Límites de caché | Monitoreo de memoria |

---

## 7. Monitoreo Post-Implementación

```php
// En reportes
$start = microtime(true);

@foreach($payments as $payment)
    $principal = $payment->getPrincipalPortion();
    // ... usar $principal
@endforeach

$time = microtime(true) - $start;
Log::info("Report execution: {$time}s");
```

**Target**: < 1 segundo para 1000 pagos

---

**Documento**: Optimización Híbrida
**Versión**: 1.0
**Recomendación**: Implementar Fase 1 (caché en memoria) INMEDIATAMENTE
