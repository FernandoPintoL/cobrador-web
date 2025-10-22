<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Rendimiento</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .summary {
            background: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 10px;
        }

        th {
            background-color: #f2f2f2;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }

    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Rendimiento</h1>
        <p>Generado el: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
        <p>Por: {{ $generated_by }}</p>
    </div>

    <div class="summary">
        <h3>Resumen del Período</h3>
        <p><strong>Período:</strong> {{ $summary['period']['start'] }} - {{ $summary['period']['end'] }}</p>
        <p><strong>Total cobradores:</strong> {{ $summary['total_cobradores'] }}</p>
        <p><strong>Créditos entregados:</strong> {{ $summary['totals']['credits_delivered'] }}</p>
        <p><strong>Monto prestado:</strong> Bs {{ number_format($summary['totals']['amount_lent'], 2) }}</p>
        <p><strong>Pagos cobrados:</strong> {{ $summary['totals']['payments_collected'] }}</p>
        <p><strong>Monto cobrado:</strong> Bs {{ number_format($summary['totals']['amount_collected'], 2) }}</p>
        <p><strong>Tasa promedio de cobranza:</strong> {{ number_format($summary['averages']['collection_rate'], 2) }}%</p>
        <p><strong>Calidad promedio de cartera:</strong> {{ number_format($summary['averages']['portfolio_quality'], 2) }}%</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Cobrador</th>
                <th>Manager</th>
                <th>Créd. Entregados</th>
                <th>Monto Prestado</th>
                <th>Pagos Cobrados</th>
                <th>Monto Cobrado</th>
                <th>Tasa Cobranza</th>
                <th>Créd. Activos</th>
                <th>Créd. Completados</th>
                <th>En Mora</th>
                <th>Calidad Cartera</th>
                <th>Días Prom.</th>
                <th>Score</th>
                <th>Clientes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($performance as $item)
            <tr>
                <td>{{ $item['cobrador_name'] }}</td>
                <td>{{ $item['manager_name'] }}</td>
                <td>{{ $item['metrics']['credits_delivered'] }}</td>
                <td>Bs {{ number_format($item['metrics']['total_amount_lent'], 2) }}</td>
                <td>{{ $item['metrics']['payments_collected_count'] }}</td>
                <td>Bs {{ number_format($item['metrics']['total_amount_collected'], 2) }}</td>
                <td>{{ $item['metrics']['collection_rate'] }}%</td>
                <td>{{ $item['metrics']['active_credits'] }}</td>
                <td>{{ $item['metrics']['completed_credits'] }}</td>
                <td>{{ $item['metrics']['overdue_credits'] }}</td>
                <td>{{ $item['metrics']['portfolio_quality'] }}%</td>
                <td>{{ number_format($item['metrics']['avg_days_to_complete'], 1) }}</td>
                <td>{{ number_format($item['metrics']['efficiency_score'], 2) }}</td>
                <td>{{ $item['metrics']['active_clients'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado por el Sistema de Cobrador</p>
    </div>
</body>
</html>
