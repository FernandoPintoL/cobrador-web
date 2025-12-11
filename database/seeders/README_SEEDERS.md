# Guía de Seeders y Datos de Prueba

Esta guía explica cómo usar los seeders y comandos para generar datos de prueba en la aplicación Cobrador.

## 📋 Tabla de Contenidos

- [Seeders Disponibles](#seeders-disponibles)
- [Comandos Artisan](#comandos-artisan)
- [Casos de Uso Comunes](#casos-de-uso-comunes)
- [Estados de Créditos Generados](#estados-de-créditos-generados)

---

## Seeders Disponibles

### 1. `DatabaseSeeder` (Seeder Principal)
Crea la configuración inicial de la aplicación:
- Roles y permisos
- Usuarios admin, manager y cobrador
- Tasa de interés por defecto
- Algunos créditos básicos

```bash
php artisan db:seed
```

### 2. `ComprehensiveReportDataSeeder` ⭐ (Recomendado para reportes)
Genera datos completos en **todos los estados posibles** para probar reportes:
- 2 créditos pendientes de aprobación
- 2 créditos aprobados esperando entrega
- 1 crédito rechazado
- 5 créditos activos (con diferentes niveles de pago)
- 2 créditos en mora grave
- 2 créditos completados
- 1 crédito cancelado

**Total: 15 créditos con ~70 pagos**

```bash
php artisan db:seed --class=ComprehensiveReportDataSeeder
```

### 3. `SimpleCreditsPaymentsSeeder`
Genera 5 créditos simples solo en estado "active" con diferentes niveles de pago.

```bash
php artisan db:seed --class=SimpleCreditsPaymentsSeeder
```

### 4. `CreditsAndPaymentsSeeder`
Similar a SimpleCreditsPaymentsSeeder pero integrado en el flujo del DatabaseSeeder.

---

## Comandos Artisan

### 🔄 `test-data:regenerate` - Regenerar Datos de Prueba

Limpia todos los créditos y pagos y los regenera automáticamente.

#### Uso básico:
```bash
# Con confirmación interactiva
php artisan test-data:regenerate

# Sin confirmación (útil para scripts)
php artisan test-data:regenerate --force

# Mantener los clientes existentes
php artisan test-data:regenerate --keep-clients

# Combinación: sin confirmación y mantener clientes
php artisan test-data:regenerate --force --keep-clients
```

#### Opciones:

| Opción | Descripción |
|--------|-------------|
| `--keep-clients` | Mantiene los clientes de prueba existentes en lugar de eliminarlos |
| `--force` | Ejecuta sin pedir confirmación |

#### Protecciones:

✅ **Usuarios protegidos** (NUNCA se eliminan):
- Admin
- Manager
- Cobrador

⚠️ **Se eliminan** (a menos que uses `--keep-clients`):
- Todos los créditos
- Todos los pagos
- Todos los clientes de prueba

#### Ejemplo de salida:

```
🔄 Regenerador de Datos de Prueba

┌────────────────────────┬──────────────────┬──────────────────────┐
│ Tipo                   │ Cantidad Actual  │ Acción               │
├────────────────────────┼──────────────────┼──────────────────────┤
│ Créditos               │ 15               │ 🗑️  Eliminar todos   │
│ Pagos                  │ 68               │ 🗑️  Eliminar todos   │
│ Clientes               │ 15               │ ✓ Mantener           │
│ Admin/Manager/Cobrador │ 3                │ ✓ Mantener           │
└────────────────────────┴──────────────────┴──────────────────────┘

✅ ¡Datos de prueba regenerados exitosamente!

📈 Estadísticas de datos generados:
...
```

---

## Casos de Uso Comunes

### 🆕 Configuración inicial del sistema
```bash
# 1. Migrar base de datos
php artisan migrate:fresh

# 2. Crear usuarios y configuración básica
php artisan db:seed

# 3. Generar datos completos para reportes
php artisan db:seed --class=ComprehensiveReportDataSeeder
```

### 🔄 Regenerar datos para pruebas de reportes
```bash
# Opción 1: Comando rápido (recomendado)
php artisan test-data:regenerate --keep-clients

# Opción 2: Manual
php artisan db:seed --class=ComprehensiveReportDataSeeder
```

### 🧪 Probar con datos limpios cada vez
```bash
# Resetear TODO y regenerar
php artisan migrate:fresh --seed
php artisan db:seed --class=ComprehensiveReportDataSeeder
```

### 📊 Solo agregar más datos de prueba
```bash
# Agregar 15 créditos adicionales sin eliminar los existentes
php artisan db:seed --class=ComprehensiveReportDataSeeder
```

---

## Estados de Créditos Generados

El `ComprehensiveReportDataSeeder` genera créditos en los siguientes estados:

### 1. **PENDING_APPROVAL** (2 créditos)
- Estado: Esperando aprobación del manager
- Uso: Probar flujo de aprobación
- Reportes afectados: Panel de aprobaciones

### 2. **WAITING_DELIVERY** (2 créditos)
- Estado: Aprobado, esperando entrega física
- Variantes:
  - Entrega programada para hoy (listo para entregar)
  - Entrega programada para mañana
- Uso: Probar lista de espera y entregas programadas
- Reportes afectados: Lista de entregas pendientes

### 3. **REJECTED** (1 crédito)
- Estado: Rechazado por el manager
- Razón: "Cliente no cumple con los requisitos de ingresos mínimos"
- Uso: Probar historial de rechazos

### 4. **ACTIVE** (5 créditos) ⭐ Principal para reportes
Estados variados para probar diferentes escenarios:

| Variante | % Pagado | Descripción | Uso en Reportes |
|----------|----------|-------------|-----------------|
| Al día 100% | 100% | Todas las cuotas esperadas pagadas | Clientes buenos |
| Al día 80% | 80% | La mayoría de cuotas pagadas | Clientes regulares |
| Atraso leve | 60% | Algunas cuotas atrasadas | Alerta temprana |
| Atraso moderado | 40% | Bastantes cuotas atrasadas | Clientes en riesgo |
| Recién entregado | 0% | Sin pagos aún (hace 3 días) | Nuevos créditos |

### 5. **DEFAULTED** (2 créditos) ⚠️ Mora grave
- Estado: En mora grave
- Variantes:
  - Solo 20% pagado (hace 90 días)
  - Solo 10% pagado (hace 120 días)
- Uso: Probar reportes de mora y cartera vencida

### 6. **COMPLETED** (2 créditos) ✅
- Estado: Completamente pagados
- Balance: 0 Bs
- Uso: Probar historial de créditos completados

### 7. **CANCELLED** (1 crédito)
- Estado: Cancelado por acuerdo mutuo
- Pagos: 30% completado antes de la cancelación
- Uso: Probar manejo de créditos cancelados

---

## Reportes Beneficiados

Estos datos de prueba son útiles para los siguientes reportes:

### 📊 Reporte de Cartera
- Créditos activos: 5
- Créditos completados: 2
- Cartera en mora: 2

### 📈 Reporte de Performance
- Clientes al día: 2
- Clientes con atraso leve: 1
- Clientes con atraso moderado: 1
- Clientes en mora grave: 2

### 💰 Reporte de Comisiones
- Pagos completados: ~70 pagos
- Diferentes métodos de pago (efectivo, transferencia, tarjeta)
- Diferentes fechas y montos

### ⚠️ Reporte de Mora
- Créditos vencidos: 2
- Créditos con atrasos: 2
- Créditos al día: 2

### 📅 Reporte de Entregas Pendientes
- Listas para entregar hoy: 1
- Programadas para mañana: 1

### ✅ Reporte de Aprobaciones
- Pendientes de aprobación: 2

---

## Datos Generados

### Usuarios
- **Admin**: admin@cobrador.com / password
- **Manager**: app@manager.com / password
- **Cobrador**: app@cobrador.com / password
- **Clientes**: 15 clientes de prueba

### Créditos
- **Total**: 15 créditos
- **Montos**: Entre 1,200 Bs y 3,500 Bs
- **Frecuencias**: weekly, biweekly
- **Cuotas**: Entre 5 y 15 cuotas

### Pagos
- **Total**: ~70 pagos
- **Métodos**: efectivo, transferencia, tarjeta
- **Estados**: completed, partial

---

## Solución de Problemas

### Error: "No hay usuarios con roles de manager o cobrador"
**Solución**: Ejecuta primero el seeder principal:
```bash
php artisan db:seed
```

### Los datos no aparecen en los reportes
**Verificación**:
```bash
# Verificar créditos creados
php artisan tinker
>>> Credit::count()
>>> Credit::pluck('status', 'id')
```

### Quiero empezar de cero
```bash
php artisan migrate:fresh --seed
php artisan test-data:regenerate --force
```

---

## Tips y Mejores Prácticas

1. **Para desarrollo local**: Usa `test-data:regenerate --keep-clients` frecuentemente
2. **Para demos**: Regenera datos limpios antes de cada demo
3. **Para testing**: Usa `--force` en scripts automatizados
4. **Para producción**: ⚠️ NUNCA uses estos seeders en producción

---

## Contribuir

Si necesitas agregar más estados o escenarios:

1. Edita `ComprehensiveReportDataSeeder.php`
2. Agrega tu nuevo método de creación
3. Llámalo desde el método `run()`
4. Documenta el nuevo estado aquí

---

**Última actualización**: Diciembre 2024
**Versión**: 1.0.0
