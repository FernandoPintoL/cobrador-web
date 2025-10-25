<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Mora</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 12px;
            font-size: 9px;
        }

        .header {
            text-align: center;
            margin-bottom: 12px;
        }

        .header h1 {
            margin: 0;
            font-size: 16px;
        }

        .header p {
            margin: 2px 0;
            font-size: 8px;
        }

        .summary {
            background: #f5f5f5;
            padding: 8px;
            margin-bottom: 12px;
            border-radius: 5px;
        }

        .summary h3,
        .summary h4 {
            margin: 3px 0;
            font-size: 10px;
        }

        .summary p {
            margin: 2px 0;
            font-size: 8px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 8px;
        }

        th,
        td {
            border: 1px solid #999;
            padding: 3px 2px;
            text-align: left;
        }

        th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
            font-size: 7px;
        }

        .footer {
            margin-top: 12px;
            text-align: center;
            font-size: 8px;
            color: #666;
        }

        /* Overdue installments colors */
        .overdue-light { background-color: #fffacd; } /* Amarillo para 1-3 atrasos */
        .overdue-severe { background-color: #ffcccc; } /* Rojo para más de 3 atrasos */

        /* Days overdue colors */
        .severity-light { background-color: #fff3cd; }
        .severity-moderate { background-color: #ffe6e6; }
        .severity-severe { background-color: #ffcccc; }

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
                <th>Monto Vencido</th>
                <th>Balance Total</th>
                <th>Cuotas Vencidas</th>
                <th>Cuotas Totales</th>
                <th>% Completado</th>
                <th>Monto Original</th>
                <th>Monto con Interés</th>
            </tr>
        </thead>
        <tbody>
            @foreach($credits as $credit)
            @php
                $overdueDaysClass = '';
                if ($credit->days_overdue <= 7) {
                    $overdueDaysClass = 'severity-light';
                } elseif ($credit->days_overdue <= 30) {
                    $overdueDaysClass = 'severity-moderate';
                } else {
                    $overdueDaysClass = 'severity-severe';
                }

                $overdueInstallmentsClass = '';
                if ($credit->overdue_installments >= 1 && $credit->overdue_installments <= 3) {
                    $overdueInstallmentsClass = 'overdue-light';
                } elseif ($credit->overdue_installments > 3) {
                    $overdueInstallmentsClass = 'overdue-severe';
                }

                $finalClass = $overdueInstallmentsClass ?: $overdueDaysClass;
            @endphp
            <tr class="{{ $finalClass }}">
                <td><strong>{{ $credit->id }}</strong></td>
                <td>{{ $credit->client->name ?? 'N/A' }}</td>
                <td>{{ $credit->client->client_category ?? 'N/A' }}</td>
                <td>{{ $credit->deliveredBy->name ?? $credit->createdBy->name ?? 'N/A' }}</td>
                <td><strong>{{ $credit->days_overdue }}</strong></td>
                <td>Bs {{ number_format($credit->overdue_amount, 2) }}</td>
                <td>Bs {{ number_format($credit->balance, 2) }}</td>
                <td style="font-weight: bold; text-align: center;">{{ $credit->overdue_installments }}</td>
                <td style="text-align: center;">{{ $credit->total_installments ?? 0 }}</td>
                <td style="text-align: center;">{{ $credit->completion_rate }}%</td>
                <td>Bs {{ number_format($credit->amount, 2) }}</td>
                <td>Bs {{ number_format($credit->total_amount ?? $credit->amount * 1.1, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado por el Sistema de Cobrador</p>
    </div>
</body>
</html>
