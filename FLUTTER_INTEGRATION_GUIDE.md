# 📱 Guía de Integración Flutter - Reportes API

## 🎯 Resumen de Compatibilidad

✅ **COMPLETAMENTE COMPATIBLE** con Flutter
✅ **Múltiples formatos** soportados
✅ **Manejo de autenticación** incluido
✅ **Filtros avanzados** disponibles

## 📋 Endpoints Disponibles

### 1. **GET /api/reports/types**

- **Propósito**: Obtener lista de reportes disponibles
- **Formato**: JSON
- **Uso**: Para mostrar opciones al usuario

### 2. **GET /api/reports/payments**

- **Propósito**: Reporte de pagos
- **Formatos**: `json`, `pdf`, `html`
- **Filtros**: `start_date`, `end_date`, `cobrador_id`

### 3. **GET /api/reports/credits**

- **Propósito**: Reporte de créditos
- **Formatos**: `json`, `pdf`, `html`
- **Filtros**: `status`, `cobrador_id`, `client_id`

### 4. **GET /api/reports/users**

- **Propósito**: Reporte de usuarios
- **Formatos**: `json`, `pdf`, `html`
- **Filtros**: `role`, `client_category`

### 5. **GET /api/reports/balances**

- **Propósito**: Reporte de balances de efectivo
- **Formatos**: `json`, `pdf`, `html`
- **Filtros**: `start_date`, `end_date`, `cobrador_id`

## 🔧 Implementación en Flutter

### Configuración Base

```dart
class ApiService {
  final String baseUrl = 'https://tu-api.com';
  final String token = 'YOUR_BEARER_TOKEN';

  Future<Map<String, String>> get headers async => {
    'Authorization': 'Bearer $token',
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  };
}
```

### Manejo de Respuestas por Formato

#### 📄 Formato JSON (para mostrar datos)

```dart
Future<ReportData?> fetchReportData(String reportType, Map<String, dynamic> filters) async {
  final queryParams = {
    ...filters,
    'format': 'json',
  };

  final uri = Uri.parse('$baseUrl/api/reports/$reportType')
      .replace(queryParameters: queryParams.map((k, v) => MapEntry(k, v.toString())));

  final response = await http.get(uri, headers: await headers);

  if (response.statusCode == 200) {
    final jsonData = jsonDecode(response.body);
    return ReportData.fromJson(jsonData['data']);
  }
  return null;
}
```

#### 📕 Formato PDF (para descargar archivos)

```dart
Future<String?> downloadReportPDF(String reportType, Map<String, dynamic> filters) async {
  final queryParams = {
    ...filters,
    'format': 'pdf',
  };

  final uri = Uri.parse('$baseUrl/api/reports/$reportType')
      .replace(queryParameters: queryParams.map((k, v) => MapEntry(k, v.toString())));

  final response = await http.get(uri, headers: await headers);

  if (response.statusCode == 200) {
    final directory = await getApplicationDocumentsDirectory();
    final timestamp = DateTime.now().millisecondsSinceEpoch;
    final fileName = 'reporte-$reportType-$timestamp.pdf';
    final file = File('${directory.path}/$fileName');

    await file.writeAsBytes(response.bodyBytes);
    return file.path;
  }
  return null;
}
```

#### 🌐 Formato HTML (para vista previa)

```dart
Future<String?> getReportHTML(String reportType, Map<String, dynamic> filters) async {
  final queryParams = {
    ...filters,
    'format': 'html',
  };

  final uri = Uri.parse('$baseUrl/api/reports/$reportType')
      .replace(queryParameters: queryParams.map((k, v) => MapEntry(k, v.toString())));

  final response = await http.get(uri, headers: {
    ...await headers,
    'Accept': 'text/html',
  });

  if (response.statusCode == 200) {
    return response.body;
  }
  return null;
}
```

## 🎨 Ejemplo de UI en Flutter

```dart
class ReportsScreen extends StatefulWidget {
  @override
  _ReportsScreenState createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  List<dynamic> reportTypes = [];
  String selectedReport = '';
  Map<String, dynamic> filters = {};
  bool isLoading = false;

  @override
  void initState() {
    super.initState();
    loadReportTypes();
  }

  Future<void> loadReportTypes() async {
    setState(() => isLoading = true);
    final types = await ApiService().getReportTypes();
    setState(() {
      reportTypes = types ?? [];
      isLoading = false;
    });
  }

  Future<void> generateReport(String format) async {
    if (selectedReport.isEmpty) return;

    setState(() => isLoading = true);

    try {
      if (format == 'json') {
        final data = await ApiService().fetchReportData(selectedReport, filters);
        // Mostrar datos en la UI
        showReportData(data);
      } else if (format == 'pdf') {
        final filePath = await ApiService().downloadReportPDF(selectedReport, filters);
        // Abrir archivo PDF
        OpenFile.open(filePath);
      } else if (format == 'html') {
        final html = await ApiService().getReportHTML(selectedReport, filters);
        // Mostrar en WebView
        showHTMLPreview(html);
      }
    } finally {
      setState(() => isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Reportes')),
      body: Padding(
        padding: EdgeInsets.all(16),
        child: Column(
          children: [
            // Selector de tipo de reporte
            DropdownButton<String>(
              value: selectedReport,
              hint: Text('Seleccionar reporte'),
              items: reportTypes.map((type) {
                return DropdownMenuItem(
                  value: type['key'],
                  child: Text(type['name']),
                );
              }).toList(),
              onChanged: (value) => setState(() => selectedReport = value!),
            ),

            // Filtros dinámicos según el reporte seleccionado
            if (selectedReport.isNotEmpty) ...[
              SizedBox(height: 16),
              Text('Filtros', style: Theme.of(context).textTheme.headline6),
              // Agregar campos de filtro aquí
            ],

            // Botones de acción
            SizedBox(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                ElevatedButton.icon(
                  icon: Icon(Icons.table_chart),
                  label: Text('Ver Datos'),
                  onPressed: () => generateReport('json'),
                ),
                ElevatedButton.icon(
                  icon: Icon(Icons.picture_as_pdf),
                  label: Text('Descargar PDF'),
                  onPressed: () => generateReport('pdf'),
                ),
                ElevatedButton.icon(
                  icon: Icon(Icons.web),
                  label: Text('Vista Previa'),
                  onPressed: () => generateReport('html'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
```

## 📦 Dependencias Flutter Recomendadas

```yaml
dependencies:
  http: ^1.1.0
  path_provider: ^2.1.1
  open_file: ^3.3.2
  webview_flutter: ^4.2.0  # Para vista previa HTML
  intl: ^0.19.0  # Para formateo de fechas
```

## 🔒 Consideraciones de Seguridad

1. **Autenticación**: Siempre incluir Bearer token
2. **Validación**: Verificar respuestas de error (401, 403, 422)
3. **Permisos**: Los reportes respetan los permisos del usuario
4. **Rate Limiting**: Considerar límites de requests

## 🚀 Casos de Uso Típicos

### 📊 Dashboard con Datos en Tiempo Real

- Usar formato JSON para obtener datos
- Mostrar gráficos y estadísticas en la app

### 📄 Generación de Reportes para Clientes

- Descargar PDFs para compartir o imprimir
- Vista previa HTML antes de descargar

### 📈 Análisis de Datos

- Procesar datos JSON para crear gráficos personalizados
- Filtrar y ordenar datos según necesidades específicas

¡Los endpoints están completamente preparados para integración con Flutter! 🎉
