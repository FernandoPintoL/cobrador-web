<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Balances de Efectivo</title>
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

        .positive {
            color: green;
        }

        .negative {
            color: red;
        }

        .zero {
            color: black;
        }

    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Balances de Efectivo</h1>
        <p>Generado el: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
        <p>Por: {{ $generated_by }}</p>
    </div>

    <div class="summary">
        <h3>Resumen Consolidado</h3>
        <p><strong>Total de registros:</strong> {{ $summary['total_records'] }}</p>
        <p><strong>Monto inicial total:</strong> ${{ number_format($summary['total_initial'], 2) }}</p>
        <p><strong>Monto recolectado total:</strong> ${{ number_format($summary['total_collected'], 2) }}</p>
        <p><strong>Monto prestado total:</strong> ${{ number_format($summary['total_lent'], 2) }}</p>
        <p><strong>Monto final total:</strong> ${{ number_format($summary['total_final'], 2) }}</p>
        <p><strong>Diferencia promedio:</strong>
            <span class="{{ $summary['average_difference'] > 0 ? 'positive' : ($summary['average_difference'] < 0 ? 'negative' : 'zero') }}">
                ${{ number_format($summary['average_difference'], 2) }}
            </span>
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cobrador</th>
                <th>Fecha</th>
                <th>Monto Inicial</th>
                <th>Monto Recolectado</th>
                <th>Monto Prestado</th>
                <th>Monto Final</th>
                <th>Diferencia</th>
            </tr>
        </thead>
        <tbody>
            @foreach($balances as $balance)
            @php
            $difference = $balance->final_amount - ($balance->initial_amount + $balance->collected_amount - $balance->lent_amount);
            @endphp
            <tr>
                <td>{{ $balance->id }}</td>
                <td>{{ $balance->cobrador->name ?? 'N/A' }}</td>
                <td>{{ $balance->date->format('d/m/Y') }}</td>
                <td>${{ number_format($balance->initial_amount, 2) }}</td>
                <td>${{ number_format($balance->collected_amount, 2) }}</td>
                <td>${{ number_format($balance->lent_amount, 2) }}</td>
                <td>${{ number_format($balance->final_amount, 2) }}</td>
                <td class="{{ $difference > 0 ? 'positive' : ($difference < 0 ? 'negative' : 'zero') }}">
                    ${{ number_format($difference, 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Reporte generado por el Sistema de Cobrador</p>
    </div>
</body>
</html>
