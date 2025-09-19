<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Créditos</title>
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

        .status-active {
            color: green;
        }

        .status-completed {
            color: blue;
        }

        .status-pending {
            color: orange;
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
        <h3>Resumen</h3>
        <p><strong>Total de créditos:</strong> {{ $summary['total_credits'] }}</p>
        <p><strong>Monto total:</strong> ${{ number_format($summary['total_amount'], 2) }}</p>
        <p><strong>Créditos activos:</strong> {{ $summary['active_credits'] }}</p>
        <p><strong>Créditos completados:</strong> {{ $summary['completed_credits'] }}</p>
        <p><strong>Balance total:</strong> ${{ number_format($summary['total_balance'], 2) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Cobrador</th>
                <th>Monto</th>
                <th>Balance</th>
                <th>Estado</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
            </tr>
        </thead>
        <tbody>
            @foreach($credits as $credit)
            <tr>
                <td>{{ $credit->id }}</td>
                <td>{{ $credit->client->name ?? 'N/A' }}</td>
                <td>{{ $credit->cobrador->name ?? 'N/A' }}</td>
                <td>${{ number_format($credit->amount, 2) }}</td>
                <td>${{ number_format($credit->balance, 2) }}</td>
                <td class="status-{{ $credit->status }}">
                    {{ ucfirst(str_replace('_', ' ', $credit->status)) }}
                </td>
                <td>{{ $credit->start_date ? \Carbon\Carbon::parse($credit->start_date)->format('d/m/Y') : 'N/A' }}</td>
                <td>{{ $credit->end_date ? \Carbon\Carbon::parse($credit->end_date)->format('d/m/Y') : 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado por el Sistema de Cobrador</p>
    </div>
</body>
</html>
