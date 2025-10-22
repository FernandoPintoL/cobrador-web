<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actividad Diaria</title>
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

        .cobrador-section {
            margin-bottom: 30px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 6px;
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
        <h1>Reporte de Actividad Diaria</h1>
        <p>Fecha: {{ $summary['date'] }} - {{ $summary['day_name'] }}</p>
        <p>Generado el: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
        <p>Por: {{ $generated_by }}</p>
    </div>

    <div class="summary">
        <h3>Resumen del Día</h3>
        <p><strong>Total cobradores:</strong> {{ $summary['total_cobradores'] }}</p>
        <p><strong>Créditos entregados:</strong> {{ $summary['totals']['credits_delivered'] }} (Bs {{ number_format($summary['totals']['amount_lent'], 2) }})</p>
        <p><strong>Pagos cobrados:</strong> {{ $summary['totals']['payments_collected'] }} (Bs {{ number_format($summary['totals']['amount_collected'], 2) }})</p>
        <p><strong>Pagos esperados:</strong> {{ $summary['totals']['expected_payments'] }}</p>
        <p><strong>Pendientes de entregar:</strong> {{ $summary['totals']['pending_deliveries'] }}</p>
        <p><strong>Eficiencia general:</strong> {{ $summary['overall_efficiency'] }}%</p>
        <p><strong>Cajas abiertas:</strong> {{ $summary['cash_balances']['opened'] }} |
           <strong>Cerradas:</strong> {{ $summary['cash_balances']['closed'] }} |
           <strong>No abiertas:</strong> {{ $summary['cash_balances']['not_opened'] }}</p>
    </div>

    @foreach($activities as $activity)
    <div class="cobrador-section">
        <h3>{{ $activity['cobrador_name'] }}</h3>

        <h4>Estado de Caja</h4>
        <p>
            <strong>Estado:</strong> {{ $activity['cash_balance']['status'] }} |
            <strong>Inicial:</strong> Bs {{ number_format($activity['cash_balance']['initial_amount'], 2) }} |
            <strong>Cobrado:</strong> Bs {{ number_format($activity['cash_balance']['collected_amount'], 2) }} |
            <strong>Prestado:</strong> Bs {{ number_format($activity['cash_balance']['lent_amount'], 2) }} |
            <strong>Final:</strong> Bs {{ number_format($activity['cash_balance']['final_amount'], 2) }}
        </p>

        <h4>Créditos Entregados ({{ $activity['credits_delivered']['count'] }})</h4>
        @if(count($activity['credits_delivered']['details']) > 0)
        <table>
            <tr><th>ID</th><th>Cliente</th><th>Monto</th></tr>
            @foreach($activity['credits_delivered']['details'] as $credit)
            <tr>
                <td>{{ $credit['id'] }}</td>
                <td>{{ $credit['client'] }}</td>
                <td>Bs {{ number_format($credit['amount'], 2) }}</td>
            </tr>
            @endforeach
        </table>
        @else
        <p>No se entregaron créditos</p>
        @endif

        <h4>Pagos Cobrados ({{ $activity['payments_collected']['count'] }})</h4>
        @if(count($activity['payments_collected']['details']) > 0)
        <table>
            <tr><th>ID</th><th>Cliente</th><th>Monto</th></tr>
            @foreach($activity['payments_collected']['details'] as $payment)
            <tr>
                <td>{{ $payment['id'] }}</td>
                <td>{{ $payment['client'] }}</td>
                <td>Bs {{ number_format($payment['amount'], 2) }}</td>
            </tr>
            @endforeach
        </table>
        @else
        <p>No se cobraron pagos</p>
        @endif

        <h4>Eficiencia de Cobranza</h4>
        <p>
            <strong>Esperados:</strong> {{ $activity['expected_payments']['count'] }} |
            <strong>Cobrados:</strong> {{ $activity['expected_payments']['collected'] }} |
            <strong>Pendientes:</strong> {{ $activity['expected_payments']['pending'] }} |
            <strong>Eficiencia:</strong> {{ $activity['expected_payments']['efficiency'] }}%
        </p>
    </div>
    @endforeach

    <div class="footer">
        <p>Reporte generado por el Sistema de Cobrador</p>
    </div>
</body>
</html>
