@extends('reports.layouts.base')

@section('content')
    @include('reports.components.header', [
        'title' => 'Reporte de Mora',
        'generated_at' => $generated_at,
        'generated_by' => $generated_by,
    ])

    {{-- Resumen especial con dos columnas y gravedad --}}
    <div class="summary-section">
        <h3>Resumen General de Mora</h3>

        <div class="summary-grid" style="grid-template-columns: repeat(2, 1fr);">
            <div class="summary-item">
                <p><strong>Total créditos en mora</strong></p>
                <div class="value">{{ $summary['total_overdue_credits'] }}</div>
                <p style="margin-top: var(--spacing-md); font-size: var(--font-size-body);">
                    <strong>Monto total:</strong> Bs {{ number_format($summary['total_overdue_amount'], 2) }}
                </p>
                <p style="font-size: var(--font-size-body);">
                    <strong>Balance:</strong> Bs {{ number_format($summary['total_balance_overdue'], 2) }}
                </p>
            </div>

            <div class="summary-item">
                <p><strong>Estadísticas de Días en Mora</strong></p>
                <p style="margin-top: var(--spacing-xs); font-size: var(--font-size-body);">
                    <strong>Promedio:</strong> {{ number_format($summary['average_days_overdue'], 0) }} días
                </p>
                <p style="font-size: var(--font-size-body);">
                    <strong>Máximo:</strong> {{ $summary['max_days_overdue'] }} días
                </p>
                <p style="font-size: var(--font-size-body);">
                    <strong>Mínimo:</strong> {{ $summary['min_days_overdue'] }} días
                </p>
            </div>
        </div>

        <div class="summary-grid" style="grid-template-columns: repeat(3, 1fr); margin-top: var(--spacing-md);">
            <div class="summary-item" style="border-left-color: var(--color-warning);">
                <p><strong>Leve (1-7 días)</strong></p>
                <div class="value">{{ $summary['by_severity']['light'] }} créditos</div>
            </div>
            <div class="summary-item" style="border-left-color: var(--color-danger);">
                <p><strong>Moderada (8-30 días)</strong></p>
                <div class="value">{{ $summary['by_severity']['moderate'] }} créditos</div>
            </div>
            <div class="summary-item" style="border-left-color: #8B0000;">
                <p><strong>Severa (+30 días)</strong></p>
                <div class="value">{{ $summary['by_severity']['severe'] }} créditos</div>
            </div>
        </div>
    </div>

    @php
        $getRowClassOverdue = function($credit) {
            $daysOverdue = $credit->days_overdue ?? 0;
            $overdueInstallments = $credit->overdue_installments ?? 0;

            // Prioridad a cuotas vencidas
            if ($overdueInstallments >= 1 && $overdueInstallments <= 3) {
                return 'row-warning';
            } elseif ($overdueInstallments > 3) {
                return 'row-danger';
            }

            // Fallback a días en mora
            if ($daysOverdue <= 7) {
                return 'row-warning';
            } elseif ($daysOverdue <= 30) {
                return 'row-warning';
            }
            return 'row-danger';
        };

        $headers = [
            'ID', 'Cliente', 'Categoría', 'Cobrador', 'Días Mora',
            'Monto Vencido', 'Balance Total', 'Cuotas Vencidas', 'Cuotas Totales',
            '% Completado', 'Monto Original', 'Monto con Interés'
        ];

        $credits_custom = $credits->map(function($credit) {
            $daysOverdue = $credit->days_overdue ?? 0;
            $overdueInstallments = $credit->overdue_installments ?? 0;

            // Generador de icon basado en días
            $daysIcon = match(true) {
                $daysOverdue <= 7 => '<span class="icon icon-warning">⚠</span>',
                $daysOverdue <= 30 => '<span class="icon icon-danger">●</span>',
                default => '<span style="color: #8B0000; font-size: 12px;">◆</span>',
            };

            // Generador de icon para cuotas
            $installmentsIcon = match(true) {
                $overdueInstallments <= 0 => '<span class="icon icon-clean">✓</span>',
                $overdueInstallments <= 3 => '<span class="icon icon-warning">⚠</span>',
                default => '<span class="icon icon-danger">✕</span>',
            };

            return (object) array_merge(
                $credit->toArray(),
                [
                    'client_name' => $credit->client->name ?? 'N/A',
                    'client_category' => $credit->client->client_category ?? 'N/A',
                    'cobrador_name' => $credit->deliveredBy->name ?? $credit->createdBy->name ?? 'N/A',
                    'days_overdue_display' => $daysIcon . ' ' . $daysOverdue . 'd',
                    'overdue_amount_formatted' => 'Bs ' . number_format($credit->overdue_amount, 2),
                    'balance_formatted' => 'Bs ' . number_format($credit->balance, 2),
                    'overdue_installments_display' => $installmentsIcon . ' ' . $overdueInstallments,
                    'total_installments_display' => $credit->total_installments ?? 0,
                    'completion_rate_display' => $credit->completion_rate . '%',
                    'amount_formatted' => 'Bs ' . number_format($credit->amount, 2),
                    'total_amount_formatted' => 'Bs ' . number_format($credit->total_amount ?? $credit->amount * 1.1, 2),
                ]
            );
        });
    @endphp

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
            <tr class="{{ $getRowClassOverdue($credit) }}">
                <td><strong>{{ $credit->id }}</strong></td>
                <td>{{ $credit->client_name }}</td>
                <td>{{ $credit->client_category }}</td>
                <td>{{ $credit->cobrador_name }}</td>
                <td style="text-align: center; font-weight: bold;">
                    {!! $credit->days_overdue_display !!}
                </td>
                <td>{{ $credit->overdue_amount_formatted }}</td>
                <td>{{ $credit->balance_formatted }}</td>
                <td style="font-weight: bold; text-align: center;">
                    {!! $credit->overdue_installments_display !!}
                </td>
                <td style="text-align: center;">{{ $credit->total_installments_display }}</td>
                <td style="text-align: center;">{{ $credit->completion_rate_display }}</td>
                <td>{{ $credit->amount_formatted }}</td>
                <td>{{ $credit->total_amount_formatted }}</td>
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
