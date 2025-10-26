@extends('reports.layouts.base')

@section('content')
    @include('reports.components.header', [
        'title' => 'Reporte de Actividad Diaria',
        'generated_at' => $generated_at,
        'generated_by' => $generated_by,
        'subtitle' => $summary['date'] . ' - ' . $summary['day_name'],
    ])

    @include('reports.components.summary-section', [
        'title' => 'Resumen del Día',
        'columns' => 2,
        'items' => [
            'Total cobradores' => $summary['total_cobradores'],
            'Créditos entregados' => $summary['totals']['credits_delivered'] . ' (Bs ' . number_format($summary['totals']['amount_lent'], 2) . ')',
            'Pagos cobrados' => $summary['totals']['payments_collected'] . ' (Bs ' . number_format($summary['totals']['amount_collected'], 2) . ')',
            'Pagos esperados' => $summary['totals']['expected_payments'],
            'Pendientes de entregar' => $summary['totals']['pending_deliveries'],
            'Eficiencia general' => $summary['overall_efficiency'] . '%',
            'Cajas abiertas' => $summary['cash_balances']['opened'],
            'Cajas cerradas' => $summary['cash_balances']['closed'],
        ],
    ])

    @foreach($activities as $activity)
    <div style="margin-top: var(--spacing-lg); padding: var(--spacing-lg); border: 1px solid var(--color-border-light); border-radius: var(--border-radius); background: var(--color-surface);">
        <h3 style="color: var(--color-primary); margin-top: 0;">{{ $activity['cobrador_name'] }}</h3>

        <h4 style="color: var(--color-primary-dark); margin-top: var(--spacing-lg);">Estado de Caja</h4>
        <p style="font-size: var(--font-size-body);">
            <strong>Estado:</strong> {{ $activity['cash_balance']['status'] }} |
            <strong>Inicial:</strong> Bs {{ number_format($activity['cash_balance']['initial_amount'], 2) }} |
            <strong>Cobrado:</strong> Bs {{ number_format($activity['cash_balance']['collected_amount'], 2) }} |
            <strong>Prestado:</strong> Bs {{ number_format($activity['cash_balance']['lent_amount'], 2) }} |
            <strong>Final:</strong> Bs {{ number_format($activity['cash_balance']['final_amount'], 2) }}
        </p>

        <h4 style="color: var(--color-primary-dark);">Créditos Entregados ({{ $activity['credits_delivered']['count'] }})</h4>
        @if(count($activity['credits_delivered']['details']) > 0)
        <table class="report-table" style="margin-top: var(--spacing-md);">
            <thead><tr><th>ID</th><th>Cliente</th><th>Monto</th></tr></thead>
            <tbody>
            @foreach($activity['credits_delivered']['details'] as $credit)
            <tr>
                <td>{{ $credit['id'] }}</td>
                <td>{{ $credit['client'] }}</td>
                <td>Bs {{ number_format($credit['amount'], 2) }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @else
        <p style="color: var(--color-text-secondary);">No se entregaron créditos</p>
        @endif

        <h4 style="color: var(--color-primary-dark); margin-top: var(--spacing-lg);">Pagos Cobrados ({{ $activity['payments_collected']['count'] }})</h4>
        @if(count($activity['payments_collected']['details']) > 0)
        <table class="report-table" style="margin-top: var(--spacing-md);">
            <thead><tr><th>ID</th><th>Cliente</th><th>Monto</th></tr></thead>
            <tbody>
            @foreach($activity['payments_collected']['details'] as $payment)
            <tr>
                <td>{{ $payment['id'] }}</td>
                <td>{{ $payment['client'] }}</td>
                <td>Bs {{ number_format($payment['amount'], 2) }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @else
        <p style="color: var(--color-text-secondary);">No se cobraron pagos</p>
        @endif

        <h4 style="color: var(--color-primary-dark); margin-top: var(--spacing-lg);">Eficiencia de Cobranza</h4>
        <p style="font-size: var(--font-size-body);">
            <strong>Esperados:</strong> {{ $activity['expected_payments']['count'] }} |
            <strong>Cobrados:</strong> {{ $activity['expected_payments']['collected'] }} |
            <strong>Pendientes:</strong> {{ $activity['expected_payments']['pending'] }} |
            <strong>Eficiencia:</strong> {{ $activity['expected_payments']['efficiency'] }}%
        </p>
    </div>
    @endforeach

    @include('reports.components.footer')
@endsection
