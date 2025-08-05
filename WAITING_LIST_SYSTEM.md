# 📋 Sistema de Lista de Espera para Créditos

## 🎯 Objetivo
Implementar un sistema de gestión de lista de espera para créditos, donde los créditos pueden pasar por diferentes estados antes de ser entregados al cliente, permitiendo a cobradores y managers gestionar las fechas de entrega de manera programada.

## 📊 Estados del Crédito

### 1. **pending_approval** 
- Crédito recién creado, pendiente de aprobación
- Solo visible para managers/admins
- Requiere revisión antes de pasar a lista de espera

### 2. **waiting_delivery**
- Crédito aprobado, en lista de espera para entrega
- Tiene fecha programada de entrega
- Visible para cobradores y managers

### 3. **active** 
- Crédito entregado al cliente y activo
- Inicia el proceso de pagos/cobranza
- Estado normal de funcionamiento

### 4. **rejected**
- Crédito rechazado durante el proceso de aprobación
- Se especifica motivo de rechazo
- No se puede entregar

### 5. **completed**, **defaulted**, **cancelled**
- Estados finales del crédito (sin cambios)

## 🆕 Nuevos Campos en la Tabla Credits

```sql
-- Fecha programada para entrega
scheduled_delivery_date DATETIME NULL

-- Usuario que aprobó el crédito
approved_by BIGINT UNSIGNED NULL
approved_at DATETIME NULL

-- Usuario que entregó el crédito
delivered_by BIGINT UNSIGNED NULL  
delivered_at DATETIME NULL

-- Notas del proceso
delivery_notes TEXT NULL
rejection_reason TEXT NULL
```

## 🔧 Flujo de Trabajo

### Creación de Crédito
```php
$credit = Credit::create([
    'client_id' => 1,
    'created_by' => 2, // ID del cobrador
    'amount' => 1000.00,
    'interest_rate' => 20.00,
    'frequency' => 'daily',
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-24',
    'status' => 'pending_approval', // Estado inicial
]);
```

### Aprobación (Manager/Admin)
```php
$credit->approveForDelivery(
    $managerId, 
    Carbon::parse('2025-01-15 10:00'), // Fecha programada
    'Aprobado para entrega el lunes'   // Notas opcionales
);
// Status cambia a: 'waiting_delivery'
```

### Entrega (Cobrador/Manager)
```php
$credit->deliverToClient(
    $cobradorId,
    'Entregado en efectivo a domicilio'
);
// Status cambia a: 'active'
```

### Rechazo (Manager/Admin)
```php
$credit->reject(
    $managerId,
    'Cliente no cumple con requisitos de ingresos'
);
// Status cambia a: 'rejected'
```

## 🌐 Endpoints API Disponibles

### Lista de Espera - Consultas
```http
GET /api/credits/waiting-list/pending-approval
GET /api/credits/waiting-list/waiting-delivery  
GET /api/credits/waiting-list/ready-today
GET /api/credits/waiting-list/overdue-delivery
GET /api/credits/waiting-list/summary
```

### Gestión Individual
```http
POST /api/credits/{credit}/waiting-list/approve
POST /api/credits/{credit}/waiting-list/reject
POST /api/credits/{credit}/waiting-list/deliver
POST /api/credits/{credit}/waiting-list/reschedule
GET  /api/credits/{credit}/waiting-list/status
```

## 📋 Ejemplos de Uso

### 1. Aprobar Crédito para Entrega
```http
POST /api/credits/123/waiting-list/approve
Content-Type: application/json

{
    "scheduled_delivery_date": "2025-01-15 10:00:00",
    "notes": "Entrega programada para el lunes por la mañana"
}
```

**Respuesta:**
```json
{
    "success": true,
    "message": "Crédito aprobado para entrega exitosamente",
    "data": {
        "credit": { /* datos del crédito */ },
        "delivery_status": {
            "status": "waiting_delivery",
            "is_ready_for_delivery": false,
            "days_until_delivery": 8,
            "scheduled_delivery_date": "2025-01-15T10:00:00Z"
        }
    }
}
```

### 2. Entregar Crédito al Cliente
```http
POST /api/credits/123/waiting-list/deliver
Content-Type: application/json

{
    "notes": "Entregado en efectivo en domicilio del cliente"
}
```

### 3. Obtener Créditos Listos para Entrega Hoy
```http
GET /api/credits/waiting-list/ready-today
```

**Respuesta:**
```json
{
    "success": true,
    "data": [
        {
            "id": 123,
            "client": {
                "id": 1,
                "name": "Juan Pérez",
                "phone": "70123456"
            },
            "amount": 1000.00,
            "total_amount": 1200.00,
            "scheduled_delivery_date": "2025-01-15T10:00:00Z",
            "delivery_status": {
                "is_ready_for_delivery": true,
                "days_until_delivery": 0
            }
        }
    ],
    "count": 1
}
```

## 🚀 Comando Automático para Procesamiento

### Ejecución Manual
```bash
php artisan credits:process-scheduled-deliveries --notify
```

### Opciones Disponibles
```bash
# Solo mostrar qué se procesaría (sin cambios)
php artisan credits:process-scheduled-deliveries --dry-run

# Enviar notificaciones para créditos listos
php artisan credits:process-scheduled-deliveries --notify

# Auto-entregar créditos (si está habilitado)
php artisan credits:process-scheduled-deliveries --auto-deliver
```

### Programación Automática (Cron)
```bash
# Ejecutar cada día a las 8:00 AM
0 8 * * * cd /path/to/project && php artisan credits:process-scheduled-deliveries --notify
```

## 📊 Métodos Útiles en el Modelo Credit

### Verificaciones de Estado
```php
$credit->isReadyForDelivery();        // ¿Listo para entregar?
$credit->isOverdueForDelivery();      // ¿Atrasado para entrega?
$credit->getDaysUntilDelivery();      // Días hasta entrega
$credit->getDaysOverdueForDelivery(); // Días de atraso
```

### Consultas Estáticas
```php
Credit::pendingApproval()->get();      // Pendientes de aprobación
Credit::waitingForDelivery()->get();   // En lista de espera
Credit::readyForDeliveryToday();       // Listos hoy
Credit::overdueForDelivery();          // Atrasados
```

### Permisos
```php
Credit::userCanApprove($user);   // ¿Puede aprobar? (manager/admin)
Credit::userCanDeliver($user);   // ¿Puede entregar? (cobrador/manager/admin)
```

## 🔔 Notificaciones en Tiempo Real

### WebSocket Events
- `credit.waiting.list.update` - Cuando cambia estado de un crédito
- Canales: `waiting-list`, `user.{userId}`, `cobrador.{cobradorId}`

### Datos del Evento
```json
{
    "credit_id": 123,
    "client_name": "Juan Pérez",
    "action": "approved", // approved, rejected, delivered, rescheduled
    "status": "waiting_delivery",
    "scheduled_delivery_date": "2025-01-15T10:00:00Z",
    "delivery_status": { /* info completa */ },
    "user": {
        "id": 2,
        "name": "Manager López"
    },
    "timestamp": "2025-01-08T14:30:00Z"
}
```

## 🎯 Casos de Uso Típicos

### Escenario 1: Gestión Diaria de un Manager
1. **8:00 AM** - Revisar créditos pendientes de aprobación
2. **8:30 AM** - Aprobar créditos válidos y programar entregas
3. **Durante el día** - Recibir notificaciones de entregas realizadas
4. **6:00 PM** - Revisar créditos atrasados para entrega

### Escenario 2: Trabajo de un Cobrador
1. **Consultar** créditos listos para entrega hoy
2. **Planificar** ruta de entregas
3. **Entregar** créditos a clientes y actualizar estado
4. **Recibir** notificaciones de nuevos créditos aprobados

### Escenario 3: Programación de Entregas
```php
// Crédito para cliente premium - entrega inmediata
$credit->approveForDelivery($managerId, now()->addHour(), 'Cliente premium');

// Crédito normal - entrega en 2 días
$credit->approveForDelivery($managerId, now()->addDays(2), 'Entrega regular');

// Crédito grande - entrega coordinada
$credit->approveForDelivery($managerId, Carbon::parse('2025-01-20 14:00'), 'Coordinar con cliente');
```

## 📈 Beneficios del Sistema

1. **Control de Flujo**: Mejor gestión del proceso de entrega
2. **Programación**: Entregas organizadas por fechas
3. **Trazabilidad**: Historial completo de aprobaciones y entregas
4. **Notificaciones**: Actualizaciones en tiempo real
5. **Automatización**: Procesamiento automático de entregas programadas
6. **Permisos**: Control de acceso por roles de usuario
7. **Reportes**: Estadísticas de lista de espera y entregas

## ⚠️ Consideraciones Importantes

- Los créditos en `pending_approval` no generan pagos hasta ser entregados
- Solo users con rol `manager` o `admin` pueden aprobar/rechazar
- Los cobradores pueden entregar pero no aprobar
- Las fechas de entrega no pueden ser en el pasado
- Se mantiene auditoría completa de cambios de estado

¡Sistema completo y listo para implementar! 🎉
