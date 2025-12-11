<?php

/**
 * RUTAS DE EJEMPLO PARA REPORTES DE CRÉDITOS
 *
 * Copia estas rutas y pégalas en tu archivo routes/api.php
 *
 * Asegúrate de importar el controlador al inicio del archivo:
 * use App\Http\Controllers\Api\CreditReportController;
 */

// ============================================================================
// RUTAS DE EXPORTACIÓN DE REPORTES - CRÉDITOS
// ============================================================================

Route::middleware(['auth:sanctum'])->group(function () {

    // Grupo de rutas para reportes de créditos
    Route::prefix('reports/credits')->name('reports.credits.')->group(function () {

        // ===== EXCEL (.xlsx) =====
        Route::get('/export/excel', [CreditReportController::class, 'exportExcel'])
            ->name('export.excel');

        // ===== PDF (.pdf) =====
        Route::get('/export/pdf', [CreditReportController::class, 'exportPdf'])
            ->name('export.pdf');

        // Ver PDF en navegador (sin descargar)
        Route::get('/preview/pdf', [CreditReportController::class, 'previewPdf'])
            ->name('preview.pdf');

        // ===== HTML (.html) =====
        // Ver HTML directamente en navegador
        Route::get('/view/html', [CreditReportController::class, 'viewHtml'])
            ->name('view.html');

        // Descargar HTML como archivo
        Route::get('/export/html', [CreditReportController::class, 'downloadHtml'])
            ->name('export.html');
    });
});

/**
 * EJEMPLOS DE USO DE LAS RUTAS
 *
 * Las rutas soportan los siguientes parámetros de query opcionales:
 * - status: Estado del crédito (active, completed, cancelled)
 * - created_by: ID del usuario que creó el crédito
 * - client_id: ID del cliente
 * - start_date: Fecha de inicio (formato: Y-m-d)
 * - end_date: Fecha de fin (formato: Y-m-d)
 *
 * Ejemplos:
 *
 * 1. Exportar todos los créditos activos a Excel:
 *    GET /api/reports/credits/export/excel
 *
 * 2. Exportar créditos de un cobrador específico a PDF:
 *    GET /api/reports/credits/export/pdf?created_by=5
 *
 * 3. Ver en HTML créditos de un rango de fechas:
 *    GET /api/reports/credits/view/html?start_date=2024-01-01&end_date=2024-12-31
 *
 * 4. Exportar todos los créditos (activos, completados, etc.) a Excel:
 *    GET /api/reports/credits/export/excel?status=all
 *
 * 5. Vista previa de PDF en navegador:
 *    GET /api/reports/credits/preview/pdf
 *
 * ============================================================================
 * RESPUESTAS ESPERADAS
 * ============================================================================
 *
 * Excel (.xlsx):
 * - Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
 * - Content-Disposition: attachment; filename="reporte-creditos-2024-12-10_143022.xlsx"
 *
 * PDF (.pdf):
 * - Content-Type: application/pdf
 * - Content-Disposition: attachment; filename="reporte-creditos-2024-12-10_143022.pdf"
 *
 * PDF Preview:
 * - Content-Type: application/pdf
 * - Content-Disposition: inline (se muestra en navegador)
 *
 * HTML (view):
 * - Content-Type: text/html; charset=UTF-8
 * - Se renderiza directamente en el navegador
 *
 * HTML (download):
 * - Content-Type: text/html; charset=UTF-8
 * - Content-Disposition: attachment; filename="reporte-creditos-2024-12-10_143022.html"
 *
 * ============================================================================
 * PERMISOS Y SEGURIDAD
 * ============================================================================
 *
 * Todas las rutas requieren autenticación con Sanctum (middleware auth:sanctum)
 *
 * El controlador aplica filtros automáticos según el rol del usuario:
 *
 * - Admin: Ve todos los créditos sin restricción
 *
 * - Manager: Ve sus créditos + créditos de sus cobradores asignados +
 *           créditos de sus clientes directos e indirectos
 *
 * - Cobrador: Ve solo los créditos que creó + créditos de sus clientes asignados
 *
 * - Cliente: No tiene acceso a reportes (puedes agregar un endpoint específico si lo necesitas)
 *
 * ============================================================================
 * PERSONALIZACIÓN ADICIONAL
 * ============================================================================
 *
 * Si necesitas agregar más filtros o funcionalidad:
 *
 * 1. Edita el método getCreditsQuery() en CreditReportController.php
 * 2. Agrega nuevos parámetros de request
 * 3. Aplica los filtros a la query
 *
 * Ejemplo - Filtrar por severidad:
 *
 * if ($request->has('severity')) {
 *     // Filtrar después de obtener resultados porque es un atributo calculado
 *     $credits = $query->get()->filter(function ($credit) use ($request) {
 *         return $credit->overdue_severity === $request->severity;
 *     });
 * }
 *
 * ============================================================================
 */
