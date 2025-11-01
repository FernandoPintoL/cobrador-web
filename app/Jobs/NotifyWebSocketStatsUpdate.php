<?php

namespace App\Jobs;

use App\Services\WebSocketNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * 📊 Job para notificar al servidor WebSocket sobre actualizaciones de estadísticas
 *
 * Se ejecuta en background para no bloquear el flujo de pagos/créditos.
 * Envía la actualización de estadísticas al servidor WebSocket.
 */
class NotifyWebSocketStatsUpdate implements ShouldQueue
{
    use Queueable;

    protected string $type;
    protected array $stats;
    protected ?int $userId;

    public function __construct(string $type, array $stats, ?int $userId = null)
    {
        $this->type = $type;
        $this->stats = $stats;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(WebSocketNotificationService $wsService): void
    {
        try {
            // Enviar notificación de actualización de estadísticas al WebSocket
            $response = $wsService->notifyStatsUpdate($this->type, $this->stats, $this->userId);

            if ($response) {
                Log::debug("Stats update notification sent successfully", [
                    'type' => $this->type,
                    'user_id' => $this->userId,
                ]);
            } else {
                Log::warning("Stats update notification failed or WebSocket disabled", [
                    'type' => $this->type,
                    'user_id' => $this->userId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error notifying WebSocket about stats update', [
                'error' => $e->getMessage(),
                'type' => $this->type,
                'user_id' => $this->userId,
            ]);

            // No re-lanzamos para que el job no falle
        }
    }
}
