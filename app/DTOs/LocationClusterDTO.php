<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

/**
 * DTO para representar un cluster de ubicación agrupado
 *
 * Estructura JSON para Flutter:
 * {
 *   "cluster_id": "19.4326,-99.1332",
 *   "location": {
 *     "latitude": 19.4326,
 *     "longitude": -99.1332,
 *     "address": "Calle Principal 123"
 *   },
 *   "cluster_summary": {
 *     "total_people": 3,
 *     "total_credits": 5,
 *     "total_amount": 5500.00,
 *     "total_balance": 3500.00,
 *     "overdue_count": 2,
 *     "overdue_amount": 1500.00,
 *     "active_count": 3,
 *     "active_amount": 2000.00,
 *     "completed_count": 0,
 *     "completed_amount": 0.00
 *   },
 *   "cluster_status": "overdue",
 *   "people": [...]
 * }
 */
class LocationClusterDTO
{
    public function __construct(
        public string $cluster_id,
        public array $location,
        public array $cluster_summary,
        public string $cluster_status,
        public array $people,
    ) {}

    /**
     * Convertir a array para respuesta JSON
     */
    public function toArray(): array
    {
        return [
            'cluster_id'       => $this->cluster_id,
            'location'         => $this->location,
            'cluster_summary'  => $this->cluster_summary,
            'cluster_status'   => $this->cluster_status,
            'people'           => $this->people,
        ];
    }
}
