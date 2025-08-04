import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:socket_io_client/socket_io_client.dart' as IO;

/// Tipos de usuario soportados
enum UserType { client, cobrador, admin }

/// Estados de conexión WebSocket
enum ConnectionStatus {
  disconnected,
  connecting,
  connected,
  reconnecting,
  error,
}

/// Modelo para notificaciones
class WebSocketNotification {
  final String title;
  final String message;
  final String type;
  final Map<String, dynamic>? data;
  final DateTime timestamp;

  WebSocketNotification({
    required this.title,
    required this.message,
    required this.type,
    this.data,
    DateTime? timestamp,
  }) : timestamp = timestamp ?? DateTime.now();

  factory WebSocketNotification.fromJson(Map<String, dynamic> json) {
    return WebSocketNotification(
      title: json['title'] ?? '',
      message: json['message'] ?? '',
      type: json['type'] ?? 'info',
      data: json['data'],
      timestamp: json['timestamp'] != null
          ? DateTime.parse(json['timestamp'])
          : DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'title': title,
      'message': message,
      'type': type,
      'data': data,
      'timestamp': timestamp.toIso8601String(),
    };
  }
}

/// Modelo para usuario conectado
class ConnectedUser {
  final String id;
  final String name;
  final UserType type;

  ConnectedUser({required this.id, required this.name, required this.type});

  factory ConnectedUser.fromJson(Map<String, dynamic> json) {
    return ConnectedUser(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      type: _parseUserType(json['type']),
    );
  }

  static UserType _parseUserType(String? type) {
    switch (type?.toLowerCase()) {
      case 'cobrador':
        return UserType.cobrador;
      case 'admin':
        return UserType.admin;
      default:
        return UserType.client;
    }
  }

  String get typeString {
    switch (type) {
      case UserType.cobrador:
        return 'cobrador';
      case UserType.admin:
        return 'admin';
      case UserType.client:
        return 'client';
    }
  }
}

/// Manager principal para WebSocket
class WebSocketManager extends ChangeNotifier {
  static final WebSocketManager _instance = WebSocketManager._internal();
  factory WebSocketManager() => _instance;
  WebSocketManager._internal();

  // Socket.IO client
  IO.Socket? _socket;

  // Estado de conexión
  ConnectionStatus _status = ConnectionStatus.disconnected;
  ConnectionStatus get status => _status;

  // Usuario actual
  ConnectedUser? _currentUser;
  ConnectedUser? get currentUser => _currentUser;

  // Configuración
  String _serverUrl =
      'http://192.168.1.100:3001'; // IP por defecto para desarrollo
  String get serverUrl => _serverUrl;

  // Intentos de reconexión
  int _reconnectAttempts = 0;
  static const int maxReconnectAttempts = 5;
  static const Duration reconnectDelay = Duration(seconds: 2);

  // Streams para eventos
  final StreamController<WebSocketNotification> _notificationController =
      StreamController<WebSocketNotification>.broadcast();
  final StreamController<Map<String, dynamic>> _paymentController =
      StreamController<Map<String, dynamic>>.broadcast();
  final StreamController<Map<String, dynamic>> _routeController =
      StreamController<Map<String, dynamic>>.broadcast();
  final StreamController<Map<String, dynamic>> _messageController =
      StreamController<Map<String, dynamic>>.broadcast();
  final StreamController<Map<String, dynamic>> _locationController =
      StreamController<Map<String, dynamic>>.broadcast();

  // Getters para streams
  Stream<WebSocketNotification> get notificationStream =>
      _notificationController.stream;
  Stream<Map<String, dynamic>> get paymentStream => _paymentController.stream;
  Stream<Map<String, dynamic>> get routeStream => _routeController.stream;
  Stream<Map<String, dynamic>> get messageStream => _messageController.stream;
  Stream<Map<String, dynamic>> get locationStream => _locationController.stream;

  /// Configurar URL del servidor
  void configureServer({String? url, bool isProduction = false}) {
    if (url != null) {
      _serverUrl = url;
    } else if (isProduction) {
      // URL de producción - cambiar por tu dominio
      _serverUrl = 'https://tu-dominio.com:3001';
    } else {
      // Detectar IP local automáticamente para desarrollo
      _detectLocalIP();
    }

    debugPrint('WebSocket configurado para: $_serverUrl');
  }

  /// Detectar IP local automáticamente
  Future<void> _detectLocalIP() async {
    try {
      // Intenta detectar la IP de la red local
      final interfaces = await NetworkInterface.list();
      for (var interface in interfaces) {
        for (var addr in interface.addresses) {
          if (addr.type == InternetAddressType.IPv4 &&
              !addr.isLoopback &&
              addr.address.startsWith('192.168.')) {
            _serverUrl = 'http://${addr.address}:3001';
            debugPrint('IP local detectada: ${addr.address}');
            return;
          }
        }
      }
    } catch (e) {
      debugPrint('Error detectando IP local: $e');
    }
  }

  /// Conectar al servidor WebSocket
  Future<bool> connect({String? customUrl}) async {
    if (_status == ConnectionStatus.connecting ||
        _status == ConnectionStatus.connected) {
      return _status == ConnectionStatus.connected;
    }

    if (customUrl != null) {
      _serverUrl = customUrl;
    }

    _updateStatus(ConnectionStatus.connecting);

    try {
      // Crear socket con configuración optimizada para móviles
      _socket = IO.io(_serverUrl, {
        'transports': ['websocket'],
        'timeout': 10000,
        'reconnection': true,
        'reconnectionAttempts': maxReconnectAttempts,
        'reconnectionDelay': reconnectDelay.inMilliseconds,
        'autoConnect': false,
      });

      _setupEventListeners();
      _socket!.connect();

      // Esperar conexión o timeout
      final completer = Completer<bool>();
      Timer? timeoutTimer;

      void cleanup() {
        timeoutTimer?.cancel();
      }

      _socket!.once('connect', (_) {
        cleanup();
        if (!completer.isCompleted) {
          completer.complete(true);
        }
      });

      _socket!.once('connect_error', (error) {
        cleanup();
        if (!completer.isCompleted) {
          completer.complete(false);
        }
      });

      timeoutTimer = Timer(const Duration(seconds: 10), () {
        cleanup();
        if (!completer.isCompleted) {
          completer.complete(false);
        }
      });

      return await completer.future;
    } catch (e) {
      debugPrint('Error conectando WebSocket: $e');
      _updateStatus(ConnectionStatus.error);
      return false;
    }
  }

  /// Configurar event listeners
  void _setupEventListeners() {
    if (_socket == null) return;

    // Eventos de conexión
    _socket!.on('connect', (_) {
      debugPrint('✅ Conectado a WebSocket: $_serverUrl');
      _updateStatus(ConnectionStatus.connected);
      _reconnectAttempts = 0;
    });

    _socket!.on('disconnect', (reason) {
      debugPrint('❌ Desconectado de WebSocket: $reason');
      _updateStatus(ConnectionStatus.disconnected);
    });

    _socket!.on('connect_error', (error) {
      debugPrint('❌ Error de conexión WebSocket: $error');
      _reconnectAttempts++;
      if (_reconnectAttempts < maxReconnectAttempts) {
        _updateStatus(ConnectionStatus.reconnecting);
      } else {
        _updateStatus(ConnectionStatus.error);
      }
    });

    // Eventos de autenticación
    _socket!.on('authenticated', (data) {
      debugPrint('✅ Autenticado en WebSocket');
    });

    _socket!.on('authentication_error', (data) {
      debugPrint('❌ Error de autenticación: ${data['message']}');
    });

    // Eventos de la aplicación
    _socket!.on('new_credit_notification', (data) {
      final notification = WebSocketNotification.fromJson(data);
      _notificationController.add(notification);
    });

    _socket!.on('payment_updated', (data) {
      _paymentController.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('route_updated', (data) {
      _routeController.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('credit_attention_required', (data) {
      final notification = WebSocketNotification.fromJson(data);
      _notificationController.add(notification);
    });

    _socket!.on('new_message', (data) {
      _messageController.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('cobrador_location_update', (data) {
      _locationController.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('user_connected', (data) {
      debugPrint('👋 Usuario conectado: ${data['userName']}');
    });

    _socket!.on('user_disconnected', (data) {
      debugPrint('👋 Usuario desconectado: ${data['userName']}');
    });
  }

  /// Autenticar usuario
  Future<bool> authenticate(ConnectedUser user) async {
    if (_socket == null || _status != ConnectionStatus.connected) {
      debugPrint('❌ No hay conexión WebSocket para autenticar');
      return false;
    }

    try {
      _currentUser = user;

      _socket!.emit('authenticate', {
        'userId': user.id,
        'userType': user.typeString,
        'userName': user.name,
      });

      return true;
    } catch (e) {
      debugPrint('Error autenticando usuario: $e');
      return false;
    }
  }

  /// Desconectar
  void disconnect() {
    _socket?.disconnect();
    _socket?.dispose();
    _socket = null;
    _updateStatus(ConnectionStatus.disconnected);
    _currentUser = null;
    debugPrint('🔌 WebSocket desconectado');
  }

  /// Enviar notificación de crédito
  void sendCreditNotification({
    String? targetUserId,
    String? userType,
    required WebSocketNotification notification,
  }) {
    if (!_canSendEvents()) return;

    _socket!.emit('credit_notification', {
      if (targetUserId != null) 'targetUserId': targetUserId,
      if (userType != null) 'userType': userType,
      'notification': notification.toJson(),
    });
  }

  /// Actualizar pago
  void updatePayment({
    required String paymentId,
    String? cobradorId,
    String? clientId,
    required Map<String, dynamic> paymentData,
  }) {
    if (!_canSendEvents()) return;

    _socket!.emit('payment_update', {
      'cobradorId': cobradorId,
      'clientId': clientId,
      'payment': {'id': paymentId, ...paymentData},
    });
  }

  /// Actualizar ubicación (solo cobradores)
  void updateLocation(double latitude, double longitude) {
    if (!_canSendEvents()) return;

    if (_currentUser?.type != UserType.cobrador) {
      debugPrint('❌ Solo los cobradores pueden actualizar ubicación');
      return;
    }

    _socket!.emit('location_update', {
      'latitude': latitude,
      'longitude': longitude,
    });
  }

  /// Enviar mensaje
  void sendMessage({required String recipientId, required String message}) {
    if (!_canSendEvents()) return;

    _socket!.emit('send_message', {
      'recipientId': recipientId,
      'message': message,
      'senderId': _currentUser?.id,
    });
  }

  /// Notificar actualización de ruta
  void notifyRouteUpdate({
    required String cobradorId,
    required Map<String, dynamic> routeData,
  }) {
    if (!_canSendEvents()) return;

    _socket!.emit('route_notification', {
      'cobradorId': cobradorId,
      'routeUpdate': routeData,
    });
  }

  /// Verificar si se pueden enviar eventos
  bool _canSendEvents() {
    if (_socket == null || _status != ConnectionStatus.connected) {
      debugPrint('❌ No hay conexión WebSocket activa');
      return false;
    }
    return true;
  }

  /// Actualizar estado de conexión
  void _updateStatus(ConnectionStatus newStatus) {
    if (_status != newStatus) {
      _status = newStatus;
      notifyListeners();
    }
  }

  /// Verificar si está conectado
  bool get isConnected => _status == ConnectionStatus.connected;

  /// Obtener información de estado
  Map<String, dynamic> getStatusInfo() {
    return {
      'status': _status.toString(),
      'serverUrl': _serverUrl,
      'reconnectAttempts': _reconnectAttempts,
      'currentUser': _currentUser?.toJson(),
      'socketId': _socket?.id,
    };
  }

  /// Limpiar recursos
  @override
  void dispose() {
    disconnect();
    _notificationController.close();
    _paymentController.close();
    _routeController.close();
    _messageController.close();
    _locationController.close();
    super.dispose();
  }
}

/// Widget para mostrar estado de conexión WebSocket
class WebSocketStatusIndicator extends StatelessWidget {
  final WebSocketManager wsManager;
  final bool showText;

  const WebSocketStatusIndicator({
    Key? key,
    required this.wsManager,
    this.showText = true,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: wsManager,
      builder: (context, child) {
        final status = wsManager.status;
        final color = _getStatusColor(status);
        final text = _getStatusText(status);

        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(
            color: color.withOpacity(0.1),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: color.withOpacity(0.3)),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(color: color, shape: BoxShape.circle),
              ),
              if (showText) ...[
                const SizedBox(width: 6),
                Text(
                  text,
                  style: TextStyle(
                    color: color,
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ],
          ),
        );
      },
    );
  }

  Color _getStatusColor(ConnectionStatus status) {
    switch (status) {
      case ConnectionStatus.connected:
        return Colors.green;
      case ConnectionStatus.connecting:
      case ConnectionStatus.reconnecting:
        return Colors.orange;
      case ConnectionStatus.error:
        return Colors.red;
      case ConnectionStatus.disconnected:
        return Colors.grey;
    }
  }

  String _getStatusText(ConnectionStatus status) {
    switch (status) {
      case ConnectionStatus.connected:
        return 'Conectado';
      case ConnectionStatus.connecting:
        return 'Conectando...';
      case ConnectionStatus.reconnecting:
        return 'Reconectando...';
      case ConnectionStatus.error:
        return 'Error';
      case ConnectionStatus.disconnected:
        return 'Desconectado';
    }
  }
}
