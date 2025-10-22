<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Pagos</title>
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
        <h1>Reporte de Pagos</h1>
        <p>Generado el: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
        <p>Por: {{ $generated_by }}</p>
    </div>

    <div class="summary">
        <h3>Resumen</h3>
        <p><strong>Total de pagos:</strong> {{ $summary['total_payments'] }}</p>
        <p><strong>Monto total:</strong> Bs{{ number_format($summary['total_amount'], 2) }}</p>
        <p><strong>Promedio por pago:</strong> Bs{{ number_format($summary['average_payment'], 2) }}</p>
        <p><strong>Total faltante para terminar las cuotas (estimado):</strong> Bs{{ number_format($summary['total_remaining_to_finish_installments'] ?? 0, 2) }}</p>
        @if(isset($summary['date_range']['start']) && isset($summary['date_range']['end']))
        <p><strong>Rango de fechas:</strong> {{ $summary['date_range']['start'] }} - {{ $summary['date_range']['end'] }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Cobrador / Cliente / Estado</th>
                <th>Cuota</th>
                <th>Cuotas faltantes</th>
                <th>Monto</th>
                <th>Falta para cuota</th>
                <th>Falta para crédito</th>
                <th>Método</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
            <tr>
                <td>{{ $payment->id }}</td>
                <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                <td>
                    <div><strong>Cobrador:</strong> {{ $payment->cobrador->name ?? 'N/A' }}</div>
                    <div><strong>Cliente:</strong> {{ $payment->credit->client->name ?? 'N/A' }}</div>
                    <div><strong>Estado:</strong> {{ $payment->status ?? 'N/A' }}</div>
                </td>
                {{-- Número de cuota pagada (instalment_number en payment) --}}
                <td>{{ $payment->installment_number > 0 ? $payment->installment_number : 'N/A' }}</td>
                {{-- Cuotas faltantes del crédito --}}
                <td>
                    @if($payment->credit)
                    {{ $payment->credit->getPendingInstallments() }}
                    @else
                    N/A
                    @endif
                </td>
                {{-- Monto pagado --}}
                <td>Bs{{ number_format($payment->amount, 2) }}</td>
                {{-- Falta para terminar la cuota (calculada en el modelo) --}}
                <td>
                    @if(!is_null($payment->remaining_for_installment))
                    Bs{{ number_format($payment->remaining_for_installment, 2) }}
                    @else
                    N/A
                    @endif
                </td>
                <td>
                    @if($payment->credit)
                    Bs{{ number_format($payment->credit->balance, 2) }}
                    @else
                    N/A
                    @endif
                </td>
                <td>{{ $payment->payment_method ?? 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado por el Sistema de Cobrador</p>
    </div>
</body>
</html>
