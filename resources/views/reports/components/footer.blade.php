{{--
    Componente Footer Reutilizable

    Props:
    - $system_name: string (opcional) - Nombre del sistema (default: "Sistema de Cobrador")
    - $additional_info: array (opcional) - Información adicional a mostrar
      Ejemplo: [
          'Total registros' => '150',
          'Período' => '01/10/2024 - 31/10/2024',
      ]
--}}

<div class="report-footer">
    <p>{{ $system_name ?? 'Reporte generado por el Sistema de Cobrador' }}</p>

    @if(!empty($additional_info))
    <div class="footer-info">
        @foreach($additional_info as $label => $value)
        <span><strong>{{ $label }}:</strong> {{ $value }}</span>
        @endforeach
    </div>
    @endif
</div>
