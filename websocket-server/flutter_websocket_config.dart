// URLs de conexión para Flutter - Generado automáticamente
class WebSocketConfig {
  static const String localUrl = 'http://localhost:3001';
  static const String networkUrl = 'http://192.168.5.44:3001';
  static const String emulatorUrl = 'http://10.0.2.2:3001';
  
  // Usar según el entorno
  static String getServerUrl(bool isEmulator, bool useNetwork) {
    if (isEmulator) return emulatorUrl;
    return useNetwork ? networkUrl : localUrl;
  }
}
