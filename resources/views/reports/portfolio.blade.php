@extends('reports.layouts.base')

@section('content')
    @include('reports.components.header', [
        'title' => 'Reporte de Cartera',
        'generated_at' => $generated_at,
        'generated_by' => $generated_by,
    ])

    @include('reports.components.summary-section', [
        'title' => 'Resumen General de Cartera',
        'columns' => 3,
        'items' => [
            'Total créditos' => $summary['total_credits'],
            'Créditos activos' => $summary['active_credits'],
            'Créditos completados' => $summary['completed_credits'],
            'Créditos al día' => $summary['current_credits'],
            'Créditos en mora' => $summary['overdue_credits'],
            'Total prestado' => 'Bs ' . number_format($summary['total_lent'], 2),
            'Total cobrado' => 'Bs ' . number_format($summary['total_collected'], 2),
            'Balance activo' => 'Bs ' . number_format($summary['active_balance'], 2),
            'Balance en mora' => 'Bs ' . number_format($summary['overdue_balance'], 2),
            'Calidad cartera' => $summary['portfolio_quality'] . '%',
            'Tasa cobranza' => $summary['collection_rate'] . '%',
        ],
    ])

    <h3 style="margin-top: var(--spacing-lg); color: var(--color-primary); border-bottom: 2px solid var(--color-primary); padding-bottom: var(--spacing-sm);">Cartera por Cobrador</h3>
    <table class="report-table">
        <thead>
            <tr>
                <th>Cobrador</th>
                <th>Total Créd.</th>
                <th>Activos</th>
                <th>Completados</th>
                <th>Balance Total</th>
                <th>Total Prestado</th>
                <th>En Mora</th>
                <th>Monto Mora</th>
                <th>Calidad</th>
            </tr>
        </thead>
        <tbody>
            @forelse($portfolio_by_cobrador as $cobrador => $data)
            <tr>
                <td>{{ $cobrador }}</td>
                <td>{{ $data['total_credits'] }}</td>
                <td>{{ $data['active_credits'] }}</td>
                <td>{{ $data['completed_credits'] }}</td>
                <td>Bs {{ number_format($data['total_balance'], 2) }}</td>
                <td>Bs {{ number_format($data['total_lent'], 2) }}</td>
                <td>{{ $data['overdue_credits'] }}</td>
                <td>Bs {{ number_format($data['overdue_amount'], 2) }}</td>
                <td>{{ $data['portfolio_quality'] }}%</td>
            </tr>
            @empty
            <tr><td colspan="9" style="text-align:center;padding:var(--spacing-lg);color:var(--color-text-secondary);">No hay datos</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3 style="margin-top: var(--spacing-lg); color: var(--color-primary); border-bottom: 2px solid var(--color-primary); padding-bottom: var(--spacing-sm);">Cartera por Categoría de Cliente</h3>
    <table class="report-table">
        <thead>
            <tr>
                <th>Categoría</th>
                <th>Total Créditos</th>
                <th>Balance Activo</th>
                <th>Total Prestado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($portfolio_by_category as $category => $data)
            <tr>
                <td>{{ $category }}</td>
                <td>{{ $data['total_credits'] }}</td>
                <td>Bs {{ number_format($data['active_balance'], 2) }}</td>
                <td>Bs {{ number_format($data['total_lent'], 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;padding:var(--spacing-lg);color:var(--color-text-secondary);">No hay datos</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3 style="margin-top: var(--spacing-lg); color: var(--color-primary); border-bottom: 2px solid var(--color-primary); padding-bottom: var(--spacing-sm);">Top 10 Clientes por Balance</h3>
    <table class="report-table">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Categoría</th>
                <th>Crédito ID</th>
                <th>Balance</th>
                <th>Monto Total</th>
                <th>% Completado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($top_clients_by_balance as $client)
            <tr>
                <td>{{ $client['client_name'] }}</td>
                <td>{{ $client['client_category'] }}</td>
                <td>{{ $client['credit_id'] }}</td>
                <td>Bs {{ number_format($client['balance'], 2) }}</td>
                <td>Bs {{ number_format($client['total_amount'], 2) }}</td>
                <td>{{ $client['completion_rate'] }}%</td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;padding:var(--spacing-lg);color:var(--color-text-secondary);">No hay datos</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: var(--spacing-lg); padding: var(--spacing-lg); background: var(--color-surface); border-radius: var(--border-radius);">
        <h3 style="color: var(--color-primary); margin-top: 0;">Distribución por Antigüedad</h3>
        <p style="margin: var(--spacing-xs) 0;"><strong>0-30 días:</strong> {{ $portfolio_by_age['0_30_days'] }} créditos</p>
        <p style="margin: var(--spacing-xs) 0;"><strong>31-60 días:</strong> {{ $portfolio_by_age['31_60_days'] }} créditos</p>
        <p style="margin: var(--spacing-xs) 0;"><strong>61-90 días:</strong> {{ $portfolio_by_age['61_90_days'] }} créditos</p>
        <p style="margin: var(--spacing-xs) 0;"><strong>Más de 90 días:</strong> {{ $portfolio_by_age['over_90_days'] }} créditos</p>
    </div>

    @include('reports.components.footer')
@endsection
