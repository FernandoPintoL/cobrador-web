import { io } from 'socket.io-client';

class WebSocketManager {
    constructor() {
        this.socket = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        this.eventHandlers = new Map();
        this.currentUser = null;
    }

    /**
     * Conectar al servidor WebSocket
     */
    connect(serverUrl = 'http://localhost:3001') {
        if (this.socket) {
            this.disconnect();
        }

        this.socket = io(serverUrl, {
            reconnection: true,
            reconnectionAttempts: this.maxReconnectAttempts,
            reconnectionDelay: this.reconnectDelay,
            timeout: 5000,
        });

        this.setupEventListeners();
        return this;
    }

    /**
     * Configurar event listeners básicos
     */
    setupEventListeners() {
        this.socket.on('connect', () => {
            console.log('✅ Conectado al servidor WebSocket');
            this.isConnected = true;
            this.reconnectAttempts = 0;
            this.emit('websocket:connected');
        });

        this.socket.on('disconnect', (reason) => {
            console.log(`❌ Desconectado del servidor WebSocket: ${reason}`);
            this.isConnected = false;
            this.emit('websocket:disconnected', reason);
        });

        this.socket.on('connect_error', (error) => {
            console.error('❌ Error de conexión WebSocket:', error);
            this.reconnectAttempts++;
            this.emit('websocket:error', error);
        });

        this.socket.on('authenticated', (data) => {
            console.log('✅ Autenticado en WebSocket:', data);
            this.emit('websocket:authenticated', data);
        });

        this.socket.on('authentication_error', (data) => {
            console.error('❌ Error de autenticación:', data);
            this.emit('websocket:auth_error', data);
        });

        // Eventos específicos de la aplicación
        this.socket.on('new_credit_notification', (notification) => {
            this.emit('credit:new', notification);
        });

        this.socket.on('payment_updated', (payment) => {
            this.emit('payment:updated', payment);
        });

        this.socket.on('route_updated', (route) => {
            this.emit('route:updated', route);
        });

        this.socket.on('credit_attention_required', (notification) => {
            this.emit('credit:attention', notification);
        });

        this.socket.on('new_message', (message) => {
            this.emit('message:received', message);
        });

        this.socket.on('cobrador_location_update', (location) => {
            this.emit('location:updated', location);
        });

        this.socket.on('user_connected', (user) => {
            this.emit('user:connected', user);
        });

        this.socket.on('user_disconnected', (user) => {
            this.emit('user:disconnected', user);
        });
    }

    /**
     * Autenticar usuario
     */
    authenticate(user) {
        if (!this.isConnected || !this.socket) {
            console.warn('No hay conexión WebSocket activa');
            return false;
        }

        this.currentUser = user;
        this.socket.emit('authenticate', {
            userId: user.id,
            userType: user.type || user.role,
            userName: user.name
        });

        return true;
    }

    /**
     * Desconectar
     */
    disconnect() {
        if (this.socket) {
            this.socket.disconnect();
            this.socket = null;
        }
        this.isConnected = false;
        this.currentUser = null;
    }

    /**
     * Enviar notificación de crédito
     */
    sendCreditNotification(targetUserId, notification, userType = null) {
        if (!this.isConnected) return false;

        this.socket.emit('credit_notification', {
            targetUserId,
            userType,
            notification
        });

        return true;
    }

    /**
     * Actualizar pago
     */
    updatePayment(cobradorId, clientId, payment) {
        if (!this.isConnected) return false;

        this.socket.emit('payment_update', {
            cobradorId,
            clientId,
            payment
        });

        return true;
    }

    /**
     * Actualizar ubicación (solo cobradores)
     */
    updateLocation(latitude, longitude) {
        if (!this.isConnected || !this.currentUser) return false;

        if (this.currentUser.type !== 'cobrador' && this.currentUser.role !== 'cobrador') {
            console.warn('Solo los cobradores pueden actualizar su ubicación');
            return false;
        }

        this.socket.emit('location_update', {
            latitude,
            longitude
        });

        return true;
    }

    /**
     * Enviar mensaje
     */
    sendMessage(recipientId, message) {
        if (!this.isConnected || !this.currentUser) return false;

        this.socket.emit('send_message', {
            recipientId,
            message,
            senderId: this.currentUser.id
        });

        return true;
    }

    /**
     * Notificar actualización de ruta
     */
    notifyRouteUpdate(cobradorId, routeUpdate) {
        if (!this.isConnected) return false;

        this.socket.emit('route_notification', {
            cobradorId,
            routeUpdate
        });

        return true;
    }

    /**
     * Registrar handler para eventos
     */
    on(event, handler) {
        if (!this.eventHandlers.has(event)) {
            this.eventHandlers.set(event, []);
        }
        this.eventHandlers.get(event).push(handler);
        return this;
    }

    /**
     * Desregistrar handler
     */
    off(event, handler) {
        if (this.eventHandlers.has(event)) {
            const handlers = this.eventHandlers.get(event);
            const index = handlers.indexOf(handler);
            if (index > -1) {
                handlers.splice(index, 1);
            }
        }
        return this;
    }

    /**
     * Emitir evento interno
     */
    emit(event, data = null) {
        if (this.eventHandlers.has(event)) {
            this.eventHandlers.get(event).forEach(handler => {
                try {
                    handler(data);
                } catch (error) {
                    console.error(`Error en handler de evento ${event}:`, error);
                }
            });
        }
    }

    /**
     * Obtener estado de conexión
     */
    getConnectionStatus() {
        return {
            isConnected: this.isConnected,
            reconnectAttempts: this.reconnectAttempts,
            currentUser: this.currentUser,
            socketId: this.socket?.id || null
        };
    }
}

// Composable para Vue 3
export function useWebSocket() {
    const wsManager = new WebSocketManager();

    return {
        connect: (url) => wsManager.connect(url),
        disconnect: () => wsManager.disconnect(),
        authenticate: (user) => wsManager.authenticate(user),
        sendCreditNotification: (targetUserId, notification, userType) =>
            wsManager.sendCreditNotification(targetUserId, notification, userType),
        updatePayment: (cobradorId, clientId, payment) =>
            wsManager.updatePayment(cobradorId, clientId, payment),
        updateLocation: (lat, lng) => wsManager.updateLocation(lat, lng),
        sendMessage: (recipientId, message) => wsManager.sendMessage(recipientId, message),
        notifyRouteUpdate: (cobradorId, route) => wsManager.notifyRouteUpdate(cobradorId, route),
        on: (event, handler) => wsManager.on(event, handler),
        off: (event, handler) => wsManager.off(event, handler),
        getStatus: () => wsManager.getConnectionStatus(),
        isConnected: () => wsManager.isConnected
    };
}

// Instancia global (opcional)
export const webSocketManager = new WebSocketManager();

export default WebSocketManager;
