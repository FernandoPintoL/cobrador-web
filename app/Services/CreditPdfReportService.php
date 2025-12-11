<?php

namespace App\Services;

use App\Models\Credit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * Servicio para generar reportes de créditos en formato PDF
 * Utiliza emojis, colores y el sistema estandarizado de severidad
 */
class CreditPdfReportService
{
    /**
     * Genera el reporte PDF de créditos
     *
     * @param Collection $credits Colección de créditos
     * @param array $summary Resumen de totales
     * @param string $filename Nombre del archivo a descargar
     * @return \Illuminate\Http\Response
     */
    public function generate(Collection $credits, array $summary = [], string $filename = 'reporte-creditos.pdf')
    {
        // Preparar datos para la vista
        $data = [
            'credits' => $credits,
            'summary' => $summary,
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'formatter' => CreditReportFormatterService::class,
        ];

        // Generar PDF con la vista Blade
        $pdf = Pdf::loadView('reports.credits-pdf', $data);

        // Configuración del PDF
        $pdf->setPaper('a4', 'landscape'); // Horizontal para más columnas
        $pdf->setOption('defaultFont', 'DejaVu Sans'); // Soporte de Unicode/emojis

        return $pdf->download($filename);
    }

    /**
     * Genera el PDF y lo retorna como stream (para preview)
     *
     * @param Collection $credits Colección de créditos
     * @param array $summary Resumen de totales
     * @return \Illuminate\Http\Response
     */
    public function stream(Collection $credits, array $summary = [])
    {
        $data = [
            'credits' => $credits,
            'summary' => $summary,
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'formatter' => CreditReportFormatterService::class,
        ];

        $pdf = Pdf::loadView('reports.credits-pdf', $data);
        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->stream();
    }
}
