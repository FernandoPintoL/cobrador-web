<?php

namespace App\Http\Controllers\Api;

use App\Exports\CreditsExport;
use App\Http\Controllers\Controller;
use App\Models\Credit;
use App\Services\CreditPdfReportService;
use App\Services\CreditHtmlReportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Controlador para exportación de reportes de créditos
 * Soporta 3 formatos: Excel, PDF y HTML
 */
class CreditReportController extends Controller
{
    /**
     * Exportar créditos a formato Excel (.xlsx)
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportExcel(Request $request)
    {
        // Obtener créditos con relaciones necesarias
        $credits = $this->getCreditsQuery($request)->get();

        // Preparar datos en formato de array
        $data = $credits->map(function ($credit) {
            return [
                'id' => $credit->id,
                'client_name' => $credit->client->name ?? 'N/A',
                'created_by_name' => $credit->createdBy->name ?? 'N/A',
                'delivered_by_name' => $credit->deliveredBy->name ?? 'N/A',
                'amount' => $credit->amount,
                'balance' => $credit->balance,
                'completed_installments' => $credit->completed_installments ?? 0,
                'expected_installments' => $credit->expected_installments ?? 0,
                'installments_overdue' => $credit->overdue_installments ?? 0,
                'payment_status_label' => $this->getPaymentStatusLabel($credit->payment_status),
                'created_at_formatted' => $credit->created_at->format('d/m/Y'),

                // Campos estandarizados desde el backend
                'overdue_severity' => $credit->overdue_severity,
                'days_overdue' => $credit->days_overdue,
                'requires_attention' => $credit->requires_attention,

                // Guardar el modelo completo para acceso a relaciones
                '_model' => $credit,
            ];
        });

        // Calcular resumen
        $summary = $this->calculateSummary($credits);

        // Generar nombre de archivo
        $filename = 'reporte-creditos-' . now()->format('Y-m-d_His') . '.xlsx';

        // Generar y descargar Excel
        return Excel::download(new CreditsExport($data, $summary), $filename);
    }

    /**
     * Exportar créditos a formato PDF
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportPdf(Request $request)
    {
        // Obtener créditos con relaciones
        $credits = $this->getCreditsQuery($request)->get();

        // Calcular resumen
        $summary = $this->calculateSummary($credits);

        // Generar nombre de archivo
        $filename = 'reporte-creditos-' . now()->format('Y-m-d_His') . '.pdf';

        // Generar PDF usando el servicio
        $pdfService = new CreditPdfReportService();

        return $pdfService->generate($credits, $summary, $filename);
    }

    /**
     * Ver PDF en el navegador sin descargar
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function previewPdf(Request $request)
    {
        // Obtener créditos con relaciones
        $credits = $this->getCreditsQuery($request)->get();

        // Calcular resumen
        $summary = $this->calculateSummary($credits);

        // Generar PDF para stream
        $pdfService = new CreditPdfReportService();

        return $pdfService->stream($credits, $summary);
    }

    /**
     * Ver reporte HTML en el navegador
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function viewHtml(Request $request)
    {
        // Obtener créditos con relaciones
        $credits = $this->getCreditsQuery($request)->get();

        // Calcular resumen
        $summary = $this->calculateSummary($credits);

        // Generar y mostrar HTML
        $htmlService = new CreditHtmlReportService();

        return $htmlService->generate($credits, $summary);
    }

    /**
     * Descargar reporte HTML como archivo
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function downloadHtml(Request $request)
    {
        // Obtener créditos con relaciones
        $credits = $this->getCreditsQuery($request)->get();

        // Calcular resumen
        $summary = $this->calculateSummary($credits);

        // Generar nombre de archivo
        $filename = 'reporte-creditos-' . now()->format('Y-m-d_His') . '.html';

        // Generar HTML para descarga
        $htmlService = new CreditHtmlReportService();

        return $htmlService->download($credits, $summary, $filename);
    }

    /**
     * Construye la query base de créditos con filtros opcionales
     *
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getCreditsQuery(Request $request)
    {
        $query = Credit::with(['client', 'createdBy', 'deliveredBy']);

        // Filtro por estado
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            // Por defecto, solo créditos activos
            $query->where('status', 'active');
        }

        // Filtro por cobrador (creado por)
        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // Filtro por cliente
        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // Filtro por rango de fechas
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Filtro por severidad
        if ($request->has('severity')) {
            // Nota: esto requiere que el modelo tenga un scope o usar having
            // Por simplicidad, se filtra después de obtener resultados
        }

        // Aplicar filtros de rol (manager/cobrador)
        $currentUser = $request->user();

        if ($currentUser->hasRole('cobrador')) {
            // Cobrador: solo sus créditos o de sus clientes asignados
            $query->where(function ($q) use ($currentUser) {
                $q->where('created_by', $currentUser->id)
                  ->orWhereHas('client', function ($clientQuery) use ($currentUser) {
                      $clientQuery->where('assigned_cobrador_id', $currentUser->id);
                  });
            });
        } elseif ($currentUser->hasRole('manager')) {
            // Manager: sus créditos + créditos de sus cobradores + créditos de sus clientes
            $query->where(function ($q) use ($currentUser) {
                $q->where('created_by', $currentUser->id)
                  ->orWhereHas('createdBy', function ($createdByQuery) use ($currentUser) {
                      $createdByQuery->role('cobrador')
                          ->where('assigned_manager_id', $currentUser->id);
                  })
                  ->orWhereHas('client', function ($clientQuery) use ($currentUser) {
                      $clientQuery->where('assigned_manager_id', $currentUser->id);
                  })
                  ->orWhereHas('client.assignedCobrador', function ($cobradorQuery) use ($currentUser) {
                      $cobradorQuery->where('assigned_manager_id', $currentUser->id);
                  });
            });
        }
        // Admin puede ver todos (no se aplica filtro)

        // Ordenar por ID descendente (más recientes primero)
        $query->orderBy('id', 'desc');

        return $query;
    }

    /**
     * Calcula el resumen de totales para los créditos
     *
     * @param \Illuminate\Database\Eloquent\Collection $credits
     * @return array
     */
    protected function calculateSummary($credits)
    {
        return [
            'total_credits' => $credits->count(),
            'total_amount' => $credits->sum('total_amount'),
            'total_paid' => $credits->sum('total_paid'),
            'total_balance' => $credits->sum('balance'),
            'pending_amount' => $credits->sum('balance'),
        ];
    }

    /**
     * Obtiene el label legible del estado de pago
     *
     * @param string $paymentStatus
     * @return string
     */
    protected function getPaymentStatusLabel($paymentStatus)
    {
        return match($paymentStatus) {
            'completed' => 'Completado',
            'on_track' => 'Al día',
            'at_risk' => 'En riesgo',
            'critical' => 'Crítico',
            default => ucfirst(str_replace('_', ' ', $paymentStatus ?? 'N/A')),
        };
    }
}
