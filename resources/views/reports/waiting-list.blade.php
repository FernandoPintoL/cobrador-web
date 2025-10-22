<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Espera</title>
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

        .status-pending_approval { background-color: #fff3cd; }
        .status-waiting_delivery { background-color: #cfe2ff; }
        .status-overdue { background-color: #f8d7da; }

    </style>
</head>
<body>
    <div class="header">
        <h1>Lista de Espera - Créditos Pendientes</h1>
        <p>Generado el: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
        <p>Por: {{ $generated_by }}</p>
    </div>

    <div class="summary">
        <h3>Resumen</h3>
        <p><strong>Total en lista de espera:</strong> {{ $summary['total_waiting'] }}</p>
        <p><strong>Pendientes de aprobación:</strong> {{ $summary['pending_approval'] }}</p>
        <p><strong>Esperando entrega:</strong> {{ $summary['waiting_delivery'] }}</p>
        <p><strong>Vencidos para entrega:</strong> {{ $summary['overdue_for_delivery'] }}</p>
        <p><strong>Monto total pendiente:</strong> Bs {{ number_format($summary['total_pending_amount'], 2) }}</p>
        <p><strong>Promedio días esperando:</strong> {{ number_format($summary['average_days_waiting'], 1) }} días</p>
        <p><strong>Máximo días esperando:</strong> {{ $summary['max_days_waiting'] }} días</p>
    </div>

    <h3>Pendientes de Aprobación</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Cobrador</th>
                <th>Monto</th>
                <th>Fecha Creación</th>
                <th>Días Esperando</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pending_approval as $credit)
            <tr class="status-pending_approval">
                <td>{{ $credit->id }}</td>
                <td>{{ $credit->client->name ?? 'N/A' }}</td>
                <td>{{ $credit->createdBy->name ?? 'N/A' }}</td>
                <td>Bs {{ number_format($credit->amount, 2) }}</td>
                <td>{{ $credit->created_at->format('d/m/Y') }}</td>
                <td>{{ $credit->days_waiting }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Esperando Entrega</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>Cobrador</th>
                <th>Monto</th>
                <th>Fecha Programada</th>
                <th>Días Esperando</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($waiting_delivery as $credit)
            <tr class="{{ $credit->is_overdue_for_delivery ? 'status-overdue' : 'status-waiting_delivery' }}">
                <td>{{ $credit->id }}</td>
                <td>{{ $credit->client->name ?? 'N/A' }}</td>
                <td>{{ $credit->createdBy->name ?? 'N/A' }}</td>
                <td>Bs {{ number_format($credit->amount, 2) }}</td>
                <td>{{ $credit->scheduled_delivery_date ? $credit->scheduled_delivery_date->format('d/m/Y') : 'N/A' }}</td>
                <td>{{ $credit->days_waiting }}</td>
                <td>{{ $credit->is_overdue_for_delivery ? 'VENCIDO' : 'Pendiente' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado por el Sistema de Cobrador</p>
    </div>
</body>
</html>
