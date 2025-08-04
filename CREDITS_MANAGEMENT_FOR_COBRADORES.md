# API de Gestión de Créditos para Cobradores

## Resumen de Funcionalidades Implementadas

Se han implementado las siguientes mejoras al sistema de gestión de créditos para permitir que los cobradores gestionen los créditos de sus clientes asignados:

### 🔐 **Control de Acceso por Roles**

#### **Para Cobradores:**
- Solo pueden ver, crear, actualizar y eliminar créditos de clientes que tienen asignados
- No pueden acceder a créditos de clientes asignados a otros cobradores
- Pueden ver sus propias estadísticas de créditos

#### **Para Administradores y Managers:**
- Tienen acceso completo a todos los créditos
- Pueden filtrar créditos por cobrador específico
- Pueden ver estadísticas de cualquier cobrador

### 📋 **Endpoints Mejorados**

#### **1. GET /api/credits** 
- **Funcionalidad mejorada:** Los cobradores solo ven créditos de sus clientes asignados
- **Nuevos filtros:**
  - `cobrador_id`: Filtrar por cobrador específico (solo para admins/managers)
  - Mantiene filtros existentes: `client_id`, `status`, `search`

#### **2. POST /api/credits**
- **Validación de permisos:** Cobradores solo pueden crear créditos para sus clientes asignados
- **Campo agregado:** `created_by` se llena automáticamente con el ID del usuario que crea el crédito

#### **3. GET /api/credits/{credit}**
- **Control de acceso:** Cobradores solo pueden ver créditos de sus clientes asignados

#### **4. PUT/PATCH /api/credits/{credit}**
- **Control de acceso:** Cobradores solo pueden actualizar créditos de sus clientes asignados
- **Validación adicional:** Si se cambia el cliente, verifica que el nuevo cliente también esté asignado al cobrador

#### **5. DELETE /api/credits/{credit}**
- **Control de acceso:** Cobradores solo pueden eliminar créditos de sus clientes asignados

#### **6. GET /api/credits/client/{client}**
- **Control de acceso mejorado:** Cobradores solo pueden ver créditos de sus clientes asignados
- **Validación:** Verifica que el usuario especificado sea realmente un cliente

### 🆕 **Nuevos Endpoints**

#### **7. GET /api/credits/cobrador/{cobrador}**
- **Descripción:** Obtiene todos los créditos de un cobrador específico
- **Acceso:** Solo admins y managers
- **Parámetros de consulta:**
  - `status`: Filtrar por estado del crédito
  - `search`: Buscar por nombre del cliente
  - `per_page`: Número de resultados por página
- **Respuesta:** Lista paginada de créditos con información del cliente, pagos y creador

#### **8. GET /api/credits/cobrador/{cobrador}/stats**
- **Descripción:** Obtiene estadísticas de créditos de un cobrador
- **Acceso:** 
  - Cobradores pueden ver solo sus propias estadísticas
  - Admins y managers pueden ver estadísticas de cualquier cobrador
- **Respuesta:**
  ```json
  {
    "total_credits": 15,
    "active_credits": 8,
    "completed_credits": 5,
    "defaulted_credits": 2,
    "total_amount": "50000.00",
    "total_balance": "25000.00"
  }
  ```

#### **9. GET /api/credits-requiring-attention**
- **Descripción:** Obtiene créditos que requieren atención (vencidos o próximos a vencer)
- **Acceso:** 
  - Cobradores ven solo créditos de sus clientes asignados
  - Admins y managers ven todos los créditos
- **Criterios de "requiere atención":**
  - Créditos vencidos (fecha de fin pasada)
  - Créditos que vencen en los próximos 7 días
- **Parámetros de consulta:**
  - `per_page`: Número de resultados por página

### 🔍 **Validaciones y Seguridad**

1. **Verificación de roles:** Todos los endpoints verifican que los usuarios tengan los roles apropiados
2. **Verificación de asignación:** Los cobradores solo pueden acceder a recursos de sus clientes asignados
3. **Validación de tipos de usuario:** Se verifica que los clientes sean realmente clientes y los cobradores sean cobradores
4. **Mensajes de error descriptivos:** Respuestas claras cuando se deniega el acceso

### 📊 **Casos de Uso Típicos**

#### **Para un Cobrador:**
```bash
# Ver todos mis créditos
GET /api/credits

# Crear un crédito para uno de mis clientes
POST /api/credits
{
  "client_id": 123,
  "amount": 5000,
  "balance": 5000,
  "frequency": "monthly",
  "start_date": "2025-08-03",
  "end_date": "2025-12-03"
}

# Ver créditos que requieren mi atención
GET /api/credits-requiring-attention

# Ver mis estadísticas
GET /api/credits/cobrador/456/stats  # donde 456 es mi ID

# Ver créditos de un cliente específico
GET /api/credits/client/123
```

#### **Para un Manager o Admin:**
```bash
# Ver todos los créditos
GET /api/credits

# Ver créditos de un cobrador específico
GET /api/credits/cobrador/456

# Ver estadísticas de un cobrador
GET /api/credits/cobrador/456/stats

# Filtrar créditos por cobrador
GET /api/credits?cobrador_id=456
```

### 🚀 **Próximos Pasos Sugeridos**

1. **✅ Notificaciones WebSocket:** Sistema de notificaciones en tiempo real implementado (Ver `WEBSOCKETS_IMPLEMENTATION_GUIDE.md`)
2. **Reportes:** Generar reportes de rendimiento por cobrador
3. **Dashboard:** Crear un dashboard específico para cobradores con sus métricas principales
4. **Gestión de pagos:** Mejorar la gestión de pagos con las mismas restricciones por rol
5. **Auditoría:** Implementar logs de auditoría para cambios en créditos

### ✅ **Estado de Implementación**

- ✅ Control de acceso por roles implementado
- ✅ Endpoints básicos mejorados con validaciones
- ✅ Nuevos endpoints específicos para cobradores creados
- ✅ Rutas agregadas y registradas
- ✅ Validaciones de seguridad implementadas
- ✅ Documentación creada
- ✅ **Sistema WebSocket preparado para notificaciones en tiempo real**

### 🔔 **Notificaciones Implementadas**

El sistema ahora incluye eventos de WebSocket para:
- **Créditos que requieren atención**: Notifica automáticamente cuando un crédito está próximo a vencer
- **Estructura base**: Para pagos recibidos, asignación de clientes, etc.
- **Canales seguros**: Cada cobrador recibe solo notificaciones de sus clientes asignados

Ver `WEBSOCKETS_IMPLEMENTATION_GUIDE.md` para implementación completa.

El sistema está listo para que los cobradores gestionen los créditos de sus clientes asignados de manera segura y eficiente, con notificaciones en tiempo real.
