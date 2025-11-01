# 📱 Guía Rápida - Estadísticas en Tiempo Real en Flutter

## 1️⃣ Instalación de dependencias

```yaml
# pubspec.yaml
dependencies:
  flutter:
    sdk: flutter
  socket_io_client: ^2.0.1
  provider: ^6.0.0  # O tu state management preferido
```

```bash
flutter pub get
```

## 2️⃣ Crear servicio de Socket.IO

```dart
// lib/services/socket_service.dart
import 'package:socket_io_client/socket_io_client.dart' as IO;
import 'package:flutter/foundation.dart';

class SocketService {
  late IO.Socket socket;

  // Callbacks para estadísticas
  Function(Map<String, dynamic>)? onGlobalStatsUpdated;
  Function(Map<String, dynamic>)? onCobradorStatsUpdated;
  Function(Map<String, dynamic>)? onManagerStatsUpdated;

  final String socketUrl = 'http://TU_WEBSOCKET_SERVER:3001';

  void connect() {
    socket = IO.io(socketUrl, IO.SocketIoClientOption(
      'reconnection', true,
      'reconnectionDelay', 1000,
      'reconnectionDelayMax', 5000,
      'reconnectionAttempts', 5,
      'transports', ['websocket', 'polling'],
    ));

    // Eventos de conexión
    socket.onConnect((_) {
      if (kDebugMode) {
        print('✅ Conectado al WebSocket');
      }
    });

    socket.on('disconnect', (_) {
      if (kDebugMode) {
        print('❌ Desconectado del WebSocket');
      }
    });

    socket.onError((data) {
      if (kDebugMode) {
        print('❌ Error WebSocket: $data');
      }
    });

    // Escuchar eventos de estadísticas
    _setupStatsListeners();
  }

  void _setupStatsListeners() {
    // Estadísticas globales
    socket.on('stats.global.updated', (data) {
      if (kDebugMode) {
        print('📊 Global stats: $data');
      }
      onGlobalStatsUpdated?.call(data);
    });

    // Estadísticas del cobrador
    socket.on('stats.cobrador.updated', (data) {
      if (kDebugMode) {
        print('📊 Cobrador stats: $data');
      }
      onCobradorStatsUpdated?.call(data);
    });

    // Estadísticas del manager
    socket.on('stats.manager.updated', (data) {
      if (kDebugMode) {
        print('📊 Manager stats: $data');
      }
      onManagerStatsUpdated?.call(data);
    });
  }

  void disconnect() {
    socket.disconnect();
  }
}

// Singleton
final socketService = SocketService();
```

## 3️⃣ Crear modelo de datos

```dart
// lib/models/stats_model.dart
class GlobalStats {
  final int totalClients;
  final int totalCobradores;
  final int totalManagers;
  final int totalCredits;
  final int totalPayments;
  final int overduePayments;
  final int pendingPayments;
  final double totalBalance;
  final double todayCollections;
  final double monthCollections;
  final DateTime updatedAt;

  GlobalStats({
    required this.totalClients,
    required this.totalCobradores,
    required this.totalManagers,
    required this.totalCredits,
    required this.totalPayments,
    required this.overduePayments,
    required this.pendingPayments,
    required this.totalBalance,
    required this.todayCollections,
    required this.monthCollections,
    required this.updatedAt,
  });

  factory GlobalStats.fromJson(Map<String, dynamic> json) {
    return GlobalStats(
      totalClients: json['total_clients'] ?? 0,
      totalCobradores: json['total_cobradores'] ?? 0,
      totalManagers: json['total_managers'] ?? 0,
      totalCredits: json['total_credits'] ?? 0,
      totalPayments: json['total_payments'] ?? 0,
      overduePayments: json['overdue_payments'] ?? 0,
      pendingPayments: json['pending_payments'] ?? 0,
      totalBalance: (json['total_balance'] ?? 0).toDouble(),
      todayCollections: (json['today_collections'] ?? 0).toDouble(),
      monthCollections: (json['month_collections'] ?? 0).toDouble(),
      updatedAt: DateTime.parse(json['updated_at'] ?? DateTime.now().toIso8601String()),
    );
  }
}

class CobradorStats {
  final int cobradorId;
  final int totalClients;
  final int totalCredits;
  final int totalPayments;
  final int overduePayments;
  final int pendingPayments;
  final double totalBalance;
  final double todayCollections;
  final double monthCollections;
  final DateTime updatedAt;

  CobradorStats({
    required this.cobradorId,
    required this.totalClients,
    required this.totalCredits,
    required this.totalPayments,
    required this.overduePayments,
    required this.pendingPayments,
    required this.totalBalance,
    required this.todayCollections,
    required this.monthCollections,
    required this.updatedAt,
  });

  factory CobradorStats.fromJson(Map<String, dynamic> json) {
    return CobradorStats(
      cobradorId: json['cobrador_id'] ?? 0,
      totalClients: json['total_clients'] ?? 0,
      totalCredits: json['total_credits'] ?? 0,
      totalPayments: json['total_payments'] ?? 0,
      overduePayments: json['overdue_payments'] ?? 0,
      pendingPayments: json['pending_payments'] ?? 0,
      totalBalance: (json['total_balance'] ?? 0).toDouble(),
      todayCollections: (json['today_collections'] ?? 0).toDouble(),
      monthCollections: (json['month_collections'] ?? 0).toDouble(),
      updatedAt: DateTime.parse(json['updated_at'] ?? DateTime.now().toIso8601String()),
    );
  }
}

class ManagerStats {
  final int managerId;
  final int totalCobradores;
  final int totalCredits;
  final int totalPayments;
  final int overduePayments;
  final int pendingPayments;
  final double totalBalance;
  final double todayCollections;
  final double monthCollections;
  final DateTime updatedAt;

  ManagerStats({
    required this.managerId,
    required this.totalCobradores,
    required this.totalCredits,
    required this.totalPayments,
    required this.overduePayments,
    required this.pendingPayments,
    required this.totalBalance,
    required this.todayCollections,
    required this.monthCollections,
    required this.updatedAt,
  });

  factory ManagerStats.fromJson(Map<String, dynamic> json) {
    return ManagerStats(
      managerId: json['manager_id'] ?? 0,
      totalCobradores: json['total_cobradores'] ?? 0,
      totalCredits: json['total_credits'] ?? 0,
      totalPayments: json['total_payments'] ?? 0,
      overduePayments: json['overdue_payments'] ?? 0,
      pendingPayments: json['pending_payments'] ?? 0,
      totalBalance: (json['total_balance'] ?? 0).toDouble(),
      todayCollections: (json['today_collections'] ?? 0).toDouble(),
      monthCollections: (json['month_collections'] ?? 0).toDouble(),
      updatedAt: DateTime.parse(json['updated_at'] ?? DateTime.now().toIso8601String()),
    );
  }
}
```

## 4️⃣ Crear Provider (Estado)

```dart
// lib/providers/stats_provider.dart
import 'package:flutter/foundation.dart';
import 'package:provider/provider.dart';
import '../models/stats_model.dart';
import '../services/socket_service.dart';

class StatsProvider extends ChangeNotifier {
  GlobalStats? _globalStats;
  CobradorStats? _cobradorStats;
  ManagerStats? _managerStats;
  bool _isLoading = false;

  // Getters
  GlobalStats? get globalStats => _globalStats;
  CobradorStats? get cobradorStats => _cobradorStats;
  ManagerStats? get managerStats => _managerStats;
  bool get isLoading => _isLoading;

  StatsProvider() {
    _initializeSocketListeners();
  }

  void _initializeSocketListeners() {
    socketService.onGlobalStatsUpdated = (data) {
      final stats = data['stats'] as Map<String, dynamic>;
      _globalStats = GlobalStats.fromJson(stats);
      notifyListeners();
    };

    socketService.onCobradorStatsUpdated = (data) {
      final stats = data['stats'] as Map<String, dynamic>;
      _cobradorStats = CobradorStats.fromJson(stats);
      notifyListeners();
    };

    socketService.onManagerStatsUpdated = (data) {
      final stats = data['stats'] as Map<String, dynamic>;
      _managerStats = ManagerStats.fromJson(stats);
      notifyListeners();
    };
  }

  void connect() {
    _isLoading = true;
    notifyListeners();

    socketService.connect();

    _isLoading = false;
    notifyListeners();
  }

  void disconnect() {
    socketService.disconnect();
  }

  @override
  void dispose() {
    disconnect();
    super.dispose();
  }
}
```

## 5️⃣ Usar en la App

### Opción A: Provider

```dart
// lib/main.dart
void main() {
  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => StatsProvider()),
        // ... otros providers
      ],
      child: const MyApp(),
    ),
  );
}

class MyApp extends StatelessWidget {
  const MyApp({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    // Conectar al WebSocket cuando la app inicia
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<StatsProvider>().connect();
    });

    return MaterialApp(
      home: DashboardScreen(),
    );
  }
}
```

### Opción B: GetX

```dart
// lib/controllers/stats_controller.dart
import 'package:get/get.dart';
import '../models/stats_model.dart';
import '../services/socket_service.dart';

class StatsController extends GetxController {
  final globalStats = Rx<GlobalStats?>(null);
  final cobradorStats = Rx<CobradorStats?>(null);
  final managerStats = Rx<ManagerStats?>(null);
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    connect();
    _setupSocketListeners();
  }

  void _setupSocketListeners() {
    socketService.onGlobalStatsUpdated = (data) {
      final stats = data['stats'] as Map<String, dynamic>;
      globalStats.value = GlobalStats.fromJson(stats);
    };

    socketService.onCobradorStatsUpdated = (data) {
      final stats = data['stats'] as Map<String, dynamic>;
      cobradorStats.value = CobradorStats.fromJson(stats);
    };

    socketService.onManagerStatsUpdated = (data) {
      final stats = data['stats'] as Map<String, dynamic>;
      managerStats.value = ManagerStats.fromJson(stats);
    };
  }

  void connect() {
    isLoading.value = true;
    socketService.connect();
    isLoading.value = false;
  }

  @override
  void onClose() {
    socketService.disconnect();
    super.onClose();
  }
}

// Usar en main.dart
void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    Get.put(StatsController());

    return GetMaterialApp(
      home: DashboardScreen(),
    );
  }
}
```

## 6️⃣ Componentes UI

### Con Provider:

```dart
// lib/screens/dashboard_screen.dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/stats_provider.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard - Estadísticas en Tiempo Real'),
      ),
      body: Consumer<StatsProvider>(
        builder: (context, statsProvider, child) {
          if (statsProvider.isLoading) {
            return const Center(
              child: CircularProgressIndicator(),
            );
          }

          final stats = statsProvider.globalStats;

          if (stats == null) {
            return const Center(
              child: Text('Esperando datos...'),
            );
          }

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // Card de estadísticas
              StatsCard(
                title: 'Clientes Totales',
                value: '${stats.totalClients}',
                icon: Icons.people,
                color: Colors.blue,
              ),
              StatsCard(
                title: 'Cobros Hoy',
                value: '\$${stats.todayCollections.toStringAsFixed(2)}',
                icon: Icons.money,
                color: Colors.green,
              ),
              StatsCard(
                title: 'Balance Activo',
                value: '\$${stats.totalBalance.toStringAsFixed(2)}',
                icon: Icons.account_balance,
                color: Colors.orange,
              ),
              StatsCard(
                title: 'Pagos Atrasados',
                value: '${stats.overduePayments}',
                icon: Icons.warning,
                color: Colors.red,
              ),

              // Sección de créditos y pagos
              SizedBox(height: 20),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Resumen General',
                        style: Theme.of(context).textTheme.headline6,
                      ),
                      SizedBox(height: 16),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceAround,
                        children: [
                          _StatItem(
                            label: 'Créditos',
                            value: '${stats.totalCredits}',
                          ),
                          _StatItem(
                            label: 'Pagos',
                            value: '${stats.totalPayments}',
                          ),
                          _StatItem(
                            label: 'Cobradores',
                            value: '${stats.totalCobradores}',
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),

              // Timestamp
              SizedBox(height: 20),
              Center(
                child: Text(
                  'Actualizado: ${stats.updatedAt.toLocal().toString().split('.')[0]}',
                  style: Theme.of(context).textTheme.caption,
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class StatsCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;
  final Color color;

  const StatsCard({
    Key? key,
    required this.title,
    required this.value,
    required this.icon,
    required this.color,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 8),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: color.withOpacity(0.2),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(icon, color: color, size: 28),
            ),
            SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: Theme.of(context).textTheme.caption,
                  ),
                  Text(
                    value,
                    style: Theme.of(context).textTheme.headline6,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatItem extends StatelessWidget {
  final String label;
  final String value;

  const _StatItem({
    Key? key,
    required this.label,
    required this.value,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          value,
          style: Theme.of(context).textTheme.headline5,
        ),
        SizedBox(height: 4),
        Text(
          label,
          style: Theme.of(context).textTheme.caption,
        ),
      ],
    );
  }
}
```

## 7️⃣ Pantalla de Cobrador

```dart
// lib/screens/cobrador_dashboard_screen.dart
class CobradorDashboardScreen extends StatelessWidget {
  const CobradorDashboardScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Mi Dashboard'),
      ),
      body: Consumer<StatsProvider>(
        builder: (context, statsProvider, child) {
          final stats = statsProvider.cobradorStats;

          if (stats == null) {
            return const Center(
              child: Text('Cargando datos del cobrador...'),
            );
          }

          return Padding(
            padding: const EdgeInsets.all(16),
            child: SingleChildScrollView(
              child: Column(
                children: [
                  StatsCard(
                    title: 'Mis Clientes',
                    value: '${stats.totalClients}',
                    icon: Icons.people,
                    color: Colors.blue,
                  ),
                  StatsCard(
                    title: 'Cobros Hoy',
                    value: '\$${stats.todayCollections.toStringAsFixed(2)}',
                    icon: Icons.money,
                    color: Colors.green,
                  ),
                  StatsCard(
                    title: 'Balance Pendiente',
                    value: '\$${stats.totalBalance.toStringAsFixed(2)}',
                    icon: Icons.account_balance,
                    color: Colors.orange,
                  ),
                  StatsCard(
                    title: 'Pagos Atrasados',
                    value: '${stats.overduePayments}',
                    icon: Icons.warning,
                    color: Colors.red,
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
```

## ✅ Resumen Rápido

| Paso | Qué hacer |
|------|-----------|
| 1 | Agregar `socket_io_client` y `provider` a `pubspec.yaml` |
| 2 | Crear `SocketService` para conectarse al WebSocket |
| 3 | Crear modelos de datos (`GlobalStats`, `CobradorStats`, `ManagerStats`) |
| 4 | Crear `StatsProvider` para manejar estado |
| 5 | Inicializar `StatsProvider` en `main.dart` |
| 6 | Usar `Consumer<StatsProvider>` en widgets |
| 7 | Mostrar datos en tiempo real |

## 🚀 Flujo en Flutter

```
1. App inicia
   ↓
2. StatsProvider.connect() → SocketService.connect()
   ↓
3. Socket.IO se conecta a http://WEBSOCKET_SERVER:3001
   ↓
4. Escucha eventos:
   - stats.global.updated
   - stats.cobrador.updated
   - stats.manager.updated
   ↓
5. Cuando evento llega:
   - StatsProvider actualiza estado
   - notifyListeners()
   - UI se reconstruye automáticamente
   ↓
6. Datos en pantalla se actualizan en tiempo real ✨
```

## 📝 Configuración de WebSocket URL

**En desarrollo:**
```dart
final String socketUrl = 'http://192.168.5.44:3001';
```

**En producción:**
```dart
final String socketUrl = 'https://tu-dominio.com:3001';
```

---

¡Listo! Con esto tu app Flutter tendrá estadísticas actualizadas en tiempo real sin necesidad de hacer polling.
