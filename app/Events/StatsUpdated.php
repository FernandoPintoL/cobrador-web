<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 📊 Evento para broadcast de estadísticas actualizadas
 *
 * Se dispara cuando las estadísticas cambiam (pago creado, crédito entregado, etc)
 * y se envía a través de WebSocket para actualizar dashboards en tiempo real.
 *
 * Soporta tres tipos de canales:
 * - 'global': Estadísticas globales (todos los usuarios)
 * - 'cobrador.{cobradorId}': Estadísticas de un cobrador específico
 * - 'manager.{managerId}': Estadísticas de un manager y su equipo
 */
class StatsUpdated
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    /**
     * Tipo de estadísticas actualizadas
     * 'global', 'cobrador', o 'manager'
     */
    public string $type;

    /**
     * ID del cobrador o manager (null para global)
     */
    public ?int $userId;

    /**
     * Los datos de estadísticas actualizadas
     */
    public array $stats;

    public function __construct(string $type, array $stats, ?int $userId = null)
    {
        $this->type = $type;
        $this->stats = $stats;
        $this->userId = $userId;
    }

    /**
     * Obtener los canales en los que se debe broadcast este evento
     */
    public function broadcastOn(): array
    {
        return match ($this->type) {
            'global' => [
                new Channel('stats.global'),
            ],
            'cobrador' => [
                new Channel("stats.cobrador.{$this->userId}"),
            ],
            'manager' => [
                new Channel("stats.manager.{$this->userId}"),
            ],
            default => [],
        };
    }

    /**
     * El nombre del evento para broadcast
     */
    public function broadcastAs(): string
    {
        return 'stats.updated';
    }

    /**
     * Datos a serializar para broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'stats' => $this->stats,
            'user_id' => $this->userId,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
