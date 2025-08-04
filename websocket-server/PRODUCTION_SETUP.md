# 🚀 Configuración de Producción - WebSocket Cobrador

## 📋 Variables de Entorno para Producción

### Archivo .env.production
```env
# Servidor
WEBSOCKET_PORT=3001
NODE_ENV=production

# Dominio de producción
CLIENT_URL=https://cobrador-app.com
SERVER_DOMAIN=wss://api.cobrador-app.com

# Orígenes permitidos (IMPORTANTE: Especificar dominios exactos en producción)
ALLOWED_ORIGINS=https://cobrador-app.com,https://admin.cobrador-app.com,capacitor://localhost

# SSL/TLS (si se usa HTTPS)
SSL_CERT_PATH=/path/to/certificate.crt
SSL_KEY_PATH=/path/to/private.key

# Logs
LOG_LEVEL=warn

# Base de datos (si es necesaria)
DB_HOST=prod-db-server.com
DB_PORT=5432
DB_NAME=cobrador_prod
DB_USER=cobrador_user
DB_PASS=secure_password

# JWT (si se usa autenticación)
JWT_SECRET=your-super-secure-production-secret-key-here
```

## 🌐 Configuración con SSL/HTTPS

### Modificar server.js para HTTPS:
```javascript
const https = require('https');
const fs = require('fs');

// SSL Configuration
let server;
if (process.env.SSL_CERT_PATH && process.env.SSL_KEY_PATH) {
    const options = {
        key: fs.readFileSync(process.env.SSL_KEY_PATH),
        cert: fs.readFileSync(process.env.SSL_CERT_PATH)
    };
    server = https.createServer(options, app);
    console.log('🔒 Servidor HTTPS configurado');
} else {
    server = http.createServer(app);
    console.log('⚠️  Servidor HTTP (desarrollo)');
}
```

## 🐳 Despliegue con Docker

### Dockerfile
```dockerfile
FROM node:18-alpine

# Crear directorio de trabajo
WORKDIR /app

# Copiar archivos de configuración
COPY package*.json ./

# Instalar dependencias
RUN npm ci --only=production

# Copiar código fuente
COPY . .

# Crear usuario no-root
RUN addgroup -g 1001 -S nodejs
RUN adduser -S websocket -u 1001

# Cambiar permisos
RUN chown -R websocket:nodejs /app
USER websocket

# Exponer puerto
EXPOSE 3001

# Comando de inicio
CMD ["npm", "start"]
```

### docker-compose.yml
```yaml
version: '3.8'

services:
  websocket-server:
    build: .
    ports:
      - "3001:3001"
    environment:
      - NODE_ENV=production
      - WEBSOCKET_PORT=3001
      - CLIENT_URL=https://cobrador-app.com
      - ALLOWED_ORIGINS=https://cobrador-app.com,https://admin.cobrador-app.com
    volumes:
      - ./logs:/app/logs
    restart: unless-stopped
    networks:
      - cobrador-network

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
      - ./ssl:/etc/nginx/ssl
    depends_on:
      - websocket-server
    networks:
      - cobrador-network

networks:
  cobrador-network:
    driver: bridge
```

## 🔧 Configuración Nginx (Proxy Reverso)

### nginx.conf
```nginx
events {
    worker_connections 1024;
}

http {
    upstream websocket_backend {
        server websocket-server:3001;
    }

    # HTTP -> HTTPS redirect
    server {
        listen 80;
        server_name api.cobrador-app.com;
        return 301 https://$server_name$request_uri;
    }

    # WebSocket HTTPS
    server {
        listen 443 ssl http2;
        server_name api.cobrador-app.com;

        ssl_certificate /etc/nginx/ssl/certificate.crt;
        ssl_certificate_key /etc/nginx/ssl/private.key;
        ssl_protocols TLSv1.2 TLSv1.3;
        ssl_ciphers HIGH:!aNULL:!MD5;

        location / {
            proxy_pass http://websocket_backend;
            proxy_http_version 1.1;
            
            # Headers para WebSocket
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "upgrade";
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            
            # Timeouts
            proxy_read_timeout 86400;
            proxy_send_timeout 86400;
        }
    }
}
```

## 🔒 Configuración de Seguridad

### 1. Firewall (Ubuntu/Debian)
```bash
# Permitir solo puertos necesarios
sudo ufw allow 22    # SSH
sudo ufw allow 80    # HTTP
sudo ufw allow 443   # HTTPS
sudo ufw allow 3001  # WebSocket (solo si no se usa proxy)
sudo ufw enable
```

### 2. Rate Limiting
```javascript
const rateLimit = require('express-rate-limit');

const limiter = rateLimit({
    windowMs: 15 * 60 * 1000, // 15 minutos
    max: 100, // máximo 100 requests por ventana
    message: 'Demasiadas peticiones desde esta IP'
});

app.use('/notify', limiter);
```

### 3. Validación de JWT (si se usa)
```javascript
const jwt = require('jsonwebtoken');

const verifyToken = (token) => {
    try {
        return jwt.verify(token, process.env.JWT_SECRET);
    } catch (error) {
        return null;
    }
};

// Usar en autenticación
socket.on('authenticate', (data) => {
    if (data.token && verifyToken(data.token)) {
        // Autenticación válida
    } else {
        socket.emit('authentication_error', { message: 'Token inválido' });
    }
});
```

## 📊 Monitoreo y Logs

### PM2 con monitoreo
```bash
# Instalar PM2
npm install -g pm2

# Configurar aplicación
pm2 start server.js --name "cobrador-websocket" --watch --ignore-watch="logs"

# Configurar logs
pm2 install pm2-logrotate
pm2 set pm2-logrotate:max_size 10M
pm2 set pm2-logrotate:retain 30
pm2 set pm2-logrotate:compress true

# Monitoreo
pm2 monit

# Auto-inicio en boot
pm2 startup
pm2 save
```

### ecosystem.config.js (PM2)
```javascript
module.exports = {
    apps: [{
        name: 'cobrador-websocket',
        script: 'server.js',
        instances: 1,
        autorestart: true,
        watch: false,
        max_memory_restart: '1G',
        env: {
            NODE_ENV: 'production',
            WEBSOCKET_PORT: 3001
        },
        error_file: './logs/err.log',
        out_file: './logs/out.log',
        log_file: './logs/combined.log',
        time: true
    }]
};
```

## 🌍 URLs para Flutter en Producción

### Configuración Flutter (producción)
```dart
class WebSocketConfig {
    static const bool isProduction = bool.fromEnvironment('dart.vm.product');
    
    static String get serverUrl {
        if (isProduction) {
            return 'wss://api.cobrador-app.com'; // HTTPS WebSocket
        } else {
            // Desarrollo
            return 'http://192.168.1.100:3001'; // Tu IP local
        }
    }
    
    static Map<String, String> get headers {
        return {
            'Origin': isProduction ? 'https://cobrador-app.com' : 'http://localhost:3000',
        };
    }
}
```

### Uso en Flutter
```dart
await _wsManager.connect(
    WebSocketConfig.serverUrl,
    options: IO.OptionBuilder()
        .setExtraHeaders(WebSocketConfig.headers)
        .build()
);
```

## 🚀 Script de Despliegue

### deploy.sh
```bash
#!/bin/bash

echo "🚀 Desplegando WebSocket Server..."

# Actualizar código
git pull origin main

# Instalar dependencias
npm ci --only=production

# Ejecutar tests (si existen)
# npm test

# Reiniciar con PM2
pm2 restart cobrador-websocket

# Verificar estado
pm2 status

echo "✅ Despliegue completado"
echo "🌐 Servidor disponible en: https://api.cobrador-app.com"
```

## 📋 Checklist de Producción

- [ ] Variables de entorno configuradas
- [ ] SSL/TLS configurado
- [ ] CORS configurado con dominios específicos
- [ ] Rate limiting implementado
- [ ] Logs configurados
- [ ] Monitoreo con PM2
- [ ] Firewall configurado
- [ ] Backups automatizados
- [ ] Pruebas de carga realizadas
- [ ] Documentación actualizada
- [ ] URLs de Flutter actualizadas

## 🔧 Troubleshooting Producción

### Verificar estado del servidor
```bash
# Estado de PM2
pm2 status

# Logs en tiempo real
pm2 logs cobrador-websocket

# Reiniciar si es necesario
pm2 restart cobrador-websocket

# Verificar puertos
netstat -tulpn | grep 3001

# Verificar conexiones activas
curl https://api.cobrador-app.com/health
```

### Logs importantes
```bash
# Logs de aplicación
tail -f logs/combined.log

# Logs de Nginx
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# Logs del sistema
journalctl -u pm2-websocket
```
