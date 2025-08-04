import 'package:flutter/material.dart';
import 'FlutterWebSocketService.dart';

/// Ejemplo de uso del WebSocket en Flutter
class WebSocketExample extends StatefulWidget {
  const WebSocketExample({Key? key}) : super(key: key);

  @override
  State<WebSocketExample> createState() => _WebSocketExampleState();
}

class _WebSocketExampleState extends State<WebSocketExample> {
  final FlutterWebSocketService _wsService = FlutterWebSocketService();
  bool _isConnected = false;
  String _connectionStatus = 'Desconectado';
  List<Map<String, dynamic>> _notifications = [];

  // Controladores para los formularios
  final TextEditingController _serverUrlController = TextEditingController();
  final TextEditingController _userIdController = TextEditingController();
  final TextEditingController _userNameController = TextEditingController();
  String _selectedUserType = 'client';

  @override
  void initState() {
    super.initState();
    _initializeWebSocket();
  }

  void _initializeWebSocket() {
    // Configurar URL del servidor
    _serverUrlController.text = _wsService.serverUrl;

    // Escuchar cambios de conexión
    _wsService.connectionStream.listen((connected) {
      setState(() {
        _isConnected = connected;
        _connectionStatus = connected ? 'Conectado' : 'Desconectado';
      });
    });

    // Escuchar notificaciones
    _wsService.notificationStream.listen((notification) {
      setState(() {
        _notifications.insert(0, {
          ...notification,
          'receivedAt': DateTime.now().toString(),
        });

        // Mantener solo las últimas 20 notificaciones
        if (_notifications.length > 20) {
          _notifications.removeLast();
        }
      });

      // Mostrar snackbar
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${notification['title']}: ${notification['message']}',
            ),
            backgroundColor: _getNotificationColor(notification['type']),
            duration: const Duration(seconds: 3),
          ),
        );
      }
    });

    // Escuchar otros eventos
    _wsService.paymentStream.listen((payment) {
      print('Pago recibido: $payment');
    });

    _wsService.messageStream.listen((message) {
      print('Mensaje recibido: $message');
    });

    _wsService.locationStream.listen((location) {
      print('Ubicación actualizada: $location');
    });
  }

  Color _getNotificationColor(String? type) {
    switch (type) {
      case 'credit':
        return Colors.green;
      case 'urgent':
        return Colors.red;
      case 'payment':
        return Colors.blue;
      case 'route':
        return Colors.orange;
      default:
        return Colors.grey;
    }
  }

  Future<void> _connect() async {
    final url = _serverUrlController.text.trim();
    if (url.isEmpty) {
      _showMessage('Por favor ingresa la URL del servidor');
      return;
    }

    _wsService.configureServer(url: url);

    setState(() {
      _connectionStatus = 'Conectando...';
    });

    final success = await _wsService.connect();

    if (success) {
      _showMessage('Conectado exitosamente');
    } else {
      setState(() {
        _connectionStatus = 'Error de conexión';
      });
      _showMessage('Error al conectar');
    }
  }

  Future<void> _authenticate() async {
    if (!_isConnected) {
      _showMessage('Primero debes conectarte al servidor');
      return;
    }

    final userId = _userIdController.text.trim();
    final userName = _userNameController.text.trim();

    if (userId.isEmpty || userName.isEmpty) {
      _showMessage('Completa todos los campos');
      return;
    }

    final success = await _wsService.authenticate(
      userId: userId,
      userName: userName,
      userType: _selectedUserType,
    );

    if (success) {
      _showMessage('Autenticado exitosamente');
    } else {
      _showMessage('Error en autenticación');
    }
  }

  void _disconnect() {
    _wsService.disconnect();
    _showMessage('Desconectado del servidor');
  }

  void _sendTestNotification() {
    if (!_isConnected) {
      _showMessage('No hay conexión activa');
      return;
    }

    _wsService.sendCreditNotification(
      targetUserId: _userIdController.text,
      title: 'Notificación de Prueba',
      message: 'Esta es una notificación de prueba desde Flutter',
      type: 'credit',
    );

    _showMessage('Notificación enviada');
  }

  void _updateLocation() {
    if (!_isConnected) {
      _showMessage('No hay conexión activa');
      return;
    }

    // Ubicación simulada (Santa Cruz, Bolivia)
    _wsService.updateLocation(-17.783327, -63.182140);
    _showMessage('Ubicación actualizada');
  }

  void _showMessage(String message) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message), duration: const Duration(seconds: 2)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('WebSocket Cobrador'),
        backgroundColor: Colors.blue,
        foregroundColor: Colors.white,
        actions: [
          Container(
            margin: const EdgeInsets.only(right: 16),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: _isConnected ? Colors.green : Colors.red,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              _connectionStatus,
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.bold,
                fontSize: 12,
              ),
            ),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Sección de conexión
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      '🔌 Conexión WebSocket',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _serverUrlController,
                      decoration: const InputDecoration(
                        labelText: 'URL del Servidor',
                        hintText: 'http://192.168.1.100:3001',
                        border: OutlineInputBorder(),
                      ),
                      enabled: !_isConnected,
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: ElevatedButton(
                            onPressed: _isConnected ? null : _connect,
                            child: const Text('Conectar'),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: ElevatedButton(
                            onPressed: _isConnected ? _disconnect : null,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.red,
                              foregroundColor: Colors.white,
                            ),
                            child: const Text('Desconectar'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Sección de autenticación
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      '👤 Autenticación',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _userIdController,
                      decoration: const InputDecoration(
                        labelText: 'ID de Usuario',
                        hintText: '123',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _userNameController,
                      decoration: const InputDecoration(
                        labelText: 'Nombre de Usuario',
                        hintText: 'Juan Pérez',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      value: _selectedUserType,
                      decoration: const InputDecoration(
                        labelText: 'Tipo de Usuario',
                        border: OutlineInputBorder(),
                      ),
                      items: const [
                        DropdownMenuItem(
                          value: 'client',
                          child: Text('Cliente'),
                        ),
                        DropdownMenuItem(
                          value: 'cobrador',
                          child: Text('Cobrador'),
                        ),
                        DropdownMenuItem(value: 'admin', child: Text('Admin')),
                      ],
                      onChanged: (value) {
                        if (value != null) {
                          setState(() {
                            _selectedUserType = value;
                          });
                        }
                      },
                    ),
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: _isConnected ? _authenticate : null,
                        child: const Text('Autenticar'),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Sección de acciones
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      '⚡ Acciones de Prueba',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: _isConnected
                                ? _sendTestNotification
                                : null,
                            icon: const Icon(Icons.notifications),
                            label: const Text('Enviar Notificación'),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed:
                                _isConnected && _selectedUserType == 'cobrador'
                                ? _updateLocation
                                : null,
                            icon: const Icon(Icons.location_on),
                            label: const Text('Actualizar Ubicación'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Sección de notificaciones
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          '📧 Notificaciones Recibidas',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        if (_notifications.isNotEmpty)
                          TextButton(
                            onPressed: () {
                              setState(() {
                                _notifications.clear();
                              });
                            },
                            child: const Text('Limpiar'),
                          ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    if (_notifications.isEmpty)
                      const Center(
                        child: Text(
                          'No hay notificaciones',
                          style: TextStyle(
                            color: Colors.grey,
                            fontStyle: FontStyle.italic,
                          ),
                        ),
                      )
                    else
                      ListView.separated(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        itemCount: _notifications.length,
                        separatorBuilder: (context, index) => const Divider(),
                        itemBuilder: (context, index) {
                          final notification = _notifications[index];
                          return ListTile(
                            leading: Container(
                              width: 12,
                              height: 12,
                              decoration: BoxDecoration(
                                color: _getNotificationColor(
                                  notification['type'],
                                ),
                                shape: BoxShape.circle,
                              ),
                            ),
                            title: Text(
                              notification['title'] ?? 'Sin título',
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            subtitle: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(notification['message'] ?? 'Sin mensaje'),
                                const SizedBox(height: 4),
                                Text(
                                  'Recibido: ${notification['receivedAt']}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: Colors.grey,
                                  ),
                                ),
                              ],
                            ),
                            trailing: Chip(
                              label: Text(
                                notification['type'] ?? 'info',
                                style: const TextStyle(fontSize: 10),
                              ),
                              backgroundColor: _getNotificationColor(
                                notification['type'],
                              ).withOpacity(0.2),
                            ),
                          );
                        },
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    _wsService.dispose();
    _serverUrlController.dispose();
    _userIdController.dispose();
    _userNameController.dispose();
    super.dispose();
  }
}
