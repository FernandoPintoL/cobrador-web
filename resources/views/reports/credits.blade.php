<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Créditos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 15px;
            font-size: 10px;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
        }

        .header p {
            margin: 2px 0;
            font-size: 9px;
        }

        .summary {
            background: #f5f5f5;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .summary h3 {
            grid-column: 1 / -1;
            margin: 0 0 5px 0;
            font-size: 11px;
        }

        .summary p {
            margin: 2px 0;
            font-size: 9px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9px;
        }

        th,
        td {
            border: 1px solid #999;
            padding: 4px 3px;
            text-align: left;
        }

        th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
            font-size: 8px;
        }

        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 9px;
            color: #666;
        }

        /* Color schemes based on overdue installments */
        .overdue-0 {
            background-color: #ffffff;
        }

        .overdue-1-3 {
            background-color: #fffacd;
        }

        .overdue-more {
            background-color: #ffcccc;
        }

        .status-active {
            color: #228B22;
            font-weight: bold;
        }

        .status-completed {
            color: #0000CD;
            font-weight: bold;
        }

        .status-pending {
            color: #FF8C00;
            font-weight: bold;
        }

    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Créditos</h1>
        <p>Generado el: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
        <p>Por: {{ $generated_by }}</p>
    </div>

    <div class="summary">
        <h3>Resumen General</h3>
        <p><strong>Total créditos:</strong> {{ $summary['total_credits'] }}</p>
        <p><strong>Monto total:</strong> Bs{{ number_format($summary['total_amount'], 2) }}</p>
        <p><strong>Créditos activos:</strong> {{ $summary['active_credits'] }}</p>
        <p><strong>Créditos completados:</strong> {{ $summary['completed_credits'] }}</p>
        <p><strong>Balance pendiente:</strong> Bs{{ number_format($summary['total_balance'], 2) }}</p>
        <p><strong>Total invertido:</strong> Bs{{ number_format($summary['total_amount'] + ($summary['total_amount'] * 0.1), 2) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Cobrador</th>
                <th>Monto</th>
                <th>Interés</th>
                <th>Total</th>
                <th>Pagado</th>
                <th>Balance</th>
                <th>Cuotas</th>
                <th>Completadas</th>
                <th>Vencidas</th>
                <th>Estado</th>
                <th>Inicio</th>
            </tr>
        </thead>
        <tbody>
            @foreach($credits as $credit)
            @php
                $totalAmount = $credit->total_amount ?? ($credit->amount * 1.1);
                $paidAmount = $credit->total_paid ?? ($totalAmount - $credit->balance);
                $completedInstallments = $credit->getCompletedInstallmentsCount();
                $expectedInstallments = $credit->getExpectedInstallments();
                $pendingInstallments = $expectedInstallments - $completedInstallments;
                $overdueClass = $pendingInstallments <= 0 ? 'overdue-0' : ($pendingInstallments <= 3 ? 'overdue-1-3' : 'overdue-more');
            @endphp
            <tr class="{{ $overdueClass }}">
                <td>{{ $credit->id }}</td>
                <td>{{ $credit->client->name ?? 'N/A' }}</td>
                <td>{{ $credit->createdBy->name ?? 'N/A' }}</td>
                <td>Bs{{ number_format($credit->amount, 2) }}</td>
                <td>Bs{{ number_format($totalAmount - $credit->amount, 2) }}</td>
                <td>Bs{{ number_format($totalAmount, 2) }}</td>
                <td>Bs{{ number_format($paidAmount, 2) }}</td>
                <td>Bs{{ number_format($credit->balance, 2) }}</td>
                <td>{{ $expectedInstallments }}</td>
                <td>{{ $completedInstallments }}</td>
                <td style="font-weight: bold;">{{ $pendingInstallments }}</td>
                <td class="status-{{ $credit->status }}">
                    {{ substr(str_replace('_', ' ', $credit->status), 0, 10) }}
                </td>
                <td>{{ $credit->start_date ? \Carbon\Carbon::parse($credit->start_date)->format('d/m/y') : 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado por el Sistema de Cobrador</p>
    </div>
</body>
</html>
