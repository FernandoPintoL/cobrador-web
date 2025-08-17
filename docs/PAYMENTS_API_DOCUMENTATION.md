# API de Pagos - Documentación Completa

Esta documentación detalla todos los endpoints disponibles en el sistema de pagos, incluyendo los datos requeridos, validaciones, permisos y ejemplos de uso.

## Índice
- [Listado de Pagos](#listado-de-pagos)
- [Crear Pago](#crear-pago)
- [Mostrar Pago](#mostrar-pago)
- [Actualizar Pago](#actualizar-pago)
- [Eliminar Pago](#eliminar-pago)
- [Pagos por Crédito](#pagos-por-crédito)
- [Pagos por Cobrador](#pagos-por-cobrador)
- [Estadísticas del Cobrador](#estadísticas-del-cobrador)
- [Pagos Recientes](#pagos-recientes)
- [Resumen del Día](#resumen-del-día)

---

## Listado de Pagos

**Endpoint:** `GET /api/payments`

**Descripción:** Obtiene una lista paginada de pagos con filtros opcionales.

### Permisos
- **Cobrador:** Solo puede ver sus propios pagos
- **Manager:** Solo puede ver pagos de sus cobradores asignados
- **Admin:** Puede ver todos los pagos

### Parámetros de Consulta (Opcionales)
| Parámetro | Tipo | Descripción | Ejemplo |
|-----------|------|-------------|---------|
| `credit_id` | integer | ID del crédito específico | `credit_id=123` |
| `received_by` | integer | ID del usuario que recibió el pago | `received_by=5` |
| `payment_method` | string | Método de pago (cash, transfer, check, other) | `payment_method=cash` |
| `date_from` | date | Fecha de inicio (formato: Y-m-d) | `date_from=2024-01-01` |
| `date_to` | date | Fecha de fin (formato: Y-m-d) | `date_to=2024-12-31` |
| `amount_min` | decimal | Monto mínimo | `amount_min=100.00` |
| `amount_max` | decimal | Monto máximo | `amount_max=5000.00` |
| `page` | integer | Número de página | `page=2` |

### Ejemplo de Solicitud
