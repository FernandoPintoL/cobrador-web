@extends('reports.layouts.base')

@section('content')
    @include('reports.components.header', [
        'title' => 'Reporte de Pagos',
        'generated_at' => $generated_at,
        'generated_by' => $generated_by,
    ])

    @php
        $summaryItems = [
            'Total de pagos' => $summary['total_payments'],
            'Monto total' => 'Bs ' . number_format($summary['total_amount'], 2),
            'Promedio por pago' => 'Bs ' . number_format($summary['average_payment'], 2),
            'Faltante para cuotas' => 'Bs ' . number_format($summary['total_remaining_to_finish_installments'] ?? 0, 2),
        ];

        if(isset($summary['date_range']['start']) && isset($summary['date_range']['end'])) {
            $summaryItems['Rango de fechas'] = $summary['date_range']['start'] . ' - ' . $summary['date_range']['end'];
        }
    @endphp

    @include('reports.components.summary-section', [
        'title' => 'Resumen de Pagos',
        'columns' => 2,
        'items' => $summaryItems,
    ])

    @php
        $headers = [
            'ID', 'Fecha', 'Cobrador', 'Cliente', 'Estado', 'Cuota',
            'Cuotas Pendientes', 'Monto', 'Falta Cuota', 'Falta Crédito', 'Método'
        ];

        $payments_custom = $payments->map(function($payment) {
            // ✅ OPTIMIZACIÓN: Usando métodos cacheados para mejor performance en reportes
            $remainingForInstallment = $payment->getRemainingForInstallment();

            return (object) array_merge(
                $payment->toArray(),
                [
                    'cobrador_name' => $payment->cobrador->name ?? 'N/A',
                    'client_name' => $payment->credit->client->name ?? 'N/A',
                    'payment_date_formatted' => $payment->payment_date->format('d/m/Y'),
                    'installment_num' => $payment->installment_number > 0 ? $payment->installment_number : 'N/A',
                    'pending_installments' => $payment->credit ? $payment->credit->getPendingInstallments() : 'N/A',
                    'remaining_for_installment_formatted' => !is_null($remainingForInstallment)
                        ? 'Bs ' . number_format($remainingForInstallment, 2)
                        : 'N/A',
                    'credit_balance' => $payment->credit ? 'Bs ' . number_format($payment->credit->balance, 2) : 'N/A',
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
            @forelse($payments_custom as $payment)
            <tr>
                <td>{{ $payment->id }}</td>
                <td>{{ $payment->payment_date_formatted }}</td>
                <td>{{ $payment->cobrador_name }}</td>
                <td>{{ $payment->client_name }}</td>
                <td class="status-{{ strtolower($payment->status ?? 'pending') }}">
                    {{ $payment->status ?? 'N/A' }}
                </td>
                <td>{{ $payment->installment_num }}</td>
                <td>{{ $payment->pending_installments }}</td>
                <td>Bs {{ number_format($payment->amount, 2) }}</td>
                <td>{{ $payment->remaining_for_installment_formatted }}</td>
                <td>{{ $payment->credit_balance }}</td>
                <td>{{ $payment->payment_method ?? 'N/A' }}</td>
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
