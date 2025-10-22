<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyección de Flujo de Caja</title>
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

        .overdue { background-color: #f8d7da; }
        .pending { background-color: #fff3cd; }

    </style>
</head>
<body>
    <div class="header">
        <h1>Proyección de Flujo de Caja</h1>
        <p>Generado el: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
        <p>Por: {{ $generated_by }}</p>
    </div>

    <div class="summary">
        <h3>Resumen General</h3>
        <p><strong>Período:</strong> {{ $summary['period']['start'] }} - {{ $summary['period']['end'] }}</p>
        <p><strong>Total créditos activos:</strong> {{ $summary['total_active_credits'] }}</p>
        <p><strong>Total pagos proyectados:</strong> {{ $summary['total_projected_payments'] }}</p>
        <p><strong>Monto total proyectado:</strong> Bs {{ number_format($summary['total_projected_amount'], 2) }}</p>
        <p><strong>Monto vencido:</strong> Bs {{ number_format($summary['overdue_amount'], 2) }}</p>
        <p><strong>Monto pendiente:</strong> Bs {{ number_format($summary['pending_amount'], 2) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha Proyectada</th>
                <th>Crédito ID</th>
                <th>Cliente</th>
                <th>Cobrador</th>
                <th>Monto Proyectado</th>
                <th>Frecuencia</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($projections as $projection)
            <tr class="{{ $projection['is_overdue'] ? 'overdue' : 'pending' }}">
                <td>{{ $projection['payment_date'] }}</td>
                <td>{{ $projection['credit_id'] }}</td>
                <td>{{ $projection['client_name'] }}</td>
                <td>{{ $projection['cobrador_name'] }}</td>
                <td>Bs {{ number_format($projection['projected_amount'], 2) }}</td>
                <td>{{ $projection['frequency'] }}</td>
                <td>{{ $projection['is_overdue'] ? 'Vencido' : 'Pendiente' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado por el Sistema de Cobrador</p>
    </div>
</body>
</html>
