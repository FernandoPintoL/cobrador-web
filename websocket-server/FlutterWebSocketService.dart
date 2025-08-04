import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:socket_io_client/socket_io_client.dart' as IO;

/// Servicio WebSocket simplificado para Flutter
class FlutterWebSocketService {
  static final FlutterWebSocketService _instance =
      FlutterWebSocketService._internal();
  factory FlutterWebSocketService() => _instance;
  FlutterWebSocketService._internal();

  IO.Socket? _socket;
  bool _isConnected = false;
  String _serverUrl =
      'http://192.168.5.44:3001'; // IP detectada automáticamente

  // Información del usuario actual
  Map<String, dynamic>? _currentUser;

  // Streams para eventos
  final StreamController<Map<String, dynamic>> _notificationController =
      StreamController<Map<String, dynamic>>.broadcast();
  final StreamController<Map<String, dynamic>> _paymentController =
      StreamController<Map<String, dynamic>>.broadcast();
  final StreamController<Map<String, dynamic>> _routeController =
      StreamController<Map<String, dynamic>>.broadcast();
  final StreamController<Map<String, dynamic>> _messageController =
      StreamController<Map<String, dynamic>>.broadcast();
  final StreamController<Map<String, dynamic>> _locationController =
      StreamController<Map<String, dynamic>>.broadcast();
  final StreamController<bool> _connectionController =
      StreamController<bool>.broadcast();

  // Getters para streams
  Stream<Map<String, dynamic>> get notificationStream =>
      _notificationController.stream;
  Stream<Map<String, dynamic>> get paymentStream => _paymentController.stream;
  Stream<Map<String, dynamic>> get routeStream => _routeController.stream;
  Stream<Map<String, dynamic>> get messageStream => _messageController.stream;
  Stream<Map<String, dynamic>> get locationStream => _locationController.stream;
  Stream<bool> get connectionStream => _connectionController.stream;

  bool get isConnected => _isConnected;
  String get serverUrl => _serverUrl;
  Map<String, dynamic>? get currentUser => _currentUser;

  /// Configurar servidor
  void configureServer({String? url, bool isProduction = false}) {
    if (url != null) {
      _serverUrl = url;
    } else if (isProduction) {
      // URL de producción - cambiar por tu dominio
      _serverUrl = 'https://tu-dominio.com:3001';
    } else {
      // Para desarrollo local, detectar automáticamente o usar IP manual
      _serverUrl = 'http://192.168.1.100:3001'; // Cambiar por tu IP local
    }

    print('WebSocket configurado para: $_serverUrl');
  }

  /// Conectar al servidor
  Future<bool> connect({String? customUrl}) async {
    if (_isConnected) return true;

    if (customUrl != null) {
      _serverUrl = customUrl;
    }

    try {
      _socket = IO.io(_serverUrl, {
        'transports': ['websocket'],
        'timeout': 10000,
        'reconnection': true,
        'reconnectionAttempts': 5,
        'reconnectionDelay': 2000,
        'autoConnect': false,
      });

      _setupEventListeners();
      _socket!.connect();

      final completer = Completer<bool>();
      Timer? timeoutTimer;

      _socket!.once('connect', (_) {
        timeoutTimer?.cancel();
        if (!completer.isCompleted) {
          completer.complete(true);
        }
      });

      _socket!.once('connect_error', (error) {
        timeoutTimer?.cancel();
        if (!completer.isCompleted) {
          completer.complete(false);
        }
      });

      timeoutTimer = Timer(const Duration(seconds: 10), () {
        if (!completer.isCompleted) {
          completer.complete(false);
        }
      });

      return await completer.future;
    } catch (e) {
      print('Error conectando WebSocket: $e');
      return false;
    }
  }

  /// Configurar event listeners
  void _setupEventListeners() {
    if (_socket == null) return;

    _socket!.on('connect', (_) {
      print('✅ Conectado a WebSocket: $_serverUrl');
      _isConnected = true;
      _connectionController.add(true);
    });

    _socket!.on('disconnect', (reason) {
      print('❌ Desconectado de WebSocket: $reason');
      _isConnected = false;
      _connectionController.add(false);
    });

    _socket!.on('connect_error', (error) {
      print('❌ Error de conexión WebSocket: $error');
      _isConnected = false;
      _connectionController.add(false);
    });

    _socket!.on('authenticated', (data) {
      print('✅ Autenticado en WebSocket');
    });

    _socket!.on('authentication_error', (data) {
      print('❌ Error de autenticación: ${data['message']}');
    });

    // Eventos específicos
    _socket!.on('new_credit_notification', (data) {
      _notificationController.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('credit_attention_required', (data) {
      _notificationController.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('payment_updated', (data) {
      _paymentController.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('route_updated', (data) {
      _routeController.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('new_message', (data) {
      _messageController.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('cobrador_location_update', (data) {
      _locationController.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('user_connected', (data) {
      print('👋 Usuario conectado: ${data['userName']}');
    });

    _socket!.on('user_disconnected', (data) {
      print('👋 Usuario desconectado: ${data['userName']}');
    });
  }

  /// Autenticar usuario
  Future<bool> authenticate({
    required String userId,
    required String userName,
    required String userType, // 'client', 'cobrador', 'admin'
  }) async {
    if (!_isConnected || _socket == null) {
      print('❌ No hay conexión WebSocket para autenticar');
      return false;
    }

    try {
      _currentUser = {'id': userId, 'name': userName, 'type': userType};

      _socket!.emit('authenticate', {
        'userId': userId,
        'userType': userType,
        'userName': userName,
      });

      return true;
    } catch (e) {
      print('Error autenticando usuario: $e');
      return false;
    }
  }

  /// Desconectar
  void disconnect() {
    _socket?.disconnect();
    _socket?.dispose();
    _socket = null;
    _isConnected = false;
    _currentUser = null;
    _connectionController.add(false);
    print('🔌 WebSocket desconectado');
  }

  /// Enviar notificación de crédito
  void sendCreditNotification({
    String? targetUserId,
    String? userType,
    required String title,
    required String message,
    String type = 'credit',
    Map<String, dynamic>? additionalData,
  }) {
    if (!_canSendEvents()) return;

    final notification = {
      'title': title,
      'message': message,
      'type': type,
      'timestamp': DateTime.now().toIso8601String(),
      if (additionalData != null) ...additionalData,
    };

    _socket!.emit('credit_notification', {
      if (targetUserId != null) 'targetUserId': targetUserId,
      if (userType != null) 'userType': userType,
      'notification': notification,
    });
  }

  /// Actualizar pago
  void updatePayment({
    required String paymentId,
    String? cobradorId,
    String? clientId,
    required double amount,
    required String status,
    Map<String, dynamic>? additionalData,
  }) {
    if (!_canSendEvents()) return;

    _socket!.emit('payment_update', {
      'cobradorId': cobradorId,
      'clientId': clientId,
      'payment': {
        'id': paymentId,
        'amount': amount,
        'status': status,
        'timestamp': DateTime.now().toIso8601String(),
        if (additionalData != null) ...additionalData,
      },
    });
  }

  /// Actualizar ubicación (solo cobradores)
  void updateLocation(double latitude, double longitude) {
    if (!_canSendEvents()) return;

    if (_currentUser?['type'] != 'cobrador') {
      print('❌ Solo los cobradores pueden actualizar ubicación');
      return;
    }

    _socket!.emit('location_update', {
      'latitude': latitude,
      'longitude': longitude,
      'timestamp': DateTime.now().toIso8601String(),
    });
  }

  /// Enviar mensaje
  void sendMessage({required String recipientId, required String message}) {
    if (!_canSendEvents()) return;

    _socket!.emit('send_message', {
      'recipientId': recipientId,
      'message': message,
      'senderId': _currentUser?['id'],
      'timestamp': DateTime.now().toIso8601String(),
    });
  }

  /// Notificar actualización de ruta
  void notifyRouteUpdate({
    required String cobradorId,
    required String routeId,
    required String date,
    int? clientsCount,
    Map<String, dynamic>? additionalData,
  }) {
    if (!_canSendEvents()) return;

    _socket!.emit('route_notification', {
      'cobradorId': cobradorId,
      'routeUpdate': {
        'id': routeId,
        'date': date,
        'clients_count': clientsCount,
        'timestamp': DateTime.now().toIso8601String(),
        if (additionalData != null) ...additionalData,
      },
    });
  }

  /// Verificar si se pueden enviar eventos
  bool _canSendEvents() {
    if (_socket == null || !_isConnected) {
      print('❌ No hay conexión WebSocket activa');
      return false;
    }
    return true;
  }

  /// Obtener información de estado
  Map<String, dynamic> getStatusInfo() {
    return {
      'isConnected': _isConnected,
      'serverUrl': _serverUrl,
      'currentUser': _currentUser,
      'socketId': _socket?.id,
    };
  }

  /// Limpiar recursos
  void dispose() {
    disconnect();
    _notificationController.close();
    _paymentController.close();
    _routeController.close();
    _messageController.close();
    _locationController.close();
    _connectionController.close();
  }
}
