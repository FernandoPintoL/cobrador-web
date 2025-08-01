# Sistema de Cobrador - Laravel + React + Flutter

Sistema completo de gestión de cobranzas con roles y permisos usando Spatie Laravel Permission, APIs RESTful para acceso desde Flutter, y frontend en React con Inertia.js.

## 🚀 Características

- **Autenticación**: Sistema de autenticación con Laravel Sanctum
- **Roles y Permisos**: Gestión completa con Spatie Laravel Permission
- **APIs RESTful**: Endpoints completos para acceso desde Flutter
- **Frontend Moderno**: React con TypeScript y Tailwind CSS
- **Gestión de Usuarios**: CRUD completo con asignación de roles
- **Rutas de Cobranza**: Gestión de rutas y asignación de clientes
- **Créditos**: Sistema de créditos con diferentes frecuencias
- **Pagos**: Registro de pagos con geolocalización
- **Balances de Efectivo**: Control de efectivo por cobrador
- **Notificaciones**: Sistema de notificaciones en tiempo real
- **Geolocalización**: Soporte para coordenadas GPS en pagos

## 📋 Requisitos

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL/PostgreSQL
- Laravel 12.x

## 🛠️ Instalación

### 1. Clonar el repositorio
```bash
git clone <url-del-repositorio>
cd cobrador
```

### 2. Instalar dependencias de PHP
```bash
composer install
```

### 3. Instalar dependencias de Node.js
```bash
npm install
```

### 4. Configurar variables de entorno
```bash
cp .env.example .env
```

Editar el archivo `.env` con tu configuración:
```env
APP_NAME="Sistema de Cobrador"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cobrador
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:3000
SESSION_DOMAIN=localhost
```

### 5. Generar clave de aplicación
```bash
php artisan key:generate
```

### 6. Ejecutar migraciones
```bash
php artisan migrate
```

### 7. Ejecutar seeders
```bash
php artisan db:seed
```

### 8. Compilar assets
```bash
npm run build
```

### 9. Iniciar servidor de desarrollo
```bash
php artisan serve
```

En otra terminal:
```bash
npm run dev
```

## 👥 Usuarios por Defecto

Después de ejecutar los seeders, tendrás los siguientes usuarios:

- **Admin**: admin@cobrador.com / password
- **Cobradores**: 5 usuarios de ejemplo con rol 'cobrador'
- **Clientes**: 10 usuarios de ejemplo con rol 'client'

## 🏗️ Estructura del Proyecto

### Backend (Laravel)

```
app/
├── Http/Controllers/Api/     # Controladores API
├── Models/                   # Modelos Eloquent
├── Http/Middleware/          # Middlewares personalizados
└── Providers/               # Service Providers

database/
├── migrations/              # Migraciones de base de datos
└── seeders/                # Seeders para datos iniciales

routes/
└── api.php                 # Rutas API
```

### Frontend (React)

```
resources/js/
├── pages/                   # Páginas de la aplicación
├── components/              # Componentes reutilizables
├── hooks/                   # Custom hooks
└── types/                   # Definiciones de TypeScript
```

## 🔐 Roles y Permisos

### Roles Disponibles

1. **admin**: Acceso completo al sistema
2. **manager**: Gestión de usuarios, rutas, créditos y pagos
3. **cobrador**: Gestión de pagos y balances de efectivo
4. **client**: Visualización de créditos y pagos propios

### Permisos por Rol

- **Admin**: Todos los permisos
- **Manager**: Gestión de usuarios, rutas, créditos, pagos, balances y notificaciones
- **Cobrador**: Visualización de rutas, créditos, gestión de pagos y balances
- **Client**: Visualización de créditos y pagos propios, notificaciones

## 📱 APIs para Flutter

El sistema incluye APIs RESTful completas para integración con Flutter:

### Autenticación
- `POST /api/register` - Registro de usuarios
- `POST /api/login` - Inicio de sesión
- `POST /api/logout` - Cerrar sesión
- `GET /api/me` - Obtener usuario actual

### Gestión de Datos
- **Usuarios**: CRUD completo con roles
- **Rutas**: Gestión de rutas y clientes
- **Créditos**: Sistema de créditos con cuotas
- **Pagos**: Registro con geolocalización
- **Balances**: Control de efectivo
- **Notificaciones**: Sistema de notificaciones

Ver documentación completa en `API_DOCUMENTATION.md`

## 🎨 Frontend

### Componentes Principales

- **Gestión de Usuarios**: CRUD con asignación de roles
- **Gestión de Rutas**: Crear y asignar clientes a rutas
- **Dashboard**: Vista general del sistema
- **Notificaciones**: Sistema de notificaciones en tiempo real

### Tecnologías Frontend

- **React 18** con TypeScript
- **Inertia.js** para SPA
- **Tailwind CSS** para estilos
- **Shadcn/ui** para componentes
- **Lucide React** para iconos

## 🗄️ Base de Datos

### Tablas Principales

- `users` - Usuarios del sistema
- `routes` - Rutas de cobranza
- `client_routes` - Relación muchos a muchos entre clientes y rutas
- `credits` - Créditos otorgados
- `payments` - Pagos realizados
- `cash_balances` - Balances de efectivo
- `notifications` - Notificaciones del sistema

### Relaciones

- Un usuario puede tener múltiples roles
- Un cobrador puede tener múltiples rutas
- Una ruta puede tener múltiples clientes
- Un cliente puede tener múltiples créditos
- Un crédito puede tener múltiples pagos
- Los pagos pueden tener geolocalización

## 🚀 Comandos Útiles

### Desarrollo
```bash
# Ejecutar servidor de desarrollo
php artisan serve

# Compilar assets en desarrollo
npm run dev

# Compilar assets para producción
npm run build
```

### Base de Datos
```bash
# Ejecutar migraciones
php artisan migrate

# Revertir migraciones
php artisan migrate:rollback

# Ejecutar seeders
php artisan db:seed

# Ejecutar seeder específico
php artisan db:seed --class=RolePermissionSeeder
```

### Testing
```bash
# Ejecutar tests
php artisan test

# Ejecutar tests con coverage
php artisan test --coverage
```

## 🔧 Configuración Adicional

### CORS para APIs
El sistema está configurado para permitir acceso desde aplicaciones Flutter. Asegúrate de configurar los dominios permitidos en `config/cors.php`.

### Sanctum Configuration
Para el acceso desde Flutter, configura los dominios permitidos en `config/sanctum.php`:

```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
))),
```

### Geolocalización
El sistema soporta coordenadas GPS en los pagos. Los datos se almacenan como puntos geométricos en la base de datos.

## 📊 Reportes y Analytics

El sistema incluye funcionalidades para:

- Reportes de pagos por período
- Balances de efectivo por cobrador
- Estadísticas de créditos
- Análisis de rutas
- Exportación de datos

## 🔒 Seguridad

- Autenticación con Laravel Sanctum
- Validación de datos en todos los endpoints
- Control de acceso basado en roles
- Protección CSRF
- Sanitización de datos

## 🤝 Contribución

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 📞 Soporte

Para soporte técnico o preguntas sobre el sistema:

- Crear un issue en el repositorio
- Contactar al equipo de desarrollo
- Revisar la documentación de APIs

## 🎯 Roadmap

- [ ] Integración con sistemas de pago
- [ ] Notificaciones push
- [ ] Reportes avanzados
- [ ] Dashboard en tiempo real
- [ ] Integración con mapas
- [ ] Sistema de alertas
- [ ] Backup automático
- [ ] API para reportes 