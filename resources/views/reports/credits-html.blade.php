<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Créditos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            font-size: 16px;
            opacity: 0.95;
            margin: 5px 0;
        }

        .content {
            padding: 30px;
        }

        .table-wrapper {
            overflow-x: auto;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        thead {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
        }

        th {
            padding: 14px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #1565C0;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #E0E0E0;
            font-size: 13px;
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Estilos de severidad - fondo de fila */
        .severity-none {
            background: linear-gradient(135deg, #E8F5E9 0%, #F1F8E9 100%);
        }

        .severity-light {
            background: linear-gradient(135deg, #FFF9C4 0%, #FFECB3 100%);
        }

        .severity-moderate {
            background: linear-gradient(135deg, #FFE0B2 0%, #FFCCBC 100%);
        }

        .severity-critical {
            background: linear-gradient(135deg, #FFCDD2 0%, #FFCCCC 100%);
        }

        /* Badges de severidad */
        .severity-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .severity-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .severity-badge.none {
            background: #E8F5E9;
            color: #2E7D32;
            border: 2px solid #4CAF50;
        }

        .severity-badge.light {
            background: #FFF9C4;
            color: #F57C00;
            border: 2px solid #FFC107;
        }

        .severity-badge.moderate {
            background: #FFE0B2;
            color: #E65100;
            border: 2px solid #FF9800;
        }

        .severity-badge.critical {
            background: #FFCDD2;
            color: #C62828;
            border: 2px solid #F44336;
        }

        .alert-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: linear-gradient(135deg, #FFEBEE 0%, #FFCDD2 100%);
            color: #C62828;
            padding: 5px 10px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 11px;
            border: 2px solid #F44336;
            box-shadow: 0 2px 4px rgba(244, 67, 54, 0.2);
        }

        .summary {
            margin-top: 30px;
            padding: 25px;
            background: linear-gradient(135deg, #FFFACD 0%, #FFF9C4 100%);
            border: 3px solid #FFC107;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
        }

        .summary h2 {
            font-size: 22px;
            margin-bottom: 15px;
            color: #F57C00;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .summary-item {
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .summary-item strong {
            display: block;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .summary-item .value {
            font-size: 20px;
            font-weight: 700;
            color: #2196F3;
        }

        .footer {
            margin-top: 30px;
            padding: 20px;
            background: #F5F5F5;
            border-top: 3px solid #2196F3;
            text-align: center;
            color: #666;
            font-size: 12px;
        }

        .footer p {
            margin: 5px 0;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: 700;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .header h1 {
                font-size: 24px;
            }

            .content {
                padding: 15px;
            }

            table {
                font-size: 11px;
            }

            th, td {
                padding: 8px 5px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                border-radius: 0;
            }

            tbody tr:hover {
                transform: none;
                box-shadow: none;
            }

            .severity-badge:hover,
            .alert-badge:hover {
                transform: none;
            }
        }

        /* Animación de carga */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .table-wrapper {
            animation: fadeIn 0.5s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Reporte de Créditos</h1>
            <p><strong>Fecha de generación:</strong> {{ $generated_at }}</p>
            <p><strong>Total de registros:</strong> {{ $credits->count() }}</p>
        </div>

        <div class="content">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th>Cliente</th>
                            <th>Cobrador</th>
                            <th class="text-right">Monto</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Pagado</th>
                            <th class="text-right">Balance</th>
                            <th class="text-center">Completadas</th>
                            <th class="text-center">Esperadas</th>
                            <th class="text-center">Vencidas</th>
                            <th class="text-center">Estado de Retraso</th>
                            <th class="text-center">Días</th>
                            <th class="text-center">Alerta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($credits as $credit)
                            @php
                                // Obtener severidad desde backend
                                $overdueSeverity = $credit->overdue_severity ?? 'none';
                                $daysOverdue = $credit->days_overdue ?? 0;

                                // Obtener emoji y label usando el servicio estandarizado
                                $severityEmoji = $formatter::getSeverityEmoji($overdueSeverity);
                                $severityLabel = $formatter::getSeverityLabel($overdueSeverity);

                                // Clase CSS para el fondo de la fila
                                $rowClass = 'severity-' . $overdueSeverity;
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="text-center font-bold">{{ $credit->id }}</td>
                                <td>{{ $credit->client->name ?? 'N/A' }}</td>
                                <td>{{ $credit->createdBy->name ?? 'N/A' }}</td>
                                <td class="text-right">Bs. {{ number_format($credit->amount, 2) }}</td>
                                <td class="text-right">Bs. {{ number_format($credit->total_amount, 2) }}</td>
                                <td class="text-right">Bs. {{ number_format($credit->total_paid, 2) }}</td>
                                <td class="text-right font-bold">Bs. {{ number_format($credit->balance, 2) }}</td>
                                <td class="text-center">{{ $credit->completed_installments ?? 0 }}</td>
                                <td class="text-center">{{ $credit->expected_installments ?? 0 }}</td>
                                <td class="text-center font-bold">{{ $credit->overdue_installments ?? 0 }}</td>
                                <td class="text-center">
                                    <span class="severity-badge {{ $overdueSeverity }}">
                                        {{ $severityEmoji }} {{ $severityLabel }}
                                    </span>
                                </td>
                                <td class="text-center font-bold">{{ $daysOverdue }}</td>
                                <td class="text-center">
                                    @if($credit->requires_attention)
                                        <span class="alert-badge">⚠️ ATENCIÓN</span>
                                    @else
                                        <span style="color: #999;">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(!empty($summary))
            <div class="summary">
                <h2>📈 RESUMEN GENERAL</h2>
                <div class="summary-grid">
                    <div class="summary-item">
                        <strong>Total de Créditos</strong>
                        <div class="value">{{ $summary['total_credits'] ?? $credits->count() }}</div>
                    </div>
                    <div class="summary-item">
                        <strong>Monto Total</strong>
                        <div class="value">Bs. {{ number_format($summary['total_amount'] ?? 0, 2) }}</div>
                    </div>
                    <div class="summary-item">
                        <strong>Total Pagado</strong>
                        <div class="value" style="color: #4CAF50;">Bs. {{ number_format($summary['total_paid'] ?? 0, 2) }}</div>
                    </div>
                    <div class="summary-item">
                        <strong>Saldo Pendiente</strong>
                        <div class="value" style="color: #F44336;">Bs. {{ number_format($summary['total_balance'] ?? $summary['pending_amount'] ?? 0, 2) }}</div>
                    </div>
                </div>
            </div>
            @endif

            <div class="footer">
                <p><strong>Sistema de Gestión de Créditos - Cobrador</strong></p>
                <p>Este reporte contiene información confidencial</p>
                <p>Generado automáticamente el {{ $generated_at }}</p>
            </div>
        </div>
    </div>
</body>
</html>
