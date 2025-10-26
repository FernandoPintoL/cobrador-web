<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource genérico para datos de reportes
 * Utilizado por: Performance, CashFlowForecast, WaitingList, DailyActivity, Portfolio, Commissions
 */
class ReportDataResource extends JsonResource
{
    public function toArray($request): array
    {
        // Retorna el objeto tal como está, ya que contiene la estructura de datos transformados
        return parent::toArray($request);
    }
}
