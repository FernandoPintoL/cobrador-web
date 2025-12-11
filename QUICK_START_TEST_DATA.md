# 🚀 Guía Rápida: Datos de Prueba para Reportes

## ¿Qué se ha creado?

### ✅ 1. Seeder Completo
**Archivo**: `database/seeders/ComprehensiveReportDataSeeder.php`

Genera **15 créditos** en todos los estados posibles:
- 2 pendientes de aprobación
- 2 esperando entrega
- 1 rechazado
- 5 activos (con diferentes niveles de pago)
- 2 en mora grave
- 2 completados
- 1 cancelado

### ✅ 2. Comando de Regeneración
**Comando**: `php artisan test-data:regenerate`

Limpia y regenera datos de prueba automáticamente.

### ✅ 3. Documentación
**Archivo**: `database/seeders/README_SEEDERS.md`

Guía completa con todos los detalles.

---

## 🎯 Comandos Principales

### Generar datos por primera vez
```bash
# 1. Asegúrate de tener usuarios admin/manager/cobrador
php artisan db:seed

# 2. Genera los datos completos para reportes
php artisan db:seed --class=ComprehensiveReportDataSeeder
```

### Regenerar datos (mantener clientes)
```bash
php artisan test-data:regenerate --keep-clients
```

### Regenerar datos (limpiar todo)
```bash
php artisan test-data:regenerate --force
```

### Empezar desde cero
```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=ComprehensiveReportDataSeeder
```

---

## 📊 Datos Generados - Resumen

### Créditos por Estado

| Estado | Cantidad | Descripción |
|--------|----------|-------------|
| **pending_approval** | 2 | Esperando aprobación |
| **waiting_delivery** | 2 | Aprobados, listos para entregar |
| **rejected** | 1 | Rechazado por requisitos |
| **active** | 5 | Activos con diferentes % de pago |
| **defaulted** | 2 | En mora grave (10-20% pagado) |
| **completed** | 2 | Completamente pagados |
| **cancelled** | 1 | Cancelado por acuerdo |
| **TOTAL** | **15** | |

### Créditos Activos (Detalle)

| Descripción | % Pagado | Uso |
|-------------|----------|-----|
| Al día 100% | 100% | Cliente perfecto |
| Al día 80% | 80% | Cliente bueno |
| Atraso leve | 60% | Alerta temprana |
| Atraso moderado | 40% | Cliente en riesgo |
| Recién entregado | 0% | Nuevo (3 días) |

### Pagos
- **Total**: ~70 pagos registrados
- **Métodos**: Efectivo, Transferencia, Tarjeta
- **Estados**: Completed, Partial

### Clientes
- **Total**: 15 clientes de prueba
- Asignados al cobrador por defecto
- Con diferentes historiales de pago

---

## 🎨 Ejemplos de Uso

### Caso 1: Probar reporte de mora
```bash
# Regenera datos para tener casos de mora frescos
php artisan test-data:regenerate --force

# Verifica los créditos en mora
php artisan tinker
>>> Credit::where('status', 'defaulted')->count()
# Debería retornar: 2
```

### Caso 2: Probar reporte de cartera
```bash
# Genera datos variados
php artisan db:seed --class=ComprehensiveReportDataSeeder

# Los reportes ahora mostrarán:
# - 5 créditos activos
# - 2 créditos completados
# - 2 créditos en mora
```

### Caso 3: Demo para cliente
```bash
# Antes de la demo, regenera datos limpios
php artisan test-data:regenerate --force

# Ahora tienes:
# - Datos consistentes
# - Sin datos antiguos
# - Todos los estados representados
```

---

## 🔍 Verificación

### Ver estadísticas actuales
```bash
php artisan tinker

# Contar créditos por estado
>>> Credit::selectRaw('status, count(*) as total')->groupBy('status')->get()

# Ver total de pagos
>>> Payment::count()

# Ver clientes
>>> User::whereHas('roles', fn($q) => $q->where('name', 'client'))->count()
```

### Ver un crédito de cada tipo
```bash
php artisan tinker

>>> Credit::where('status', 'pending_approval')->first()
>>> Credit::where('status', 'active')->first()
>>> Credit::where('status', 'defaulted')->first()
>>> Credit::where('status', 'completed')->first()
```

---

## 💡 Tips

### ✅ Hacer
- Usa `--keep-clients` si solo quieres regenerar créditos
- Ejecuta antes de cada sprint review o demo
- Usa datos de prueba en desarrollo local

### ❌ No Hacer
- **NUNCA** uses estos comandos en producción
- No ejecutes sin `--keep-clients` si tienes clientes reales
- No asumas que los datos persisten entre migraciones

---

## 📝 Usuarios de Prueba (Creados por `php artisan db:seed`)

| Usuario | Email | Password | Rol |
|---------|-------|----------|-----|
| Administrador | admin@cobrador.com | password | admin |
| Manager | app@manager.com | password | manager |
| App Cobrador | app@cobrador.com | password | cobrador |

---

## 🆘 Problemas Comunes

### "No hay usuarios con roles de manager o cobrador"
```bash
# Solución: Ejecuta el seeder principal primero
php artisan db:seed
```

### Los datos no aparecen en reportes
```bash
# Verifica que se crearon correctamente
php artisan tinker
>>> Credit::count()  # Debería ser > 0
>>> Payment::count() # Debería ser > 0
```

### Quiero datos completamente nuevos
```bash
# Resetea TODO
php artisan migrate:fresh --seed
php artisan db:seed --class=ComprehensiveReportDataSeeder
```

---

## 📚 Más Información

- **Documentación completa**: `database/seeders/README_SEEDERS.md`
- **Código del seeder**: `database/seeders/ComprehensiveReportDataSeeder.php`
- **Código del comando**: `app/Console/Commands/RegenerateTestData.php`

---

**¡Listo para generar datos de prueba! 🎉**

```bash
php artisan test-data:regenerate --keep-clients
```
