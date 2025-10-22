<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Comisiones</title>
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

        .highlight {
            background-color: #d4edda;
            font-weight: bold;
        }

    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Comisiones</h1>
        <p>Generado el: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
        <p>Por: {{ $generated_by }}</p>
    </div>

    <div class="summary">
        <h3>Resumen del Período</h3>
        <p><strong>Período:</strong> {{ $summary['period']['start'] }} - {{ $summary['period']['end'] }}</p>
        <p><strong>Tasa de comisión:</strong> {{ $summary['commission_rate'] }}%</p>
        <p><strong>Total cobradores:</strong> {{ $summary['total_cobradores'] }}</p>
        <p><strong>Total cobrado:</strong> Bs {{ number_format($summary['totals']['collected'], 2) }}</p>
        <p><strong>Total prestado:</strong> Bs {{ number_format($summary['totals']['lent'], 2) }}</p>
        <p><strong>Total comisiones:</strong> Bs {{ number_format($summary['totals']['commissions'], 2) }}</p>
        <p><strong>Total bonos:</strong> Bs {{ number_format($summary['totals']['bonuses'], 2) }}</p>

        <h4>Top 5 Mejores Cobradores</h4>
        @foreach($summary['top_earners'] as $index => $earner)
        <p>{{ $index + 1 }}. <strong>{{ $earner['name'] }}</strong> -
           Comisión: Bs {{ number_format($earner['commission'], 2) }}
           ({{ $earner['collection_percentage'] }}% cumplimiento)</p>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                <th>Cobrador</th>
                <th>Pagos Cobrados</th>
                <th>Monto Cobrado</th>
                <th>Créd. Entregados</th>
                <th>Monto Prestado</th>
                <th>Tasa</th>
                <th>Comisión Base</th>
                <th>Bonus</th>
                <th>Comisión Total</th>
                <th>Cobro Esperado</th>
                <th>% Cumplimiento</th>
            </tr>
        </thead>
        <tbody>
            @foreach($commissions as $item)
            <tr class="{{ $item['performance']['collection_percentage'] >= 80 ? 'highlight' : '' }}">
                <td>{{ $item['cobrador_name'] }}</td>
                <td>{{ $item['payments_collected']['count'] }}</td>
                <td>Bs {{ number_format($item['payments_collected']['total_amount'], 2) }}</td>
                <td>{{ $item['credits_delivered']['count'] }}</td>
                <td>Bs {{ number_format($item['credits_delivered']['total_amount'], 2) }}</td>
                <td>{{ $item['commission']['rate'] }}%</td>
                <td>Bs {{ number_format($item['commission']['on_collection'], 2) }}</td>
                <td>Bs {{ number_format($item['commission']['bonus'], 2) }}</td>
                <td><strong>Bs {{ number_format($item['commission']['total'], 2) }}</strong></td>
                <td>Bs {{ number_format($item['performance']['expected_collection'], 2) }}</td>
                <td>{{ number_format($item['performance']['collection_percentage'], 2) }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado por el Sistema de Cobrador</p>
        <p style="margin-top: 10px; font-size: 10px;">
            <strong>Nota:</strong> El bonus del 20% se otorga a cobradores con cumplimiento ≥ 80% (resaltados en verde)
        </p>
    </div>
</body>
</html>
