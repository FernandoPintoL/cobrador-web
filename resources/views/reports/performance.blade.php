@extends('reports.layouts.base')

@section('content')
    @include('reports.components.header', [
        'title' => 'Reporte de Rendimiento',
        'generated_at' => $generated_at,
        'generated_by' => $generated_by,
    ])

    @include('reports.components.summary-section', [
        'title' => 'Resumen del Período',
        'columns' => 2,
        'items' => [
            'Período' => $summary['period']['start'] . ' - ' . $summary['period']['end'],
            'Total cobradores' => $summary['total_cobradores'],
            'Créditos entregados' => $summary['totals']['credits_delivered'],
            'Monto prestado' => 'Bs ' . number_format($summary['totals']['amount_lent'], 2),
            'Pagos cobrados' => $summary['totals']['payments_collected'],
            'Monto cobrado' => 'Bs ' . number_format($summary['totals']['amount_collected'], 2),
            'Tasa promedio cobranza' => number_format($summary['averages']['collection_rate'], 2) . '%',
            'Calidad promedio cartera' => number_format($summary['averages']['portfolio_quality'], 2) . '%',
        ],
    ])

    <table class="report-table">
        <thead>
            <tr>
                <th>Cobrador</th>
                <th>Manager</th>
                <th>Créd. Entregados</th>
                <th>Monto Prestado</th>
                <th>Pagos Cobrados</th>
                <th>Monto Cobrado</th>
                <th>Tasa Cobranza</th>
                <th>Créd. Activos</th>
                <th>Créd. Completados</th>
                <th>En Mora</th>
                <th>Calidad Cartera</th>
                <th>Días Prom.</th>
                <th>Score</th>
                <th>Clientes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($performance as $item)
            <tr>
                <td>{{ $item['cobrador_name'] }}</td>
                <td>{{ $item['manager_name'] }}</td>
                <td>{{ $item['metrics']['credits_delivered'] }}</td>
                <td>Bs {{ number_format($item['metrics']['total_amount_lent'], 2) }}</td>
                <td>{{ $item['metrics']['payments_collected_count'] }}</td>
                <td>Bs {{ number_format($item['metrics']['total_amount_collected'], 2) }}</td>
                <td>{{ $item['metrics']['collection_rate'] }}%</td>
                <td>{{ $item['metrics']['active_credits'] }}</td>
                <td>{{ $item['metrics']['completed_credits'] }}</td>
                <td>{{ $item['metrics']['overdue_credits'] }}</td>
                <td>{{ $item['metrics']['portfolio_quality'] }}%</td>
                <td>{{ number_format($item['metrics']['avg_days_to_complete'], 1) }}</td>
                <td>{{ number_format($item['metrics']['efficiency_score'], 2) }}</td>
                <td>{{ $item['metrics']['active_clients'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="14" style="text-align: center; padding: var(--spacing-lg); color: var(--color-text-secondary);">
                    No hay datos disponibles
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @include('reports.components.footer')
@endsection
