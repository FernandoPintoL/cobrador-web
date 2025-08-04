<template>
    <div class="websocket-status">
        <!-- Indicador de estado de conexión -->
        <div class="status-indicator" :class="statusClass">
            <span class="status-dot"></span>
            {{ statusText }}
        </div>

        <!-- Contador de notificaciones -->
        <div v-if="unreadNotifications > 0" class="notification-badge">
            {{ unreadNotifications }}
        </div>

        <!-- Lista de notificaciones recientes -->
        <div v-if="showNotifications" class="notifications-panel">
            <h3>Notificaciones Recientes</h3>
            <div v-for="notification in recentNotifications" :key="notification.id" class="notification-item"
                :class="`notification-${notification.type}`">
                <div class="notification-header">
                    <strong>{{ notification.title }}</strong>
                    <span class="notification-time">{{ formatTime(notification.timestamp) }}</span>
                </div>
                <div class="notification-message">{{ notification.message }}</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useWebSocket } from './WebSocketManager.js';

// Props
const props = defineProps({
    showNotifications: {
        type: Boolean,
        default: false
    }
});

// Composables
const page = usePage();
const ws = useWebSocket();

// Estado reactivo
const isConnected = ref(false);
const reconnectAttempts = ref(0);
const recentNotifications = ref([]);
const unreadNotifications = ref(0);

// Computed
const statusClass = computed(() => ({
    'status-connected': isConnected.value,
    'status-disconnected': !isConnected.value,
    'status-reconnecting': reconnectAttempts.value > 0 && !isConnected.value
}));

const statusText = computed(() => {
    if (isConnected.value) return 'Conectado';
    if (reconnectAttempts.value > 0) return 'Reconectando...';
    return 'Desconectado';
});

// Métodos
const addNotification = (notification) => {
    const newNotification = {
        ...notification,
        id: Date.now() + Math.random(),
        timestamp: new Date(),
        read: false
    };

    recentNotifications.value.unshift(newNotification);
    unreadNotifications.value++;

    // Mantener solo las últimas 20 notificaciones
    if (recentNotifications.value.length > 20) {
        recentNotifications.value = recentNotifications.value.slice(0, 20);
    }

    // Mostrar notificación del navegador si está permitido
    showBrowserNotification(notification);
};

const showBrowserNotification = (notification) => {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(notification.title, {
            body: notification.message,
            icon: '/favicon.ico',
            tag: notification.type
        });
    }
};

const requestNotificationPermission = async () => {
    if ('Notification' in window) {
        const permission = await Notification.requestPermission();
        console.log('Permiso de notificaciones:', permission);
    }
};

const formatTime = (timestamp) => {
    return new Date(timestamp).toLocaleTimeString();
};

const clearNotifications = () => {
    unreadNotifications.value = 0;
    recentNotifications.value.forEach(n => n.read = true);
};

// Lifecycle hooks
onMounted(async () => {
    // Solicitar permisos de notificación
    await requestNotificationPermission();

    // Conectar WebSocket
    const user = page.props.auth?.user;
    if (user) {
        ws.connect(import.meta.env.VITE_WEBSOCKET_URL || 'http://localhost:3001');

        // Event listeners
        ws.on('websocket:connected', () => {
            isConnected.value = true;
            reconnectAttempts.value = 0;

            // Autenticar usuario
            ws.authenticate({
                id: user.id,
                name: user.name,
                type: user.role || 'client'
            });
        });

        ws.on('websocket:disconnected', () => {
            isConnected.value = false;
        });

        ws.on('websocket:error', () => {
            reconnectAttempts.value++;
        });

        ws.on('websocket:authenticated', (data) => {
            console.log('Usuario autenticado en WebSocket:', data);
        });

        // Notificaciones específicas
        ws.on('credit:new', (notification) => {
            addNotification({
                title: 'Nuevo Crédito',
                message: notification.message || 'Se ha asignado un nuevo crédito',
                type: 'credit',
                data: notification
            });
        });

        ws.on('payment:updated', (payment) => {
            addNotification({
                title: 'Pago Actualizado',
                message: `Pago de ${payment.amount} Bs. procesado`,
                type: 'payment',
                data: payment
            });
        });

        ws.on('credit:attention', (notification) => {
            addNotification({
                title: 'Atención Requerida',
                message: notification.message || 'Un crédito requiere tu atención',
                type: 'urgent',
                data: notification
            });
        });

        ws.on('route:updated', (route) => {
            addNotification({
                title: 'Ruta Actualizada',
                message: `Tu ruta del ${route.date} ha sido actualizada`,
                type: 'route',
                data: route
            });
        });

        ws.on('message:received', (message) => {
            addNotification({
                title: 'Nuevo Mensaje',
                message: message.message,
                type: 'message',
                data: message
            });
        });

        ws.on('location:updated', (location) => {
            console.log('Ubicación actualizada:', location);
            // Manejar actualización de ubicación si es necesario
        });
    }
});

onUnmounted(() => {
    ws.disconnect();
});

// Exponer métodos para uso externo
defineExpose({
    clearNotifications,
    sendMessage: ws.sendMessage,
    updateLocation: ws.updateLocation,
    getStatus: ws.getStatus
});
</script>

<style scoped>
.websocket-status {
    position: relative;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
}

.status-connected {
    background-color: #d4edda;
    color: #155724;
}

.status-disconnected {
    background-color: #f8d7da;
    color: #721c24;
}

.status-reconnecting {
    background-color: #fff3cd;
    color: #856404;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: currentColor;
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background-color: #dc3545;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

.notifications-panel {
    position: absolute;
    top: 100%;
    right: 0;
    width: 300px;
    max-height: 400px;
    overflow-y: auto;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 1000;
}

.notifications-panel h3 {
    margin: 0;
    padding: 12px;
    border-bottom: 1px solid #eee;
    font-size: 16px;
}

.notification-item {
    padding: 12px;
    border-bottom: 1px solid #f5f5f5;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.notification-time {
    font-size: 12px;
    color: #666;
}

.notification-message {
    font-size: 14px;
    color: #333;
}

.notification-credit {
    border-left: 4px solid #28a745;
}

.notification-payment {
    border-left: 4px solid #007bff;
}

.notification-urgent {
    border-left: 4px solid #dc3545;
}

.notification-route {
    border-left: 4px solid #ffc107;
}

.notification-message {
    border-left: 4px solid #6c757d;
}
</style>
