@extends('reports.layouts.base')

@section('content')
    @include('reports.components.header', [
        'title' => 'Reporte de Créditos',
        'generated_at' => $generated_at,
        'generated_by' => $generated_by,
    ])

    @include('reports.components.summary-section', [
        'title' => 'Resumen General',
        'columns' => 3,
        'items' => [
            'Total créditos' => $summary['total_credits'],
            'Monto total' => 'Bs ' . number_format($summary['total_amount'], 2),
            'Créditos activos' => $summary['active_credits'],
            'Créditos completados' => $summary['completed_credits'],
            'Balance pendiente' => 'Bs ' . number_format($summary['total_balance'], 2),
            'Total pagado' => 'Bs ' . number_format($summary['total_paid'] ?? 0, 2),
        ],
    ])

    @php
        use App\Services\CreditReportFormatterService;

        // Preparar headers de tabla - Información completa
        $headers = [
            'ID', 'Participantes', 'Monto', 'Interés', 'Total',
            'Cuota', 'Pagado', 'Balance', 'Completadas', 'Esperadas',
            'Estado', 'Frecuencia', 'Fechas'
        ];

        // Los datos ya vienen transformados con payment_status calculado en el servicio
        $credits_custom = $credits->map(function($credit) {
            // Convertir a array si es objeto
            $creditArray = is_array($credit) ? $credit : (array)$credit;

            // Los campos ya están calculados por el servicio
            $completedInstallments = $creditArray['completed_installments'] ?? 0;
            $totalInstallments = $creditArray['total_installments'] ?? 0;
            $paymentStatus = $creditArray['payment_status'] ?? 'danger';
            $paymentStatusIcon = $creditArray['payment_status_icon'] ?? '?';
            $paymentStatusLabel = $creditArray['payment_status_label'] ?? 'Desconocido';

            // Obtener información del modelo
            $model = $creditArray['_model'] ?? null;
            $frequency = $model ? $model->frequency : 'N/A';
            $endDate = $model ? $model->end_date->format('d/m/Y') : 'N/A';
            $completedAtDate = ($model && $model->completed_at) ? $model->completed_at->format('d/m/Y') : null;
            $installmentAmount = $model ? number_format($model->installment_amount, 2) : '0.00';

            // Obtener severidad de retraso
            $overdueSeverity = $creditArray['overdue_severity'] ?? 'none';
            $daysOverdue = $creditArray['days_overdue'] ?? 0;

            // Generar emoji y label usando el servicio
            $severityEmoji = CreditReportFormatterService::getSeverityEmoji($overdueSeverity);
            $severityLabel = CreditReportFormatterService::getSeverityLabel($overdueSeverity);

            // Traducir frecuencia al español
            $frequencyTranslations = [
                'daily' => 'Diario',
                'weekly' => 'Semanal',
                'biweekly' => 'Quincenal',
                'monthly' => 'Mensual',
            ];
            $frequencySpanish = $frequencyTranslations[$frequency] ?? ucfirst(str_replace('_', ' ', $frequency));

            // Crear objeto con todos los datos necesarios
            return (object) array_merge(
                $creditArray,
                [
                    'payment_row_class' => 'payment-' . $paymentStatus,
                    'payment_status_display' => '<span class="payment-icon">' . $paymentStatusIcon . '</span> ' . $paymentStatusLabel,
                    'frequency_display' => $frequencySpanish,
                    'installment_amount_formatted' => 'Bs ' . $installmentAmount,
                    'end_date_formatted' => $endDate,
                    'completed_at_formatted' => $completedAtDate,
                    'expected_installments' => $creditArray['expected_installments'] ?? 0,
                    'installments_overdue' => $creditArray['installments_overdue'] ?? 0,
                    'severity_display' => $severityEmoji . ' ' . $severityLabel,
                    'days_overdue' => $daysOverdue,
                ]
            );
        });
    @endphp

    {{-- Componente tabla con datos personalizados --}}
    <table class="report-table">
        <thead>
            <tr>
                @foreach($headers as $header)
                <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($credits_custom as $credit)
            <tr class="{{ $credit->payment_row_class }}">
                <td style="text-align: center; font-weight: bold;">{{ $credit->id }}</td>
                <td style="font-size: 9px; line-height: 1.4;">
                    <strong>{{ $credit->client_name }}</strong><br>
                    <span style="color: #666;">Cob:</span> {{ $credit->created_by_name ?? 'N/A' }}<br>
                    <span style="color: #666;">Ent:</span> {{ $credit->delivered_by_name ?? 'N/A' }}
                </td>
                <td>{{ $credit->amount_formatted ?? ('Bs ' . number_format($credit->amount, 2)) }}</td>
                <td>Bs {{ number_format(($credit->_model->total_amount ?? ($credit->amount * 1.1)) - $credit->amount, 2) }}</td>
                <td>Bs {{ number_format($credit->_model->total_amount ?? ($credit->amount * 1.1), 2) }}</td>
                <td>{{ $credit->installment_amount_formatted }}</td>
                <td>Bs {{ number_format($credit->_model->total_paid ?? 0, 2) }}</td>
                <td>{{ $credit->balance_formatted ?? ('Bs ' . number_format($credit->balance, 2)) }}</td>
                <td style="text-align: center;">{{ $credit->completed_installments }}</td>
                <td style="text-align: center;">{{ $credit->expected_installments }}</td>
                <td style="text-align: center;">
                    {!! $credit->payment_status_display !!}
                </td>
                <td style="font-size: 9px;">{{ $credit->frequency_display }}</td>
                <td style="font-size: 9px; line-height: 1.4;">
                    <span style="color: #666;">Inicio:</span> {{ $credit->created_at_formatted ?? 'N/A' }}<br>
                    <span style="color: #666;">Venc:</span> {{ $credit->end_date_formatted }}
                    @if($credit->completed_at_formatted)
                    <br><span style="color: #28a745; font-weight: bold;">✓ Compl:</span> {{ $credit->completed_at_formatted }}
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($headers) }}" style="text-align: center; padding: var(--spacing-lg); color: var(--color-text-secondary);">
                    No hay datos disponibles
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @include('reports.components.footer', [
        'system_name' => 'Reporte generado por el Sistema de Cobrador',
    ])
@endsection
