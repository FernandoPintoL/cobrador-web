# 💰 Sistema de Créditos con Intereses y Cuotas Flexibles

## 📋 Características Implementadas

### ✅ Nuevos Campos en Credit
- `interest_rate` - Porcentaje de interés (ej: 20.00 para 20%)
- `total_amount` - Monto total con interés incluido
- `installment_amount` - Monto de cada cuota

### ✅ Funcionalidades Implementadas
- ✅ **Cálculo automático de intereses**
- ✅ **Cuotas flexibles** (diarias, semanales, quincenales, mensuales)
- ✅ **Manejo de pagos parciales**
- ✅ **Pagos adelantados** (múltiples cuotas)
- ✅ **Detección de atrasos**
- ✅ **Cronograma de pagos**
- ✅ **API completa** para frontend/mobile

## 🎯 Ejemplo de tu Caso de Uso

### Escenario: Crédito de 1000 Bs con 20% interés, 24 días
```php
$credit = Credit::create([
    'client_id' => 1,
    'created_by' => 2, // ID del cobrador
    'amount' => 1000.00, // Monto original
    'interest_rate' => 20.00, // 20% de interés
    'frequency' => 'daily',
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-24', // 24 días
    'status' => 'active',
]);

// Automáticamente calcula:
// - total_amount = 1200.00 (1000 + 20%)
// - installment_amount = 50.00 (1200 / 24 días)
```

### Simulación de Pagos

#### Días 1-4: Pagos regulares de 50 Bs
```php
for ($day = 1; $day <= 4; $day++) {
    // Procesar pago regular
    $result = $credit->processPayment(50.00);
    // Resultado: "Pago regular de 1 cuota"
    
    // Crear registro en BD
    $credit->payments()->create([
        'cobrador_id' => 2,
        'amount' => 50.00,
        'status' => 'completed',
        'payment_date' => "2025-01-0{$day}",
    ]);
}
```

#### Día 5: Pago especial de 190 Bs
```php
$result = $credit->processPayment(190.00);

/*
Resultado:
[
    'payment_amount' => 190.00,
    'regular_installment' => 50.00,
    'remaining_balance' => 810.00, // 1200 - 200 - 190
    'type' => 'multiple_installments',
    'installments_covered' => 3, // 190 / 50 = 3.8, floor = 3
    'message' => 'Pago cubre 3 cuota(s).',
]
*/
```

## 🔧 Métodos Disponibles en el Modelo Credit

### Cálculos Básicos
```php
$credit->calculateTotalAmount();      // 1200.00 (con interés)
$credit->calculateInstallmentAmount(); // 50.00 (cuota diaria)
$credit->calculateTotalInstallments(); // 24 (total de cuotas)
```

### Estado del Crédito
```php
$credit->getCurrentBalance();         // Balance actual a pagar
$credit->getTotalPaidAmount();        // Total ya pagado
$credit->getPendingInstallments();    // Cuotas pendientes
$credit->getExpectedInstallments();   // Cuotas que deberían estar pagadas
```

### Detección de Atrasos
```php
$credit->isOverdue();                 // true/false si está atrasado
$credit->getOverdueAmount();          // Monto del atraso
```

### Procesamiento de Pagos
```php
$result = $credit->processPayment(190.00);
// Retorna análisis completo del pago sin crear registro

// Tipos de pago detectados:
// - 'partial': Pago menor a una cuota
// - 'regular': Pago de una cuota exacta
// - 'multiple_installments': Pago de múltiples cuotas
// - 'full_payment': Pago que completa el crédito
```

### Cronograma de Pagos
```php
$schedule = $credit->getPaymentSchedule();
/*
[
    [
        'installment_number' => 1,
        'due_date' => '2025-01-01',
        'amount' => 50.00,
        'status' => 'pending'
    ],
    [
        'installment_number' => 2,
        'due_date' => '2025-01-02',
        'amount' => 50.00,
        'status' => 'pending'
    ],
    // ... 24 cuotas total
]
*/
```

## 🌐 API Endpoints Disponibles

### Procesar un Pago
```http
POST /api/credits/{credit}/payments
Content-Type: application/json

{
    "amount": 190.00,
    "payment_type": "cash",
    "notes": "Pago adelantado"
}
```

**Respuesta:**
```json
{
    "success": true,
    "message": "Pago procesado exitosamente",
    "data": {
        "payment": { /* registro del pago */ },
        "payment_analysis": {
            "type": "multiple_installments",
            "installments_covered": 3,
            "message": "Pago cubre 3 cuota(s)."
        },
        "credit_status": {
            "current_balance": 810.00,
            "total_paid": 390.00,
            "pending_installments": 19,
            "is_overdue": false,
            "overdue_amount": 0
        }
    }
}
```

### Simular un Pago (sin guardarlo)
```http
POST /api/credits/{credit}/simulate-payment
Content-Type: application/json

{
    "amount": 190.00
}
```

### Obtener Detalles del Crédito
```http
GET /api/credits/{credit}/details
```

### Obtener Cronograma de Pagos
```http
GET /api/credits/{credit}/payment-schedule
```

### Créditos Atrasados
```http
GET /api/credits/overdue
```

## 🚦 Flujo de Registro, Aprobación y Entrega de Créditos

### 1. Registro de Crédito (Solicitud)
- **Endpoint:** `POST /api/credits`
- **Descripción:** Registra una nueva solicitud de crédito.
- **Permisos:** Cobrador, Manager
- **Estado inicial:** `pending`

### 2. Aprobación de Crédito
- **Endpoint:** `POST /api/credits/{credit}/approve`
- **Descripción:** Aprueba una solicitud de crédito pendiente.
- **Permisos:** Manager
- **Estado resultante:** `approved`

### 3. Rechazo de Crédito
- **Endpoint:** `POST /api/credits/{credit}/reject`
- **Descripción:** Rechaza una solicitud de crédito pendiente.
- **Permisos:** Manager
- **Estado resultante:** `rejected`

### 4. Entrega del Crédito al Cliente
- **Endpoint:** `POST /api/credits/{credit}/deliver`
- **Descripción:** Registra la entrega del dinero al cliente. Puede ser realizado por el cobrador o el manager según permisos.
- **Permisos:** Cobrador o Manager
- **Estado resultante:** `delivered` o `active`
- **Notas:** Se debe registrar quién entregó el dinero y la fecha de entrega.

#### Ejemplo de flujo:
1. El cobrador o manager registra la solicitud de crédito (estado: `pending`).
2. El manager revisa y aprueba o rechaza la solicitud.
   - Si aprueba, el crédito pasa a estado `approved`.
   - Si rechaza, pasa a `rejected`.
3. Si es aprobado, el cobrador o manager realiza la entrega del dinero usando el endpoint `/deliver`.
4. El sistema actualiza el estado del crédito a `delivered` o `active` y registra la entrega.

#### Consideraciones de Seguridad y Auditoría
- El endpoint `/deliver` debe validar el rol del usuario.
- Se recomienda registrar en la base de datos el usuario que realizó la entrega y la fecha/hora.

## 🚀 Automatización

### Auto-cálculo de Campos
El modelo automáticamente calcula:
- `total_amount` cuando se guarda un crédito
- `installment_amount` basado en el total y número de cuotas
- `balance` se inicializa como `total_amount`

### Actualización de Balance
- Se actualiza automáticamente después de cada pago
- El crédito se marca como 'completed' cuando balance = 0

## 🎯 Beneficios del Sistema

1. **Flexibilidad total** en montos de pago
2. **Cálculo automático** de intereses
3. **Detección inteligente** de atrasos
4. **API completa** para frontend/mobile
5. **Cronograma visual** de pagos
6. **Simulación** de pagos sin afectar datos
7. **Manejo de excesos** y pagos adelantados

## 📱 Integración con WebSocket

El sistema está listo para integrarse con WebSocket para:
- Notificaciones de pagos en tiempo real
- Alertas de créditos atrasados
- Actualizaciones de estado automáticas

¡Todo implementado y listo para usar! 🎉
