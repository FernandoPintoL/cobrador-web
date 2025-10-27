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
        // Preparar headers de tabla - Información completa
        $headers = [
            'ID', 'Cliente', 'Creador/Cobrador', 'Entregó', 'Monto', 'Interés', 'Total',
            'Por Cuota', 'Pagado', 'Balance', 'Completadas', 'Esperadas', 'Vencidas',
            'Estado', 'Frecuencia', 'Vencimiento', 'Inicio'
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
            $installmentAmount = $model ? number_format($model->installment_amount, 2) : '0.00';

            // Crear objeto con todos los datos necesarios
            return (object) array_merge(
                $creditArray,
                [
                    'payment_row_class' => 'payment-' . $paymentStatus,
                    'payment_status_display' => '<span class="payment-icon">' . $paymentStatusIcon . '</span> ' . $paymentStatusLabel,
                    'frequency_display' => ucfirst(str_replace('_', ' ', $frequency)),
                    'installment_amount_formatted' => 'Bs ' . $installmentAmount,
                    'end_date_formatted' => $endDate,
                    'expected_installments' => $creditArray['expected_installments'] ?? 0,
                    'installments_overdue' => $creditArray['installments_overdue'] ?? 0,
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
                <td>{{ $credit->client_name }}</td>
                <td style="font-size: 9px;">{{ $credit->created_by_name ?? 'N/A' }}</td>
                <td style="font-size: 9px;">{{ $credit->delivered_by_name ?? 'N/A' }}</td>
                <td>{{ $credit->amount_formatted ?? ('Bs ' . number_format($credit->amount, 2)) }}</td>
                <td>Bs {{ number_format(($credit->_model->total_amount ?? ($credit->amount * 1.1)) - $credit->amount, 2) }}</td>
                <td>Bs {{ number_format($credit->_model->total_amount ?? ($credit->amount * 1.1), 2) }}</td>
                <td>{{ $credit->installment_amount_formatted }}</td>
                <td>Bs {{ number_format($credit->_model->total_paid ?? 0, 2) }}</td>
                <td>{{ $credit->balance_formatted ?? ('Bs ' . number_format($credit->balance, 2)) }}</td>
                <td style="text-align: center;">{{ $credit->completed_installments }}</td>
                <td style="text-align: center;">{{ $credit->expected_installments }}</td>
                <td style="text-align: center; color: #FF0000; font-weight: bold;">{{ $credit->installments_overdue }}</td>
                <td style="text-align: center;">
                    {!! $credit->payment_status_display !!}
                </td>
                <td style="font-size: 9px;">{{ $credit->frequency_display }}</td>
                <td style="text-align: center; font-size: 9px;">{{ $credit->end_date_formatted }}</td>
                <td>{{ $credit->created_at_formatted ?? 'N/A' }}</td>
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
