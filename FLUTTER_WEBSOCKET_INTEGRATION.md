# 📱 INTEGRACIÓN WEBSOCKET EN FLUTTER

## 1. Dependencias en pubspec.yaml

```yaml
dependencies:
  flutter:
    sdk: flutter
  socket_io_client: ^2.0.3+1  # Para WebSocket
  http: ^1.1.0                # Para API REST
  provider: ^6.1.1            # Para manejo de estado (opcional)
  logger: ^2.0.2+1            # Para logging (opcional)
```

## 2. Servicio WebSocket en Flutter

```dart
// lib/services/websocket_service.dart
import 'package:socket_io_client/socket_io_client.dart' as IO;
import 'package:logger/logger.dart';

class WebSocketService {
  static final WebSocketService _instance = WebSocketService._internal();
  factory WebSocketService() => _instance;
  WebSocketService._internal();

  IO.Socket? _socket;
  final Logger _logger = Logger();
  String? _currentUserId;
  String? _currentUserType;

  // Configuración del servidor
  static const String serverUrl = 'http://192.168.5.44:3001';

  // Callbacks para diferentes tipos de notificaciones
  Function(Map<String, dynamic>)? onPaymentNotification;
  Function(Map<String, dynamic>)? onCreditNotification;
  Function(Map<String, dynamic>)? onLocationUpdate;
  Function(Map<String, dynamic>)? onMessage;
  Function(Map<String, dynamic>)? onGeneralNotification;

  /// Conectar al servidor WebSocket
  Future<void> connect(String userId, String userType) async {
    try {
      _currentUserId = userId;
      _currentUserType = userType;

      _socket = IO.io(serverUrl, 
        IO.OptionBuilder()
          .setTransports(['websocket'])
          .enableAutoConnect()
          .setTimeout(5000)
          .build()
      );

      _socket!.connect();
      
      _setupEventListeners();
      
      _logger.i('Conectando a WebSocket como $userType (ID: $userId)');
      
    } catch (e) {
      _logger.e('Error conectando a WebSocket: $e');
    }
  }

  /// Configurar listeners de eventos
  void _setupEventListeners() {
    _socket!.onConnect((_) {
      _logger.i('Conectado a WebSocket');
      
      // Autenticar usuario al conectar
      _socket!.emit('authenticate', {
        'userId': _currentUserId,
        'userType': _currentUserType,
      });
    });

    _socket!.onDisconnect((_) {
      _logger.w('Desconectado de WebSocket');
    });

    _socket!.onConnectError((error) {
      _logger.e('Error de conexión WebSocket: $error');
    });

    // Listeners para diferentes tipos de notificaciones
    _socket!.on('payment_notification', (data) {
      _logger.i('Notificación de pago recibida: $data');
      onPaymentNotification?.call(Map<String, dynamic>.from(data));
    });

    _socket!.on('credit_notification', (data) {
      _logger.i('Notificación de crédito recibida: $data');
      onCreditNotification?.call(Map<String, dynamic>.from(data));
    });

    _socket!.on('location_update', (data) {
      _logger.i('Actualización de ubicación recibida: $data');
      onLocationUpdate?.call(Map<String, dynamic>.from(data));
    });

    _socket!.on('send_message', (data) {
      _logger.i('Mensaje recibido: $data');
      onMessage?.call(Map<String, dynamic>.from(data));
    });

    _socket!.on('notification', (data) {
      _logger.i('Notificación general recibida: $data');
      onGeneralNotification?.call(Map<String, dynamic>.from(data));
    });
  }

  /// Enviar actualización de ubicación
  void sendLocationUpdate(double latitude, double longitude) {
    if (_socket?.connected == true) {
      _socket!.emit('location_update', {
        'user_id': _currentUserId,
        'latitude': latitude,
        'longitude': longitude,
        'timestamp': DateTime.now().toIso8601String(),
      });
    }
  }

  /// Enviar mensaje
  void sendMessage(String toUserId, String message) {
    if (_socket?.connected == true) {
      _socket!.emit('send_message', {
        'from_user_id': _currentUserId,
        'to_user_id': toUserId,
        'message': message,
        'timestamp': DateTime.now().toIso8601String(),
      });
    }
  }

  /// Desconectar
  void disconnect() {
    _socket?.disconnect();
    _socket?.dispose();
    _socket = null;
    _logger.i('WebSocket desconectado');
  }

  /// Verificar estado de conexión
  bool get isConnected => _socket?.connected ?? false;
}
```

## 3. Servicio API REST

```dart
// lib/services/api_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:logger/logger.dart';

class ApiService {
  static const String baseUrl = 'http://192.168.5.44:8000/api';
  final Logger _logger = Logger();
  String? _token;

  // Singleton
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  /// Configurar token de autenticación
  void setToken(String token) {
    _token = token;
  }

  /// Headers básicos
  Map<String, String> get _headers => {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    if (_token != null) 'Authorization': 'Bearer $_token',
  };

  /// Login
  Future<Map<String, dynamic>?> login(String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/login'),
        headers: _headers,
        body: jsonEncode({
          'email': email,
          'password': password,
        }),
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        _token = data['token'];
        _logger.i('Login exitoso');
        return data;
      } else {
        _logger.e('Error en login: ${response.statusCode} - ${response.body}');
        return null;
      }
    } catch (e) {
      _logger.e('Error en login: $e');
      return null;
    }
  }

  /// Obtener créditos del cobrador
  Future<List<dynamic>?> getCredits() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/cobrador/credits'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data['credits'] ?? data['data'];
      } else {
        _logger.e('Error obteniendo créditos: ${response.statusCode}');
        return null;
      }
    } catch (e) {
      _logger.e('Error obteniendo créditos: $e');
      return null;
    }
  }

  /// Registrar pago
  Future<Map<String, dynamic>?> registerPayment({
    required String creditId,
    required double amount,
    required String paymentMethod,
    String? notes,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/payments'),
        headers: _headers,
        body: jsonEncode({
          'credit_id': creditId,
          'amount': amount,
          'payment_method': paymentMethod,
          'payment_date': DateTime.now().toIso8601String(),
          'notes': notes,
        }),
      );

      if (response.statusCode == 201) {
        final data = jsonDecode(response.body);
        _logger.i('Pago registrado exitosamente');
        return data;
      } else {
        _logger.e('Error registrando pago: ${response.statusCode} - ${response.body}');
        return null;
      }
    } catch (e) {
      _logger.e('Error registrando pago: $e');
      return null;
    }
  }

  /// Obtener notificaciones
  Future<List<dynamic>?> getNotifications() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/notifications'),
        headers: _headers,
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data['notifications'] ?? data['data'];
      } else {
        _logger.e('Error obteniendo notificaciones: ${response.statusCode}');
        return null;
      }
    } catch (e) {
      _logger.e('Error obteniendo notificaciones: $e');
      return null;
    }
  }
}
```

## 4. Provider para manejo de estado

```dart
// lib/providers/app_provider.dart
import 'package:flutter/foundation.dart';
import '../services/websocket_service.dart';
import '../services/api_service.dart';

class AppProvider with ChangeNotifier {
  final WebSocketService _wsService = WebSocketService();
  final ApiService _apiService = ApiService();

  // Estado de usuario
  Map<String, dynamic>? _currentUser;
  bool _isConnected = false;
  List<Map<String, dynamic>> _notifications = [];
  List<Map<String, dynamic>> _credits = [];

  // Getters
  Map<String, dynamic>? get currentUser => _currentUser;
  bool get isConnected => _isConnected;
  List<Map<String, dynamic>> get notifications => _notifications;
  List<Map<String, dynamic>> get credits => _credits;

  /// Inicializar app (login + WebSocket)
  Future<bool> initialize(String email, String password) async {
    try {
      // 1. Login
      final loginData = await _apiService.login(email, password);
      if (loginData == null) return false;

      _currentUser = loginData['user'];
      
      // 2. Conectar WebSocket
      await _wsService.connect(
        _currentUser!['id'].toString(),
        _currentUser!['role'] ?? 'client',
      );

      // 3. Configurar listeners
      _setupWebSocketListeners();

      // 4. Cargar datos iniciales
      await _loadInitialData();

      _isConnected = true;
      notifyListeners();
      return true;
    } catch (e) {
      debugPrint('Error inicializando app: $e');
      return false;
    }
  }

  /// Configurar listeners de WebSocket
  void _setupWebSocketListeners() {
    _wsService.onPaymentNotification = (data) {
      _addNotification({
        'type': 'payment',
        'title': 'Pago Recibido',
        'message': 'Se recibió un pago de ${data['payment']['amount']} Bs',
        'data': data,
        'timestamp': DateTime.now().toIso8601String(),
      });
    };

    _wsService.onCreditNotification = (data) {
      _addNotification({
        'type': 'credit',
        'title': 'Actualización de Crédito',
        'message': 'Crédito ${data['action']}: ${data['credit']['client_name']}',
        'data': data,
        'timestamp': DateTime.now().toIso8601String(),
      });
      
      // Recargar créditos si hay cambios
      _loadCredits();
    };

    _wsService.onMessage = (data) {
      _addNotification({
        'type': 'message',
        'title': 'Nuevo Mensaje',
        'message': data['message'],
        'data': data,
        'timestamp': DateTime.now().toIso8601String(),
      });
    };
  }

  /// Agregar notificación
  void _addNotification(Map<String, dynamic> notification) {
    _notifications.insert(0, notification);
    notifyListeners();
  }

  /// Cargar datos iniciales
  Future<void> _loadInitialData() async {
    await _loadCredits();
    await _loadNotifications();
  }

  /// Cargar créditos
  Future<void> _loadCredits() async {
    final credits = await _apiService.getCredits();
    if (credits != null) {
      _credits = List<Map<String, dynamic>>.from(credits);
      notifyListeners();
    }
  }

  /// Cargar notificaciones
  Future<void> _loadNotifications() async {
    final notifications = await _apiService.getNotifications();
    if (notifications != null) {
      _notifications = List<Map<String, dynamic>>.from(notifications);
      notifyListeners();
    }
  }

  /// Registrar pago
  Future<bool> registerPayment({
    required String creditId,
    required double amount,
    required String paymentMethod,
    String? notes,
  }) async {
    final result = await _apiService.registerPayment(
      creditId: creditId,
      amount: amount,
      paymentMethod: paymentMethod,
      notes: notes,
    );

    if (result != null) {
      // Recargar créditos después del pago
      await _loadCredits();
      return true;
    }
    return false;
  }

  /// Enviar ubicación
  void sendLocationUpdate(double latitude, double longitude) {
    _wsService.sendLocationUpdate(latitude, longitude);
  }

  /// Enviar mensaje
  void sendMessage(String toUserId, String message) {
    _wsService.sendMessage(toUserId, message);
  }

  /// Cerrar sesión
  void logout() {
    _wsService.disconnect();
    _currentUser = null;
    _isConnected = false;
    _notifications.clear();
    _credits.clear();
    notifyListeners();
  }
}
```

## 5. Ejemplo de uso en Widget

```dart
// lib/screens/dashboard_screen.dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/app_provider.dart';

class DashboardScreen extends StatefulWidget {
  @override
  _DashboardScreenState createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  @override
  Widget build(BuildContext context) {
    return Consumer<AppProvider>(
      builder: (context, appProvider, child) {
        return Scaffold(
          appBar: AppBar(
            title: Text('Dashboard'),
            actions: [
              // Indicador de conexión WebSocket
              Container(
                margin: EdgeInsets.only(right: 16),
                child: Row(
                  children: [
                    Icon(
                      appProvider.isConnected ? Icons.wifi : Icons.wifi_off,
                      color: appProvider.isConnected ? Colors.green : Colors.red,
                    ),
                    SizedBox(width: 4),
                    Text(
                      appProvider.isConnected ? 'Conectado' : 'Desconectado',
                      style: TextStyle(fontSize: 12),
                    ),
                  ],
                ),
              ),
              // Badge de notificaciones
              Stack(
                children: [
                  IconButton(
                    icon: Icon(Icons.notifications),
                    onPressed: () => _showNotifications(context, appProvider),
                  ),
                  if (appProvider.notifications.isNotEmpty)
                    Positioned(
                      right: 8,
                      top: 8,
                      child: Container(
                        padding: EdgeInsets.all(2),
                        decoration: BoxDecoration(
                          color: Colors.red,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        constraints: BoxConstraints(minWidth: 16, minHeight: 16),
                        child: Text(
                          '${appProvider.notifications.length}',
                          style: TextStyle(color: Colors.white, fontSize: 10),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ),
                ],
              ),
            ],
          ),
          body: Column(
            children: [
              // Información del usuario
              Card(
                margin: EdgeInsets.all(16),
                child: Padding(
                  padding: EdgeInsets.all(16),
                  child: Row(
                    children: [
                      CircleAvatar(
                        radius: 30,
                        child: Text(
                          appProvider.currentUser?['name']?.substring(0, 1) ?? 'U',
                          style: TextStyle(fontSize: 24),
                        ),
                      ),
                      SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              appProvider.currentUser?['name'] ?? 'Usuario',
                              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                            ),
                            Text(
                              appProvider.currentUser?['email'] ?? '',
                              style: TextStyle(color: Colors.grey[600]),
                            ),
                            Text(
                              'Rol: ${appProvider.currentUser?['role'] ?? 'N/A'}',
                              style: TextStyle(
                                color: Colors.blue,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              
              // Lista de créditos
              Expanded(
                child: ListView.builder(
                  padding: EdgeInsets.symmetric(horizontal: 16),
                  itemCount: appProvider.credits.length,
                  itemBuilder: (context, index) {
                    final credit = appProvider.credits[index];
                    return Card(
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: _getStatusColor(credit['status']),
                          child: Icon(Icons.credit_card, color: Colors.white),
                        ),
                        title: Text(
                          'Crédito #${credit['id']}',
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                        subtitle: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Cliente: ${credit['client_name'] ?? 'N/A'}'),
                            Text('Monto: ${credit['amount']} Bs'),
                            Text('Estado: ${credit['status']}'),
                          ],
                        ),
                        trailing: IconButton(
                          icon: Icon(Icons.payment),
                          onPressed: () => _showPaymentDialog(context, appProvider, credit),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
          floatingActionButton: FloatingActionButton(
            onPressed: () => _sendTestMessage(appProvider),
            child: Icon(Icons.send),
            tooltip: 'Enviar mensaje de prueba',
          ),
        );
      },
    );
  }

  Color _getStatusColor(String? status) {
    switch (status) {
      case 'active':
        return Colors.green;
      case 'pending':
        return Colors.orange;
      case 'overdue':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  void _showNotifications(BuildContext context, AppProvider appProvider) {
    showModalBottomSheet(
      context: context,
      builder: (context) => Container(
        height: 400,
        child: Column(
          children: [
            Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Notificaciones',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
            ),
            Expanded(
              child: ListView.builder(
                itemCount: appProvider.notifications.length,
                itemBuilder: (context, index) {
                  final notification = appProvider.notifications[index];
                  return ListTile(
                    leading: Icon(_getNotificationIcon(notification['type'])),
                    title: Text(notification['title'] ?? 'Notificación'),
                    subtitle: Text(notification['message'] ?? ''),
                    trailing: Text(
                      _formatTime(notification['timestamp']),
                      style: TextStyle(fontSize: 12, color: Colors.grey),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  IconData _getNotificationIcon(String? type) {
    switch (type) {
      case 'payment':
        return Icons.payment;
      case 'credit':
        return Icons.credit_card;
      case 'message':
        return Icons.message;
      default:
        return Icons.notifications;
    }
  }

  String _formatTime(String? timestamp) {
    if (timestamp == null) return '';
    final date = DateTime.parse(timestamp);
    return '${date.hour}:${date.minute.toString().padLeft(2, '0')}';
  }

  void _showPaymentDialog(BuildContext context, AppProvider appProvider, Map<String, dynamic> credit) {
    final amountController = TextEditingController();
    String paymentMethod = 'cash';

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Registrar Pago'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: amountController,
              decoration: InputDecoration(labelText: 'Monto'),
              keyboardType: TextInputType.number,
            ),
            SizedBox(height: 16),
            DropdownButtonFormField<String>(
              value: paymentMethod,
              decoration: InputDecoration(labelText: 'Método de Pago'),
              items: [
                DropdownMenuItem(value: 'cash', child: Text('Efectivo')),
                DropdownMenuItem(value: 'transfer', child: Text('Transferencia')),
                DropdownMenuItem(value: 'card', child: Text('Tarjeta')),
              ],
              onChanged: (value) => paymentMethod = value ?? 'cash',
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text('Cancelar'),
          ),
          ElevatedButton(
            onPressed: () async {
              final amount = double.tryParse(amountController.text);
              if (amount != null && amount > 0) {
                final success = await appProvider.registerPayment(
                  creditId: credit['id'].toString(),
                  amount: amount,
                  paymentMethod: paymentMethod,
                );
                
                Navigator.pop(context);
                
                if (success) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Pago registrado exitosamente')),
                  );
                } else {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Error al registrar el pago')),
                  );
                }
              }
            },
            child: Text('Registrar'),
          ),
        ],
      ),
    );
  }

  void _sendTestMessage(AppProvider appProvider) {
    appProvider.sendMessage('1', 'Mensaje de prueba desde Flutter');
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Mensaje enviado')),
    );
  }
}
```

## 6. Main.dart

```dart
// lib/main.dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'providers/app_provider.dart';
import 'screens/login_screen.dart';
import 'screens/dashboard_screen.dart';

void main() {
  runApp(MyApp());
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AppProvider()),
      ],
      child: MaterialApp(
        title: 'Cobrador App',
        theme: ThemeData(
          primarySwatch: Colors.blue,
          visualDensity: VisualDensity.adaptivePlatformDensity,
        ),
        home: AuthWrapper(),
      ),
    );
  }
}

class AuthWrapper extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Consumer<AppProvider>(
      builder: (context, appProvider, child) {
        if (appProvider.currentUser != null) {
          return DashboardScreen();
        } else {
          return LoginScreen();
        }
      },
    );
  }
}
```
