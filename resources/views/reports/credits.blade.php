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
            'Total invertido' => 'Bs ' . number_format($summary['total_amount'] + ($summary['total_amount'] * 0.1), 2),
        ],
    ])

    @php
        // Preparar headers de tabla
        $headers = [
            'ID', 'Cliente', 'Cobrador', 'Monto', 'Interés', 'Total',
            'Pagado', 'Balance', 'Cuotas', 'Completadas', 'Vencidas', 'Estado', 'Inicio'
        ];

        // Preparar función para generar clase de fila según cuotas vencidas
        $getRowClass = function($credit) {
            $totalAmount = $credit->total_amount ?? ($credit->amount * 1.1);
            $completedInstallments = $credit->getCompletedInstallmentsCount();
            $expectedInstallments = $credit->getExpectedInstallments();
            $pendingInstallments = $expectedInstallments - $completedInstallments;

            if ($pendingInstallments <= 0) {
                return 'row-clean';
            } elseif ($pendingInstallments <= 3) {
                return 'row-warning';
            }
            return 'row-danger';
        };

        // Preparar vista de datos personalizada
        $credits_custom = $credits->map(function($credit) {
            $totalAmount = $credit->total_amount ?? ($credit->amount * 1.1);
            $paidAmount = $credit->total_paid ?? ($totalAmount - $credit->balance);
            $completedInstallments = $credit->getCompletedInstallmentsCount();
            $expectedInstallments = $credit->getExpectedInstallments();
            $pendingInstallments = $expectedInstallments - $completedInstallments;

            return (object) array_merge(
                $credit->toArray(),
                [
                    'client_name' => $credit->client->name ?? 'N/A',
                    'cobrador_name' => $credit->createdBy->name ?? 'N/A',
                    'interest_amount' => $totalAmount - $credit->amount,
                    'total_with_interest' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'total_installments' => $expectedInstallments,
                    'completed_installments' => $completedInstallments,
                    'pending_installments' => $pendingInstallments,
                    'pending_display' => $pendingInstallments <= 0
                        ? '<span class="icon icon-clean">✓</span> ' . $pendingInstallments
                        : ($pendingInstallments <= 3
                            ? '<span class="icon icon-warning">⚠</span> ' . $pendingInstallments
                            : '<span class="icon icon-danger">✕</span> ' . $pendingInstallments),
                    'status_display' => substr(str_replace('_', ' ', $credit->status), 0, 10),
                    'start_date_formatted' => $credit->start_date ? \Carbon\Carbon::parse($credit->start_date)->format('d/m/y') : 'N/A',
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
            <tr class="{{ $getRowClass($credit) }}">
                <td>{{ $credit->id }}</td>
                <td>{{ $credit->client_name }}</td>
                <td>{{ $credit->cobrador_name }}</td>
                <td>Bs {{ number_format($credit->amount, 2) }}</td>
                <td>Bs {{ number_format($credit->interest_amount, 2) }}</td>
                <td>Bs {{ number_format($credit->total_with_interest, 2) }}</td>
                <td>Bs {{ number_format($credit->paid_amount, 2) }}</td>
                <td>Bs {{ number_format($credit->balance, 2) }}</td>
                <td>{{ $credit->total_installments }}</td>
                <td>{{ $credit->completed_installments }}</td>
                <td style="text-align: center;">
                    {!! $credit->pending_display !!}
                </td>
                <td class="status-{{ $credit->status }}">
                    {{ $credit->status_display }}
                </td>
                <td>{{ $credit->start_date_formatted }}</td>
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
