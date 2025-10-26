@extends('reports.layouts.base')

@section('content')
    @include('reports.components.header', [
        'title' => 'Reporte de Comisiones',
        'generated_at' => $generated_at,
        'generated_by' => $generated_by,
    ])

    @include('reports.components.summary-section', [
        'title' => 'Resumen del Período',
        'columns' => 2,
        'items' => [
            'Período' => $summary['period']['start'] . ' - ' . $summary['period']['end'],
            'Tasa de comisión' => $summary['commission_rate'] . '%',
            'Total cobradores' => $summary['total_cobradores'],
            'Total cobrado' => 'Bs ' . number_format($summary['totals']['collected'], 2),
            'Total prestado' => 'Bs ' . number_format($summary['totals']['lent'], 2),
            'Total comisiones' => 'Bs ' . number_format($summary['totals']['commissions'], 2),
            'Total bonos' => 'Bs ' . number_format($summary['totals']['bonuses'], 2),
        ],
    ])

    <div style="margin: var(--spacing-lg) 0; padding: var(--spacing-lg); background: var(--color-surface); border-radius: var(--border-radius); border-left: 4px solid var(--color-success);">
        <h3 style="color: var(--color-primary); margin-top: 0;">Top 5 Mejores Cobradores</h3>
        @forelse($summary['top_earners'] as $index => $earner)
        <p style="margin: var(--spacing-sm) 0; font-size: var(--font-size-body);">
            <strong>{{ $index + 1 }}. {{ $earner['name'] }}</strong> -
            Comisión: Bs {{ number_format($earner['commission'], 2) }}
            ({{ $earner['collection_percentage'] }}% cumplimiento)
        </p>
        @empty
        <p style="color: var(--color-text-secondary);">No hay datos disponibles</p>
        @endforelse
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th>Cobrador</th>
                <th>Pagos Cobrados</th>
                <th>Monto Cobrado</th>
                <th>Créd. Entregados</th>
                <th>Monto Prestado</th>
                <th>Tasa</th>
                <th>Comisión Base</th>
                <th>Bonus</th>
                <th>Comisión Total</th>
                <th>Cobro Esperado</th>
                <th>% Cumplimiento</th>
            </tr>
        </thead>
        <tbody>
            @forelse($commissions as $item)
            <tr style="background-color: {{ $item['performance']['collection_percentage'] >= 80 ? 'var(--color-status-clean)' : '' }}; {{ $item['performance']['collection_percentage'] >= 80 ? 'border-left: 3px solid var(--color-success);' : '' }}">
                <td>{{ $item['cobrador_name'] }}</td>
                <td>{{ $item['payments_collected']['count'] }}</td>
                <td>Bs {{ number_format($item['payments_collected']['total_amount'], 2) }}</td>
                <td>{{ $item['credits_delivered']['count'] }}</td>
                <td>Bs {{ number_format($item['credits_delivered']['total_amount'], 2) }}</td>
                <td>{{ $item['commission']['rate'] }}%</td>
                <td>Bs {{ number_format($item['commission']['on_collection'], 2) }}</td>
                <td>Bs {{ number_format($item['commission']['bonus'], 2) }}</td>
                <td><strong>Bs {{ number_format($item['commission']['total'], 2) }}</strong></td>
                <td>Bs {{ number_format($item['performance']['expected_collection'], 2) }}</td>
                <td>{{ number_format($item['performance']['collection_percentage'], 2) }}%</td>
            </tr>
            @empty
            <tr>
                <td colspan="11" style="text-align: center; padding: var(--spacing-lg); color: var(--color-text-secondary);">
                    No hay datos disponibles
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @include('reports.components.footer', [
        'additional_info' => [
            'Nota' => 'Bonus 20% para cumplimiento ≥ 80% (resaltados)',
        ],
    ])
@endsection
