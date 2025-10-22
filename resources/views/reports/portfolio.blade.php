<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Cartera</title>
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

        .section {
            margin-top: 30px;
        }

    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Cartera</h1>
        <p>Generado el: {{ $generated_at->format('d/m/Y H:i:s') }}</p>
        <p>Por: {{ $generated_by }}</p>
    </div>

    <div class="summary">
        <h3>Resumen General de Cartera</h3>
        <p><strong>Total créditos:</strong> {{ $summary['total_credits'] }}</p>
        <p><strong>Créditos activos:</strong> {{ $summary['active_credits'] }}</p>
        <p><strong>Créditos completados:</strong> {{ $summary['completed_credits'] }}</p>
        <p><strong>Créditos al día:</strong> {{ $summary['current_credits'] }}</p>
        <p><strong>Créditos en mora:</strong> {{ $summary['overdue_credits'] }}</p>
        <p><strong>Total prestado:</strong> Bs {{ number_format($summary['total_lent'], 2) }}</p>
        <p><strong>Total cobrado:</strong> Bs {{ number_format($summary['total_collected'], 2) }}</p>
        <p><strong>Balance activo:</strong> Bs {{ number_format($summary['active_balance'], 2) }}</p>
        <p><strong>Balance en mora:</strong> Bs {{ number_format($summary['overdue_balance'], 2) }}</p>
        <p><strong>Calidad de cartera:</strong> {{ $summary['portfolio_quality'] }}%</p>
        <p><strong>Tasa de cobranza:</strong> {{ $summary['collection_rate'] }}%</p>
    </div>

    <div class="section">
        <h3>Cartera por Cobrador</h3>
        <table>
            <thead>
                <tr>
                    <th>Cobrador</th>
                    <th>Total Créd.</th>
                    <th>Activos</th>
                    <th>Completados</th>
                    <th>Balance Total</th>
                    <th>Total Prestado</th>
                    <th>En Mora</th>
                    <th>Monto Mora</th>
                    <th>Calidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach($portfolio_by_cobrador as $cobrador => $data)
                <tr>
                    <td>{{ $cobrador }}</td>
                    <td>{{ $data['total_credits'] }}</td>
                    <td>{{ $data['active_credits'] }}</td>
                    <td>{{ $data['completed_credits'] }}</td>
                    <td>Bs {{ number_format($data['total_balance'], 2) }}</td>
                    <td>Bs {{ number_format($data['total_lent'], 2) }}</td>
                    <td>{{ $data['overdue_credits'] }}</td>
                    <td>Bs {{ number_format($data['overdue_amount'], 2) }}</td>
                    <td>{{ $data['portfolio_quality'] }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Cartera por Categoría de Cliente</h3>
        <table>
            <thead>
                <tr>
                    <th>Categoría</th>
                    <th>Total Créditos</th>
                    <th>Balance Activo</th>
                    <th>Total Prestado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($portfolio_by_category as $category => $data)
                <tr>
                    <td>{{ $category }}</td>
                    <td>{{ $data['total_credits'] }}</td>
                    <td>Bs {{ number_format($data['active_balance'], 2) }}</td>
                    <td>Bs {{ number_format($data['total_lent'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Top 10 Clientes por Balance</h3>
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Categoría</th>
                    <th>Crédito ID</th>
                    <th>Balance</th>
                    <th>Monto Total</th>
                    <th>% Completado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($top_clients_by_balance as $client)
                <tr>
                    <td>{{ $client['client_name'] }}</td>
                    <td>{{ $client['client_category'] }}</td>
                    <td>{{ $client['credit_id'] }}</td>
                    <td>Bs {{ number_format($client['balance'], 2) }}</td>
                    <td>Bs {{ number_format($client['total_amount'], 2) }}</td>
                    <td>{{ $client['completion_rate'] }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Distribución por Antigüedad</h3>
        <p><strong>0-30 días:</strong> {{ $portfolio_by_age['0_30_days'] }} créditos</p>
        <p><strong>31-60 días:</strong> {{ $portfolio_by_age['31_60_days'] }} créditos</p>
        <p><strong>61-90 días:</strong> {{ $portfolio_by_age['61_90_days'] }} créditos</p>
        <p><strong>Más de 90 días:</strong> {{ $portfolio_by_age['over_90_days'] }} créditos</p>
    </div>

    <div class="footer">
        <p>Reporte generado por el Sistema de Cobrador</p>
    </div>
</body>
</html>
