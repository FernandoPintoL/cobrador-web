// 📱 EJEMPLO DE USO EN FLUTTER

// 1. Obtener datos JSON para mostrar en la app
Future<void> getPaymentsReport() async {
  final response = await http.get(
    Uri.parse(
      'https://tu-api.com/api/reports/payments?format=json&start_date=2025-01-01&end_date=2025-12-31',
    ),
    headers: {
      'Authorization': 'Bearer YOUR_TOKEN',
      'Accept': 'application/json',
    },
  );

  if (response.statusCode == 200) {
    final data = jsonDecode(response.body);
    // Mostrar datos en la UI
    print('Total pagos: ${data['data']['summary']['total_payments']}');
    print('Monto total: ${data['data']['summary']['total_amount']}');
  }
}

// 2. Descargar PDF
Future<void> downloadPaymentsPDF() async {
  final response = await http.get(
    Uri.parse(
      'https://tu-api.com/api/reports/payments?format=pdf&cobrador_id=1',
    ),
    headers: {'Authorization': 'Bearer YOUR_TOKEN'},
  );

  if (response.statusCode == 200) {
    // Guardar archivo PDF
    final directory = await getApplicationDocumentsDirectory();
    final file = File('${directory.path}/reporte-pagos.pdf');
    await file.writeAsBytes(response.bodyBytes);
    print('PDF guardado en: ${file.path}');
  }
}

// 3. Mostrar reporte en WebView (para vista previa)
Future<void> showReportPreview() async {
  final response = await http.get(
    Uri.parse(
      'https://tu-api.com/api/reports/credits?format=html&status=active',
    ),
    headers: {'Authorization': 'Bearer YOUR_TOKEN', 'Accept': 'text/html'},
  );

  if (response.statusCode == 200) {
    // Mostrar HTML en WebView
    // webViewController.loadHtmlString(response.body);
  }
}

// 4. Obtener tipos de reportes disponibles
Future<void> getAvailableReports() async {
  final response = await http.get(
    Uri.parse('https://tu-api.com/api/reports/types'),
    headers: {
      'Authorization': 'Bearer YOUR_TOKEN',
      'Accept': 'application/json',
    },
  );

  if (response.statusCode == 200) {
    final data = jsonDecode(response.body);
    // Mostrar lista de reportes disponibles
    data['data'].forEach((key, report) {
      print('${report['name']}: ${report['description']}');
    });
  }
}
