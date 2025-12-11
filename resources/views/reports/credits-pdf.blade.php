<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Créditos</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2196F3;
            padding-bottom: 15px;
        }

        .header h1 {
            margin: 0 0 10px 0;
            color: #2196F3;
            font-size: 24px;
        }

        .header p {
            margin: 5px 0;
            color: #666;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            background-color: #2196F3;
            color: white;
            padding: 8px 5px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
            border: 1px solid #1976D2;
        }

        td {
            padding: 6px 5px;
            border: 1px solid #ddd;
            font-size: 9px;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        /* Estilos de severidad - fondo de fila */
        .severity-none {
            background-color: #E8F5E9 !important;
        }

        .severity-light {
            background-color: #FFF9C4 !important;
        }

        .severity-moderate {
            background-color: #FFE0B2 !important;
        }

        .severity-critical {
            background-color: #FFCDD2 !important;
        }

        /* Badges de severidad */
        .severity-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-weight: bold;
            text-align: center;
            font-size: 9px;
        }

        .severity-badge.none {
            background-color: #E8F5E9;
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }

        .severity-badge.light {
            background-color: #FFF9C4;
            color: #F57C00;
            border: 1px solid #FFC107;
        }

        .severity-badge.moderate {
            background-color: #FFE0B2;
            color: #E65100;
            border: 1px solid #FF9800;
        }

        .severity-badge.critical {
            background-color: #FFCDD2;
            color: #C62828;
            border: 1px solid #F44336;
        }

        .alert-badge {
            background-color: #FFEBEE;
            color: #D32F2F;
            padding: 3px 6px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 8px;
        }

        .summary {
            margin-top: 30px;
            padding: 15px;
            background-color: #FFFACD;
            border: 2px solid #FFC107;
            border-radius: 5px;
        }

        .summary h2 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #333;
        }

        .summary p {
            margin: 5px 0;
            font-size: 11px;
        }

        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 9px;
            color: #666;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Reporte de Créditos</h1>
        <p><strong>Fecha de generación:</strong> {{ $generated_at }}</p>
        <p><strong>Total de registros:</strong> {{ $credits->count() }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">ID</th>
                <th style="width: 12%;">Cliente</th>
                <th style="width: 10%;">Cobrador</th>
                <th style="width: 8%;">Monto</th>
                <th style="width: 8%;">Total</th>
                <th style="width: 8%;">Pagado</th>
                <th style="width: 8%;">Balance</th>
                <th class="text-center" style="width: 6%;">Comp.</th>
                <th class="text-center" style="width: 6%;">Esp.</th>
                <th class="text-center" style="width: 6%;">Venc.</th>
                <th class="text-center" style="width: 12%;">Estado Retraso</th>
                <th class="text-center" style="width: 6%;">Días</th>
                <th class="text-center" style="width: 6%;">Alerta</th>
            </tr>
        </thead>
        <tbody>
            @foreach($credits as $credit)
                @php
                    // Obtener severidad desde backend (si está disponible)
                    $overdueSeverity = $credit->overdue_severity ?? 'none';
                    $daysOverdue = $credit->days_overdue ?? 0;

                    // Obtener emoji y label usando el servicio estandarizado
                    $severityEmoji = $formatter::getSeverityEmoji($overdueSeverity);
                    $severityLabel = $formatter::getSeverityLabel($overdueSeverity);

                    // Clase CSS para el fondo de la fila
                    $rowClass = 'severity-' . $overdueSeverity;
                @endphp
                <tr class="{{ $rowClass }}">
                    <td class="text-center">{{ $credit->id }}</td>
                    <td>{{ $credit->client->name ?? 'N/A' }}</td>
                    <td>{{ $credit->createdBy->name ?? 'N/A' }}</td>
                    <td class="text-right">Bs. {{ number_format($credit->amount, 2) }}</td>
                    <td class="text-right">Bs. {{ number_format($credit->total_amount, 2) }}</td>
                    <td class="text-right">Bs. {{ number_format($credit->total_paid, 2) }}</td>
                    <td class="text-right font-bold">Bs. {{ number_format($credit->balance, 2) }}</td>
                    <td class="text-center">{{ $credit->completed_installments ?? 0 }}</td>
                    <td class="text-center">{{ $credit->expected_installments ?? 0 }}</td>
                    <td class="text-center">{{ $credit->overdue_installments ?? 0 }}</td>
                    <td class="text-center">
                        <span class="severity-badge {{ $overdueSeverity }}">
                            {{ $severityEmoji }} {{ $severityLabel }}
                        </span>
                    </td>
                    <td class="text-center font-bold">{{ $daysOverdue }}</td>
                    <td class="text-center">
                        @if($credit->requires_attention)
                            <span class="alert-badge">⚠️ SÍ</span>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(!empty($summary))
    <div class="summary">
        <h2>📈 RESUMEN</h2>
        <p><strong>Total de Créditos:</strong> {{ $summary['total_credits'] ?? $credits->count() }}</p>
        <p><strong>Monto Total:</strong> Bs. {{ number_format($summary['total_amount'] ?? 0, 2) }}</p>
        <p><strong>Total Pagado:</strong> Bs. {{ number_format($summary['total_paid'] ?? 0, 2) }}</p>
        <p><strong>Saldo Pendiente:</strong> Bs. {{ number_format($summary['total_balance'] ?? $summary['pending_amount'] ?? 0, 2) }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Sistema de Gestión de Créditos - Cobrador</p>
        <p>Este reporte contiene información confidencial</p>
    </div>
</body>
</html>
