# Seeders de Créditos y Pagos

Este archivo documenta los seeders disponibles para generar datos de prueba de créditos y pagos en la aplicación.

## 📋 Seeders Disponibles

### 1. **CreditsAndPaymentsSeeder** (Automático)
- **Descripción**: Se ejecuta automáticamente cuando ejecutas `php artisan db:seed`
- **Qué hace**:
  - Crea 5 créditos activos con diferentes estados de pagos
  - Crea 5 clientes de ejemplo si no existen
  - Asigna pagos a cada crédito según su progreso
- **Ubicación**: `database/seeders/CreditsAndPaymentsSeeder.php`

### 2. **SimpleCreditsPaymentsSeeder** (Manual)
- **Descripción**: Se ejecuta de forma independiente
- **Qué hace**:
  - Crea 5 créditos con pagos usando usuarios existentes
  - Útil si ya tienes la BD configurada y solo quieres datos de prueba
  - Crea clientes adicionales si faltan
- **Ubicación**: `database/seeders/SimpleCreditsPaymentsSeeder.php`

### 3. **WaitingListCreditsSeeder** (Existente)
- **Descripción**: Crea créditos en lista de espera
- **Estados**: Pendientes, en espera, listos para entrega, atrasados, rechazados
- **Ubicación**: `database/seeders/WaitingListCreditsSeeder.php`

## 🚀 Cómo Usar

### Opción 1: Ejecutar Todo (Recomendado para nuevo proyecto)
```bash
php artisan db:seed
```
Esto ejecuta:
- RolePermissionSeeder (permisos y roles)
- Crea usuarios por defecto (admin, manager, cobrador)
- Crea tasa de interés
- **CreditsAndPaymentsSeeder** (5 créditos con pagos)

### Opción 2: Ejecutar solo créditos y pagos (Base de datos existente)
```bash
php artisan db:seed --class=SimpleCreditsPaymentsSeeder
```

### Opción 3: Ejecutar seeder específico
```bash
php artisan db:seed --class=CreditsAndPaymentsSeeder
```

### Opción 4: Ejecutar lista de espera (para más datos)
```bash
php artisan db:seed --class=WaitingListCreditsSeeder
```

## 📊 Créditos Generados

### Crédito 1: Completamente Pagado
- **Monto**: 1500 Bs
- **Cuotas**: 5 semanales
- **Interés**: 20%
- **Monto Total**: 1800 Bs
- **Cuota**: 360 Bs
- **Pagos**: ✅ 5/5 (100%)
- **Entregado**: Hace 35 días
- **Uso**: Ver reportes con créditos finalizados

### Crédito 2: Parcialmente Pagado (50%)
- **Monto**: 2000 Bs
- **Cuotas**: 8 semanales
- **Interés**: 20%
- **Monto Total**: 2400 Bs
- **Cuota**: 300 Bs
- **Pagos**: ✅ 4/8 (50%)
- **Entregado**: Hace 28 días
- **Uso**: Ver reportes con pagos parciales

### Crédito 3: Recién Entregado (Sin Pagos)
- **Monto**: 1200 Bs
- **Cuotas**: 6 quincenales
- **Interés**: 20%
- **Monto Total**: 1440 Bs
- **Cuota**: 240 Bs
- **Pagos**: ❌ 0/6 (0%)
- **Entregado**: Hace 5 días
- **Uso**: Ver reportes con créditos nuevos

### Crédito 4: En Progreso (60%)
- **Monto**: 2500 Bs
- **Cuotas**: 10 semanales
- **Interés**: 20%
- **Monto Total**: 3000 Bs
- **Cuota**: 300 Bs
- **Pagos**: ✅ 6/10 (60%)
- **Entregado**: Hace 21 días
- **Uso**: Ver reportes en progreso normal

### Crédito 5: Con Atrasos (30%)
- **Monto**: 1800 Bs
- **Cuotas**: 9 quincenales
- **Interés**: 20%
- **Monto Total**: 2160 Bs
- **Cuota**: 240 Bs
- **Pagos**: ✅ 3/9 (30%)
- **Entregado**: Hace 42 días
- **Uso**: Ver reportes con créditos atrasados

## 💾 Datos Específicos Generados

### Tablas Afectadas
- `credits` - Tabla principal de créditos
- `payments` - Tabla de pagos
- `users` - Usuarios (clientes, si se necesitan)
- `interest_rates` - Tasas de interés

### Relaciones
```
Cliente (User)
  └── Crédito (Credit)
       └── Pagos (Payments) x5-10
```

### Estados de Créditos
- `pending_approval` - Esperando aprobación
- `waiting_delivery` - Aprobado, esperando entrega
- `active` - Activo, generando pagos
- `completed` - Completado
- `defaulted` - Vencido
- `rejected` - Rechazado

### Estados de Pagos
- `completed` - Completado
- `pending` - Pendiente
- `failed` - Fallido
- `cancelled` - Cancelado
- `partial` - Parcial

## 🎯 Casos de Uso para Reportes

### 1. Reporte de Créditos Activos
Usarás los 5 créditos con estado 'active'

### 2. Reporte de Pagos
Verás los pagos distribuidos según el progreso de cada crédito

### 3. Reporte de Mora/Atrasos
El Crédito 5 tiene menos progreso (30%) respecto a días transcurridos

### 4. Reporte de Saldos
Cada crédito tiene diferente balance pendiente

### 5. Reporte de Cobradores
Los pagos están asociados al cobrador

## 🔄 Limpiar Datos

Si necesitas empezar de cero:

```bash
# Resetear todo (cuidado: elimina todos los datos)
php artisan migrate:fresh --seed

# Resetear sin seeders
php artisan migrate:fresh

# Resetear y ejecutar solo el seeder simple
php artisan migrate:fresh --seed --class=SimpleCreditsPaymentsSeeder
```

## 📝 Personalizar Seeders

### Aumentar cantidad de créditos
Edita el archivo seeder y agrega más configuraciones al array `$creditConfigs`:

```php
[
    'name' => 'Tu Crédito',
    'amount' => 1000.00,
    'installments' => 4,
    'frequency' => 'weekly',
    'days_ago' => 10,
    'paid_count' => 2,
],
```

### Cambiar montos o frecuencias
Modifica los valores directamente en los archivos seeder

### Usar otros clientes
Los seeders automáticamente usan los clientes existentes

## ❓ Preguntas Frecuentes

**P: ¿Puedo ejecutar los seeders varias veces?**
R: Sí, pero crearán más datos duplicados. Usa `migrate:fresh` primero si quieres limpiar.

**P: ¿Los pagos tienen fechas realistas?**
R: Sí, cada pago está fechado progresivamente según la frecuencia (semanal, quincenal, etc.)

**P: ¿Los totales se calculan automáticamente?**
R: Sí, el modelo Credit calcula automáticamente:
- Monto total con interés
- Monto por cuota
- Balance pendiente

**P: ¿Puedo modificar los datos después de seedear?**
R: Claro, son datos normales en la BD. Úsalos como base y ajusta según necesites.

## 🚀 Próximos Pasos

1. Ejecuta el seeder: `php artisan db:seed`
2. Ve a tu panel de reportes
3. Selecciona un cliente y verás los 5 créditos
4. Visualiza los pagos en diferentes estados
5. Genera reportes para probar

---

**Última actualización**: 2025-10-26
