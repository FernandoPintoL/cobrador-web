# ⚛️ INTEGRACIÓN WEBSOCKET EN REACT

## 1. Dependencias necesarias

```bash
npm install socket.io-client axios react-query @reduxjs/toolkit react-redux
# o con yarn
yarn add socket.io-client axios react-query @reduxjs/toolkit react-redux
```

## 2. Servicio WebSocket en React

```javascript
// src/services/websocketService.js
import { io } from 'socket.io-client';

class WebSocketService {
  constructor() {
    this.socket = null;
    this.currentUserId = null;
    this.currentUserType = null;
    this.serverUrl = 'http://192.168.5.44:3001';
    
    // Callbacks para diferentes tipos de notificaciones
    this.onPaymentNotification = null;
    this.onCreditNotification = null;
    this.onLocationUpdate = null;
    this.onMessage = null;
    this.onGeneralNotification = null;
  }

  /**
   * Conectar al servidor WebSocket
   */
  connect(userId, userType) {
    try {
      this.currentUserId = userId;
      this.currentUserType = userType;

      this.socket = io(this.serverUrl, {
        transports: ['websocket'],
        autoConnect: true,
        timeout: 5000,
      });

      this.setupEventListeners();
      
      console.log(`Conectando a WebSocket como ${userType} (ID: ${userId})`);
      
    } catch (error) {
      console.error('Error conectando a WebSocket:', error);
    }
  }

  /**
   * Configurar listeners de eventos
   */
  setupEventListeners() {
    this.socket.on('connect', () => {
      console.log('Conectado a WebSocket');
      
      // Autenticar usuario al conectar
      this.socket.emit('authenticate', {
        userId: this.currentUserId,
        userType: this.currentUserType,
      });
    });

    this.socket.on('disconnect', () => {
      console.warn('Desconectado de WebSocket');
    });

    this.socket.on('connect_error', (error) => {
      console.error('Error de conexión WebSocket:', error);
    });

    // Listeners para diferentes tipos de notificaciones
    this.socket.on('payment_notification', (data) => {
      console.log('Notificación de pago recibida:', data);
      this.onPaymentNotification?.(data);
    });

    this.socket.on('credit_notification', (data) => {
      console.log('Notificación de crédito recibida:', data);
      this.onCreditNotification?.(data);
    });

    this.socket.on('location_update', (data) => {
      console.log('Actualización de ubicación recibida:', data);
      this.onLocationUpdate?.(data);
    });

    this.socket.on('send_message', (data) => {
      console.log('Mensaje recibido:', data);
      this.onMessage?.(data);
    });

    this.socket.on('notification', (data) => {
      console.log('Notificación general recibida:', data);
      this.onGeneralNotification?.(data);
    });
  }

  /**
   * Enviar actualización de ubicación
   */
  sendLocationUpdate(latitude, longitude) {
    if (this.socket?.connected) {
      this.socket.emit('location_update', {
        user_id: this.currentUserId,
        latitude,
        longitude,
        timestamp: new Date().toISOString(),
      });
    }
  }

  /**
   * Enviar mensaje
   */
  sendMessage(toUserId, message) {
    if (this.socket?.connected) {
      this.socket.emit('send_message', {
        from_user_id: this.currentUserId,
        to_user_id: toUserId,
        message,
        timestamp: new Date().toISOString(),
      });
    }
  }

  /**
   * Desconectar
   */
  disconnect() {
    this.socket?.disconnect();
    this.socket = null;
    console.log('WebSocket desconectado');
  }

  /**
   * Verificar estado de conexión
   */
  get isConnected() {
    return this.socket?.connected || false;
  }
}

// Exportar instancia singleton
export const websocketService = new WebSocketService();
export default websocketService;
```

## 3. Servicio API REST

```javascript
// src/services/apiService.js
import axios from 'axios';

class ApiService {
  constructor() {
    this.baseURL = 'http://192.168.5.44:8000/api';
    this.token = null;
    
    // Configurar axios
    this.api = axios.create({
      baseURL: this.baseURL,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    // Interceptor para agregar token automáticamente
    this.api.interceptors.request.use(
      (config) => {
        if (this.token) {
          config.headers.Authorization = `Bearer ${this.token}`;
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Interceptor para manejar respuestas
    this.api.interceptors.response.use(
      (response) => response,
      (error) => {
        if (error.response?.status === 401) {
          // Token expirado o inválido
          this.token = null;
          localStorage.removeItem('token');
          window.location.href = '/login';
        }
        return Promise.reject(error);
      }
    );
  }

  /**
   * Configurar token
   */
  setToken(token) {
    this.token = token;
    localStorage.setItem('token', token);
  }

  /**
   * Login
   */
  async login(email, password) {
    try {
      const response = await this.api.post('/login', {
        email,
        password,
      });

      if (response.data.token) {
        this.setToken(response.data.token);
      }

      console.log('Login exitoso');
      return response.data;
    } catch (error) {
      console.error('Error en login:', error.response?.data || error.message);
      throw error;
    }
  }

  /**
   * Obtener créditos del cobrador
   */
  async getCredits() {
    try {
      const response = await this.api.get('/cobrador/credits');
      return response.data.credits || response.data.data;
    } catch (error) {
      console.error('Error obteniendo créditos:', error);
      throw error;
    }
  }

  /**
   * Registrar pago
   */
  async registerPayment({ creditId, amount, paymentMethod, notes }) {
    try {
      const response = await this.api.post('/payments', {
        credit_id: creditId,
        amount,
        payment_method: paymentMethod,
        payment_date: new Date().toISOString(),
        notes,
      });

      console.log('Pago registrado exitosamente');
      return response.data;
    } catch (error) {
      console.error('Error registrando pago:', error.response?.data || error.message);
      throw error;
    }
  }

  /**
   * Obtener notificaciones
   */
  async getNotifications() {
    try {
      const response = await this.api.get('/notifications');
      return response.data.notifications || response.data.data;
    } catch (error) {
      console.error('Error obteniendo notificaciones:', error);
      throw error;
    }
  }

  /**
   * Marcar notificación como leída
   */
  async markNotificationAsRead(notificationId) {
    try {
      const response = await this.api.patch(`/notifications/${notificationId}/read`);
      return response.data;
    } catch (error) {
      console.error('Error marcando notificación como leída:', error);
      throw error;
    }
  }

  /**
   * Logout
   */
  async logout() {
    try {
      await this.api.post('/logout');
    } catch (error) {
      console.error('Error en logout:', error);
    } finally {
      this.token = null;
      localStorage.removeItem('token');
    }
  }
}

// Exportar instancia singleton
export const apiService = new ApiService();
export default apiService;
```

## 4. Redux Store (con Redux Toolkit)

```javascript
// src/store/index.js
import { configureStore } from '@reduxjs/toolkit';
import authSlice from './slices/authSlice';
import notificationSlice from './slices/notificationSlice';
import creditSlice from './slices/creditSlice';

export const store = configureStore({
  reducer: {
    auth: authSlice,
    notifications: notificationSlice,
    credits: creditSlice,
  },
  middleware: (getDefaultMiddleware) =>
    getDefaultMiddleware({
      serializableCheck: {
        ignoredActions: ['persist/PERSIST'],
      },
    }),
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
```

```javascript
// src/store/slices/authSlice.js
import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import { apiService } from '../../services/apiService';
import { websocketService } from '../../services/websocketService';

// Async thunks
export const loginUser = createAsyncThunk(
  'auth/login',
  async ({ email, password }, { rejectWithValue }) => {
    try {
      const data = await apiService.login(email, password);
      
      // Conectar WebSocket después del login
      websocketService.connect(
        data.user.id.toString(),
        data.user.role || 'client'
      );
      
      return data;
    } catch (error) {
      return rejectWithValue(error.response?.data || error.message);
    }
  }
);

export const logoutUser = createAsyncThunk(
  'auth/logout',
  async (_, { dispatch }) => {
    await apiService.logout();
    websocketService.disconnect();
    dispatch(clearCredits());
    dispatch(clearNotifications());
  }
);

const authSlice = createSlice({
  name: 'auth',
  initialState: {
    user: null,
    token: localStorage.getItem('token'),
    isLoading: false,
    isConnected: false,
    error: null,
  },
  reducers: {
    setConnectionStatus: (state, action) => {
      state.isConnected = action.payload;
    },
    clearError: (state) => {
      state.error = null;
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(loginUser.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(loginUser.fulfilled, (state, action) => {
        state.isLoading = false;
        state.user = action.payload.user;
        state.token = action.payload.token;
        state.error = null;
      })
      .addCase(loginUser.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.payload;
      })
      .addCase(logoutUser.fulfilled, (state) => {
        state.user = null;
        state.token = null;
        state.isConnected = false;
        state.error = null;
      });
  },
});

export const { setConnectionStatus, clearError } = authSlice.actions;
export default authSlice.reducer;
```

```javascript
// src/store/slices/notificationSlice.js
import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import { apiService } from '../../services/apiService';

export const fetchNotifications = createAsyncThunk(
  'notifications/fetch',
  async (_, { rejectWithValue }) => {
    try {
      const data = await apiService.getNotifications();
      return data;
    } catch (error) {
      return rejectWithValue(error.response?.data || error.message);
    }
  }
);

const notificationSlice = createSlice({
  name: 'notifications',
  initialState: {
    items: [],
    unreadCount: 0,
    isLoading: false,
    error: null,
  },
  reducers: {
    addNotification: (state, action) => {
      state.items.unshift(action.payload);
      if (action.payload.status === 'unread') {
        state.unreadCount += 1;
      }
    },
    markAsRead: (state, action) => {
      const notification = state.items.find(item => item.id === action.payload);
      if (notification && notification.status === 'unread') {
        notification.status = 'read';
        state.unreadCount = Math.max(0, state.unreadCount - 1);
      }
    },
    clearNotifications: (state) => {
      state.items = [];
      state.unreadCount = 0;
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchNotifications.pending, (state) => {
        state.isLoading = true;
      })
      .addCase(fetchNotifications.fulfilled, (state, action) => {
        state.isLoading = false;
        state.items = action.payload;
        state.unreadCount = action.payload.filter(item => item.status === 'unread').length;
      })
      .addCase(fetchNotifications.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.payload;
      });
  },
});

export const { addNotification, markAsRead, clearNotifications } = notificationSlice.actions;
export default notificationSlice.reducer;
```

```javascript
// src/store/slices/creditSlice.js
import { createSlice, createAsyncThunk } from '@reduxjs/toolkit';
import { apiService } from '../../services/apiService';

export const fetchCredits = createAsyncThunk(
  'credits/fetch',
  async (_, { rejectWithValue }) => {
    try {
      const data = await apiService.getCredits();
      return data;
    } catch (error) {
      return rejectWithValue(error.response?.data || error.message);
    }
  }
);

export const registerPayment = createAsyncThunk(
  'credits/registerPayment',
  async (paymentData, { rejectWithValue, dispatch }) => {
    try {
      const data = await apiService.registerPayment(paymentData);
      // Recargar créditos después del pago
      dispatch(fetchCredits());
      return data;
    } catch (error) {
      return rejectWithValue(error.response?.data || error.message);
    }
  }
);

const creditSlice = createSlice({
  name: 'credits',
  initialState: {
    items: [],
    isLoading: false,
    error: null,
  },
  reducers: {
    clearCredits: (state) => {
      state.items = [];
    },
    updateCredit: (state, action) => {
      const index = state.items.findIndex(item => item.id === action.payload.id);
      if (index !== -1) {
        state.items[index] = { ...state.items[index], ...action.payload };
      }
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchCredits.pending, (state) => {
        state.isLoading = true;
      })
      .addCase(fetchCredits.fulfilled, (state, action) => {
        state.isLoading = false;
        state.items = action.payload;
      })
      .addCase(fetchCredits.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.payload;
      })
      .addCase(registerPayment.fulfilled, (state) => {
        // El éxito del pago será manejado por la recarga de créditos
      });
  },
});

export const { clearCredits, updateCredit } = creditSlice.actions;
export default creditSlice.reducer;
```

## 5. Hook personalizado para WebSocket

```javascript
// src/hooks/useWebSocket.js
import { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { websocketService } from '../services/websocketService';
import { setConnectionStatus } from '../store/slices/authSlice';
import { addNotification } from '../store/slices/notificationSlice';
import { updateCredit, fetchCredits } from '../store/slices/creditSlice';

export const useWebSocket = () => {
  const dispatch = useDispatch();
  const { user, isConnected } = useSelector((state) => state.auth);

  useEffect(() => {
    if (!user) return;

    // Configurar listeners de WebSocket
    websocketService.onPaymentNotification = (data) => {
      dispatch(addNotification({
        id: `payment_${Date.now()}`,
        type: 'payment',
        title: 'Pago Recibido',
        message: `Se recibió un pago de ${data.payment.amount} Bs`,
        data,
        timestamp: new Date().toISOString(),
        status: 'unread',
      }));
    };

    websocketService.onCreditNotification = (data) => {
      dispatch(addNotification({
        id: `credit_${Date.now()}`,
        type: 'credit',
        title: 'Actualización de Crédito',
        message: `Crédito ${data.action}: ${data.credit.client_name}`,
        data,
        timestamp: new Date().toISOString(),
        status: 'unread',
      }));
      
      // Recargar créditos si hay cambios
      dispatch(fetchCredits());
    };

    websocketService.onMessage = (data) => {
      dispatch(addNotification({
        id: `message_${Date.now()}`,
        type: 'message',
        title: 'Nuevo Mensaje',
        message: data.message,
        data,
        timestamp: new Date().toISOString(),
        status: 'unread',
      }));
    };

    // Verificar conexión periódicamente
    const checkConnection = () => {
      const connected = websocketService.isConnected;
      if (connected !== isConnected) {
        dispatch(setConnectionStatus(connected));
      }
    };

    const interval = setInterval(checkConnection, 1000);

    return () => {
      clearInterval(interval);
    };
  }, [user, isConnected, dispatch]);

  // Funciones para enviar datos
  const sendLocationUpdate = (latitude, longitude) => {
    websocketService.sendLocationUpdate(latitude, longitude);
  };

  const sendMessage = (toUserId, message) => {
    websocketService.sendMessage(toUserId, message);
  };

  return {
    sendLocationUpdate,
    sendMessage,
    isConnected,
  };
};
```

## 6. Componentes React

```jsx
// src/components/Dashboard.jsx
import React, { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { fetchCredits, registerPayment } from '../store/slices/creditSlice';
import { fetchNotifications } from '../store/slices/notificationSlice';
import { useWebSocket } from '../hooks/useWebSocket';
import CreditList from './CreditList';
import NotificationPanel from './NotificationPanel';
import ConnectionStatus from './ConnectionStatus';

const Dashboard = () => {
  const dispatch = useDispatch();
  const { user } = useSelector((state) => state.auth);
  const { items: credits, isLoading: creditsLoading } = useSelector((state) => state.credits);
  const { items: notifications, unreadCount } = useSelector((state) => state.notifications);
  
  const { isConnected, sendMessage } = useWebSocket();

  useEffect(() => {
    // Cargar datos iniciales
    dispatch(fetchCredits());
    dispatch(fetchNotifications());
  }, [dispatch]);

  const handleRegisterPayment = async (paymentData) => {
    try {
      await dispatch(registerPayment(paymentData)).unwrap();
      alert('Pago registrado exitosamente');
    } catch (error) {
      alert('Error al registrar el pago: ' + error.message);
    }
  };

  const handleSendTestMessage = () => {
    sendMessage('1', 'Mensaje de prueba desde React');
    alert('Mensaje enviado');
  };

  return (
    <div className="dashboard">
      <header className="dashboard-header">
        <div className="user-info">
          <div className="user-avatar">
            {user?.name?.charAt(0) || 'U'}
          </div>
          <div className="user-details">
            <h2>{user?.name || 'Usuario'}</h2>
            <p>{user?.email}</p>
            <span className="user-role">Rol: {user?.role || 'N/A'}</span>
          </div>
        </div>
        
        <div className="header-actions">
          <ConnectionStatus isConnected={isConnected} />
          <NotificationPanel 
            notifications={notifications} 
            unreadCount={unreadCount} 
          />
        </div>
      </header>

      <main className="dashboard-content">
        <div className="content-section">
          <h3>Créditos</h3>
          {creditsLoading ? (
            <div className="loading">Cargando créditos...</div>
          ) : (
            <CreditList 
              credits={credits} 
              onRegisterPayment={handleRegisterPayment}
            />
          )}
        </div>

        <div className="actions-section">
          <button 
            onClick={handleSendTestMessage}
            className="btn btn-primary"
          >
            Enviar Mensaje de Prueba
          </button>
        </div>
      </main>
    </div>
  );
};

export default Dashboard;
```

```jsx
// src/components/CreditList.jsx
import React, { useState } from 'react';
import PaymentModal from './PaymentModal';

const CreditList = ({ credits, onRegisterPayment }) => {
  const [selectedCredit, setSelectedCredit] = useState(null);
  const [showPaymentModal, setShowPaymentModal] = useState(false);

  const getStatusColor = (status) => {
    switch (status) {
      case 'active':
        return '#4CAF50';
      case 'pending':
        return '#FF9800';
      case 'overdue':
        return '#F44336';
      default:
        return '#9E9E9E';
    }
  };

  const handlePaymentClick = (credit) => {
    setSelectedCredit(credit);
    setShowPaymentModal(true);
  };

  const handlePaymentSubmit = (paymentData) => {
    onRegisterPayment({
      ...paymentData,
      creditId: selectedCredit.id,
    });
    setShowPaymentModal(false);
    setSelectedCredit(null);
  };

  return (
    <div className="credit-list">
      {credits.map((credit) => (
        <div key={credit.id} className="credit-card">
          <div className="credit-header">
            <div 
              className="credit-status"
              style={{ backgroundColor: getStatusColor(credit.status) }}
            >
              {credit.status}
            </div>
            <h4>Crédito #{credit.id}</h4>
          </div>
          
          <div className="credit-details">
            <p><strong>Cliente:</strong> {credit.client_name || 'N/A'}</p>
            <p><strong>Monto:</strong> {credit.amount} Bs</p>
            <p><strong>Total:</strong> {credit.total_amount} Bs</p>
            <p><strong>Balance:</strong> {credit.balance} Bs</p>
          </div>
          
          <div className="credit-actions">
            <button 
              onClick={() => handlePaymentClick(credit)}
              className="btn btn-success"
            >
              Registrar Pago
            </button>
          </div>
        </div>
      ))}

      {showPaymentModal && (
        <PaymentModal
          credit={selectedCredit}
          onSubmit={handlePaymentSubmit}
          onClose={() => {
            setShowPaymentModal(false);
            setSelectedCredit(null);
          }}
        />
      )}
    </div>
  );
};

export default CreditList;
```

```jsx
// src/components/PaymentModal.jsx
import React, { useState } from 'react';

const PaymentModal = ({ credit, onSubmit, onClose }) => {
  const [formData, setFormData] = useState({
    amount: '',
    paymentMethod: 'cash',
    notes: '',
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    const amount = parseFloat(formData.amount);
    
    if (amount && amount > 0) {
      onSubmit({
        ...formData,
        amount,
      });
    } else {
      alert('Por favor ingrese un monto válido');
    }
  };

  const handleChange = (e) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value,
    });
  };

  return (
    <div className="modal-overlay">
      <div className="modal">
        <div className="modal-header">
          <h3>Registrar Pago</h3>
          <button onClick={onClose} className="close-btn">&times;</button>
        </div>
        
        <div className="modal-body">
          <div className="credit-info">
            <p><strong>Crédito:</strong> #{credit.id}</p>
            <p><strong>Cliente:</strong> {credit.client_name}</p>
            <p><strong>Balance actual:</strong> {credit.balance} Bs</p>
          </div>
          
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label>Monto:</label>
              <input
                type="number"
                name="amount"
                value={formData.amount}
                onChange={handleChange}
                step="0.01"
                min="0.01"
                max={credit.balance}
                required
              />
            </div>
            
            <div className="form-group">
              <label>Método de Pago:</label>
              <select
                name="paymentMethod"
                value={formData.paymentMethod}
                onChange={handleChange}
              >
                <option value="cash">Efectivo</option>
                <option value="transfer">Transferencia</option>
                <option value="card">Tarjeta</option>
              </select>
            </div>
            
            <div className="form-group">
              <label>Notas (opcional):</label>
              <textarea
                name="notes"
                value={formData.notes}
                onChange={handleChange}
                rows="3"
              />
            </div>
            
            <div className="form-actions">
              <button type="button" onClick={onClose} className="btn btn-secondary">
                Cancelar
              </button>
              <button type="submit" className="btn btn-primary">
                Registrar Pago
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
};

export default PaymentModal;
```

```jsx
// src/components/NotificationPanel.jsx
import React, { useState } from 'react';
import { useDispatch } from 'react-redux';
import { markAsRead } from '../store/slices/notificationSlice';

const NotificationPanel = ({ notifications, unreadCount }) => {
  const [isOpen, setIsOpen] = useState(false);
  const dispatch = useDispatch();

  const getNotificationIcon = (type) => {
    switch (type) {
      case 'payment':
        return '💰';
      case 'credit':
        return '💳';
      case 'message':
        return '💬';
      default:
        return '🔔';
    }
  };

  const formatTime = (timestamp) => {
    const date = new Date(timestamp);
    return `${date.getHours()}:${date.getMinutes().toString().padStart(2, '0')}`;
  };

  const handleNotificationClick = (notification) => {
    if (notification.status === 'unread') {
      dispatch(markAsRead(notification.id));
    }
  };

  return (
    <div className="notification-panel">
      <button 
        className="notification-trigger"
        onClick={() => setIsOpen(!isOpen)}
      >
        🔔
        {unreadCount > 0 && (
          <span className="notification-badge">{unreadCount}</span>
        )}
      </button>

      {isOpen && (
        <div className="notification-dropdown">
          <div className="notification-header">
            <h4>Notificaciones</h4>
            <button onClick={() => setIsOpen(false)}>&times;</button>
          </div>
          
          <div className="notification-list">
            {notifications.length === 0 ? (
              <div className="no-notifications">
                No hay notificaciones
              </div>
            ) : (
              notifications.map((notification) => (
                <div 
                  key={notification.id}
                  className={`notification-item ${notification.status === 'unread' ? 'unread' : ''}`}
                  onClick={() => handleNotificationClick(notification)}
                >
                  <div className="notification-icon">
                    {getNotificationIcon(notification.type)}
                  </div>
                  <div className="notification-content">
                    <div className="notification-title">
                      {notification.title}
                    </div>
                    <div className="notification-message">
                      {notification.message}
                    </div>
                  </div>
                  <div className="notification-time">
                    {formatTime(notification.timestamp)}
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default NotificationPanel;
```

```jsx
// src/components/ConnectionStatus.jsx
import React from 'react';

const ConnectionStatus = ({ isConnected }) => {
  return (
    <div className="connection-status">
      <div className={`status-indicator ${isConnected ? 'connected' : 'disconnected'}`}>
        <div className="status-dot"></div>
        <span>{isConnected ? 'Conectado' : 'Desconectado'}</span>
      </div>
    </div>
  );
};

export default ConnectionStatus;
```

## 7. App.jsx principal

```jsx
// src/App.jsx
import React, { useEffect } from 'react';
import { Provider } from 'react-redux';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { store } from './store';
import { apiService } from './services/apiService';
import { websocketService } from './services/websocketService';
import Login from './components/Login';
import Dashboard from './components/Dashboard';
import ProtectedRoute from './components/ProtectedRoute';
import './styles/App.css';

function App() {
  useEffect(() => {
    // Verificar token guardado
    const token = localStorage.getItem('token');
    if (token) {
      apiService.setToken(token);
    }

    // Cleanup al cerrar la app
    return () => {
      websocketService.disconnect();
    };
  }, []);

  return (
    <Provider store={store}>
      <Router>
        <div className="app">
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route 
              path="/dashboard" 
              element={
                <ProtectedRoute>
                  <Dashboard />
                </ProtectedRoute>
              } 
            />
            <Route path="/" element={<Navigate to="/dashboard" replace />} />
          </Routes>
        </div>
      </Router>
    </Provider>
  );
}

export default App;
```

## 8. Estilos CSS básicos

```css
/* src/styles/App.css */
.app {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  min-height: 100vh;
  background-color: #f5f5f5;
}

.dashboard {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  margin-bottom: 20px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 15px;
}

.user-avatar {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: #2196F3;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 20px;
  font-weight: bold;
}

.user-role {
  color: #2196F3;
  font-weight: 500;
}

.credit-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.credit-card {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.credit-status {
  display: inline-block;
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  text-transform: uppercase;
  margin-bottom: 10px;
}

.connection-status {
  display: flex;
  align-items: center;
  gap: 10px;
}

.status-indicator {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 14px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.connected .status-dot {
  background: #4CAF50;
}

.disconnected .status-dot {
  background: #F44336;
}

.notification-panel {
  position: relative;
}

.notification-trigger {
  position: relative;
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
}

.notification-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  background: #F44336;
  color: white;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  font-size: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.notification-dropdown {
  position: absolute;
  top: 100%;
  right: 0;
  width: 300px;
  background: white;
  border: 1px solid #ddd;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  z-index: 1000;
}

.notification-item {
  display: flex;
  align-items: center;
  padding: 12px;
  border-bottom: 1px solid #eee;
  cursor: pointer;
}

.notification-item:hover {
  background: #f9f9f9;
}

.notification-item.unread {
  background: #e3f2fd;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  border-radius: 8px;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  transition: background-color 0.2s;
}

.btn-primary {
  background: #2196F3;
  color: white;
}

.btn-success {
  background: #4CAF50;
  color: white;
}

.btn-secondary {
  background: #9E9E9E;
  color: white;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}
```
