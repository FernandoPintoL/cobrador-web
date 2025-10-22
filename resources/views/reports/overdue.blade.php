<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Mora</title>
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

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
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
            font-size: 11px;
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

        .severity-light { background-color: #fff3cd; }
        .severity-moderate { background-color: #ffecb5; }
        .severity-severe { background-color: #f8d7da; }

    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Mora</h1>
        <p>Generado el: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
        <p>Por: {{ $generated_by }}</p>
    </div>

    <div class="summary">
        <h3>Resumen General</h3>
        <div class="summary-grid">
            <div>
                <p><strong>Total créditos en mora:</strong> {{ $summary['total_overdue_credits'] }}</p>
                <p><strong>Monto total en mora:</strong> Bs {{ number_format($summary['total_overdue_amount'], 2) }}</p>
                <p><strong>Balance total en mora:</strong> Bs {{ number_format($summary['total_balance_overdue'], 2) }}</p>
            </div>
            <div>
                <p><strong>Promedio días en mora:</strong> {{ number_format($summary['average_days_overdue'], 0) }} días</p>
                <p><strong>Máximo días en mora:</strong> {{ $summary['max_days_overdue'] }} días</p>
                <p><strong>Mínimo días en mora:</strong> {{ $summary['min_days_overdue'] }} días</p>
            </div>
        </div>

        <h4>Distribución por Gravedad</h4>
        <p><strong>Leve (1-7 días):</strong> {{ $summary['by_severity']['light'] }} créditos</p>
        <p><strong>Moderada (8-30 días):</strong> {{ $summary['by_severity']['moderate'] }} créditos</p>
        <p><strong>Severa (+30 días):</strong> {{ $summary['by_severity']['severe'] }} créditos</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Categoría</th>
                <th>Cobrador</th>
                <th>Días Mora</th>
                <th>Monto Mora</th>
                <th>Balance</th>
                <th>Cuotas Atrasadas</th>
                <th>% Completado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($credits as $credit)
            <tr class="
                @if($credit->days_overdue <= 7) severity-light
                @elseif($credit->days_overdue <= 30) severity-moderate
                @else severity-severe
                @endif
            ">
                <td>{{ $credit->id }}</td>
                <td>{{ $credit->client->name ?? 'N/A' }}</td>
                <td>{{ $credit->client->client_category ?? 'N/A' }}</td>
                <td>{{ $credit->deliveredBy->name ?? $credit->createdBy->name ?? 'N/A' }}</td>
                <td>{{ $credit->days_overdue }}</td>
                <td>Bs {{ number_format($credit->overdue_amount, 2) }}</td>
                <td>Bs {{ number_format($credit->balance, 2) }}</td>
                <td>{{ $credit->overdue_installments }}</td>
                <td>{{ $credit->completion_rate }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado por el Sistema de Cobrador</p>
    </div>
</body>
</html>
