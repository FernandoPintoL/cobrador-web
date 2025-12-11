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
            // Convertir a array si es objeto
            $paymentArray = is_array($payment) ? $payment : (array)$payment;

            // Obtener el modelo si existe (para llamadas a métodos)
            $model = $paymentArray['_model'] ?? null;

            // Si tenemos el modelo, calcular valores adicionales
            $remainingForInstallment = null;
            $pendingInstallments = 'N/A';
            $creditBalance = 'N/A';

            if ($model) {
                try {
                    $remainingForInstallment = $model->getRemainingForInstallment();
                    $pendingInstallments = $model->credit ? $model->credit->getPendingInstallments() : 'N/A';
                    $creditBalance = $model->credit ? 'Bs ' . number_format($model->credit->balance, 2) : 'N/A';
                } catch (\Exception $e) {
                    // Si hay error, usar valores por defecto
                }
            }

            return (object) array_merge(
                $paymentArray,
                [
                    'cobrador_name' => $paymentArray['cobrador_name'] ?? 'N/A',
                    'client_name' => $paymentArray['client_name'] ?? 'N/A',
                    'payment_date_formatted' => $paymentArray['payment_date_formatted'] ?? 'N/A',
                    'installment_num' => ($paymentArray['installment_number'] ?? 0) > 0 ? $paymentArray['installment_number'] : 'N/A',
                    'pending_installments' => $pendingInstallments,
                    'remaining_for_installment_formatted' => !is_null($remainingForInstallment)
                        ? 'Bs ' . number_format($remainingForInstallment, 2)
                        : 'N/A',
                    'credit_balance' => $creditBalance,
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
