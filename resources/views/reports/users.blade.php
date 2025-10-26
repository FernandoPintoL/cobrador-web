@extends('reports.layouts.base')

@section('content')
    @include('reports.components.header', [
        'title' => 'Reporte de Usuarios',
        'generated_at' => $generated_at,
        'generated_by' => $generated_by,
    ])

    <div class="summary-section">
        <h3>Resumen</h3>

        <div class="summary-item" style="margin-bottom: var(--spacing-md);">
            <p><strong>Total de usuarios:</strong></p>
            <div class="value">{{ $summary['total_users'] }}</div>
        </div>

        <h4 style="margin: var(--spacing-lg) 0 var(--spacing-md) 0; color: var(--color-primary);">Usuarios por Rol</h4>
        <div class="summary-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
            @foreach($summary['by_role'] as $role => $count)
            <div class="summary-item">
                <p><strong>{{ $role }}</strong></p>
                <div class="value">{{ $count }}</div>
            </div>
            @endforeach
        </div>

        <h4 style="margin: var(--spacing-lg) 0 var(--spacing-md) 0; color: var(--color-primary);">Clientes por Categoría</h4>
        <div class="summary-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
            @foreach($summary['by_category'] as $category => $count)
            <div class="summary-item">
                <p><strong>Categoría {{ $category }}</strong></p>
                <div class="value">{{ $count }}</div>
            </div>
            @endforeach
        </div>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Teléfono</th>
                <th>CI</th>
                <th>Roles</th>
                <th>Categoría</th>
                <th>Fecha Registro</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            <tr>
                <td>{{ $user->id }}</td>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email ?? '-' }}</td>
                <td>{{ $user->phone ?? '-' }}</td>
                <td>{{ $user->ci ?? '-' }}</td>
                <td>{{ $user->roles->pluck('name')->join(', ') }}</td>
                <td>{{ $user->client_category ?? '-' }}</td>
                <td>{{ $user->created_at->format('d/m/Y') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align: center; padding: var(--spacing-lg); color: var(--color-text-secondary);">
                    No hay datos disponibles
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @include('reports.components.footer')
@endsection
