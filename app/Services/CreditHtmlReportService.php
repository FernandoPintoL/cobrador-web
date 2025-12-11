<?php

namespace App\Services;

use App\Models\Credit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

/**
 * Servicio para generar reportes de créditos en formato HTML
 * Utiliza emojis, colores y el sistema estandarizado de severidad
 * Ideal para visualización en navegador o exportación directa
 */
class CreditHtmlReportService
{
    /**
     * Genera el reporte HTML de créditos
     *
     * @param Collection $credits Colección de créditos
     * @param array $summary Resumen de totales
     * @return \Illuminate\Http\Response
     */
    public function generate(Collection $credits, array $summary = [])
    {
        // Preparar datos para la vista
        $data = [
            'credits' => $credits,
            'summary' => $summary,
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'formatter' => CreditReportFormatterService::class,
        ];

        // Renderizar la vista y retornar como respuesta HTML
        $html = View::make('reports.credits-html', $data)->render();

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Genera el reporte HTML y lo descarga como archivo
     *
     * @param Collection $credits Colección de créditos
     * @param array $summary Resumen de totales
     * @param string $filename Nombre del archivo a descargar
     * @return \Illuminate\Http\Response
     */
    public function download(Collection $credits, array $summary = [], string $filename = 'reporte-creditos.html')
    {
        $data = [
            'credits' => $credits,
            'summary' => $summary,
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'formatter' => CreditReportFormatterService::class,
        ];

        $html = View::make('reports.credits-html', $data)->render();

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
