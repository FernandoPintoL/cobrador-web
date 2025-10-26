{{--
    Componente Header Reutilizable

    Props:
    - $title: string - Título del reporte
    - $generated_at: \Carbon\Carbon - Fecha de generación
    - $generated_by: string - Usuario que generó el reporte
    - $subtitle: string (opcional) - Subtítulo adicional
--}}

<div class="report-header">
    <h1>{{ $title }}</h1>

    @if(!empty($subtitle))
    <p style="margin-top: var(--spacing-sm); color: var(--color-text-secondary); font-size: var(--font-size-body);">
        {{ $subtitle }}
    </p>
    @endif

    <div class="header-meta">
        <div class="meta-item">
            <span class="meta-label">Generado:</span>
            <span>{{ $generated_at->format('d/m/Y H:i:s') }}</span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Por:</span>
            <span>{{ $generated_by }}</span>
        </div>
    </div>
</div>
