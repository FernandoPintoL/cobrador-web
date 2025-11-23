<?php

namespace App\Services;

use App\Models\CashBalance;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebSocketNotificationService
{
    protected $wsUrl;

    protected $wsSecret;

    protected $enabled;

    protected $timeout;

    protected $maxRetries = 3;

    protected $retryDelay = 1000; // milliseconds

    public function __construct()
    {
        $this->wsUrl = config('websocket.url', env('WEBSOCKET_URL', 'http://localhost:3001'));
        $this->wsSecret = config('websocket.secret', env('WS_SECRET'));
        $this->enabled = config('websocket.enabled', env('WEBSOCKET_ENABLED', true));
        $this->timeout = config('websocket.timeout', env('WEBSOCKET_TIMEOUT', 5));
    }

    /**
     * Verificar si el servicio WebSocket está habilitado
     */
    public function isEnabled(): bool
    {
        return (bool) $this->enabled;
    }

    /**
     * Verificar salud del servidor WebSocket (con cache)
     */
    public function checkHealth(): array
    {
        if (! $this->isEnabled()) {
            return ['status' => 'disabled', 'message' => 'WebSocket service is disabled'];
        }

        // Cache por 30 segundos
        return Cache::remember('websocket_health', 30, function () {
            try {
                $response = Http::timeout($this->timeout)->get("{$this->wsUrl}/health");

                if ($response->successful()) {
                    return $response->json() + ['status' => 'healthy'];
                }

                return ['status' => 'unhealthy', 'message' => 'Server responded with error'];
            } catch (\Exception $e) {
                Log::warning('WebSocket health check failed', ['error' => $e->getMessage()]);

                return ['status' => 'unreachable', 'message' => $e->getMessage()];
            }
        });
    }

    /**
     * Notificar crédito creado (pendiente de aprobación)
     */
    public function notifyCreditCreated(Credit $credit, User $manager, User $cobrador): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $this->sendCreditNotification('created', $credit, $manager, $cobrador);
    }

    /**
     * Notificar crédito aprobado
     */
    public function notifyCreditApproved(Credit $credit, User $manager, User $cobrador, bool $entregaInmediata = false): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        // Agregar flag de entrega inmediata al crédito
        $creditData = $credit->toArray();
        $creditData['entrega_inmediata'] = $entregaInmediata;

        return $this->sendCreditNotification('approved', $credit, $manager, $cobrador);
    }

    /**
     * Notificar crédito rechazado
     */
    public function notifyCreditRejected(Credit $credit, User $manager, User $cobrador): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $this->sendCreditNotification('rejected', $credit, $manager, $cobrador);
    }

    /**
     * Notificar crédito entregado
     */
    public function notifyCreditDelivered(Credit $credit, User $manager, User $cobrador): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $this->sendCreditNotification('delivered', $credit, $manager, $cobrador);
    }

    /**
     * Notificar que crédito requiere atención
     */
    public function notifyCreditRequiresAttention(Credit $credit, User $cobrador): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $this->sendCreditNotification('requires_attention', $credit, null, $cobrador);
    }

    /**
     * Notificar pago recibido
     */
    public function notifyPaymentReceived(Payment $payment, User $cobrador, ?User $manager = null, ?User $client = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = [
            'payment' => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'credit_id' => $payment->credit_id,
                'status' => $payment->status,
                'payment_date' => $payment->payment_date?->toISOString(),
            ],
            'cobrador' => [
                'id' => (string) $cobrador->id,
                'name' => $cobrador->name,
                'email' => $cobrador->email,
            ],
        ];

        if ($manager) {
            $payload['manager'] = [
                'id' => (string) $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
            ];
        }

        if ($client) {
            $payload['client'] = [
                'id' => (string) $client->id,
                'name' => $client->name,
            ];
        }

        return $this->sendRequest('/payment-notification', $payload, 'payment');
    }

    /**
     * Enviar notificación genérica a un usuario
     */
    public function notifyUser(string $userId, string $event, array $data): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = [
            'userId' => $userId,
            'event' => $event,
            'data' => $data,
        ];

        return $this->sendRequest('/notify', $payload, 'generic notification');
    }

    /**
     * Enviar notificación a un tipo de usuario (broadcast)
     */
    public function notifyUserType(string $userType, string $event, array $data): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = [
            'userType' => $userType, // cobrador, manager, admin, client
            'event' => $event,
            'data' => $data,
        ];

        return $this->sendRequest('/notify', $payload, "notification to {$userType}s");
    }

    /**
     * Enviar notificación broadcast a todos
     */
    public function notifyAll(string $event, array $data): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = [
            'event' => $event,
            'data' => $data,
        ];

        return $this->sendRequest('/notify', $payload, 'broadcast notification');
    }

    /**
     * Test de conexión al WebSocket
     */
    public function testConnection(): array
    {
        if (! $this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'WebSocket service is disabled',
                'enabled' => false,
            ];
        }

        try {
            $response = Http::timeout($this->timeout)->get("{$this->wsUrl}/health");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'WebSocket server is reachable',
                    'data' => $response->json(),
                    'enabled' => true,
                    'url' => $this->wsUrl,
                ];
            }

            return [
                'success' => false,
                'message' => 'WebSocket server responded with error',
                'status' => $response->status(),
                'enabled' => true,
                'url' => $this->wsUrl,
            ];
        } catch (\Exception $e) {
            Log::error('WebSocket connection test failed', [
                'error' => $e->getMessage(),
                'url' => $this->wsUrl,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to connect to WebSocket server',
                'error' => $e->getMessage(),
                'enabled' => true,
                'url' => $this->wsUrl,
            ];
        }
    }

    /**
     * Obtener usuarios activos conectados al WebSocket
     */
    public function getActiveUsers(): array
    {
        if (! $this->isEnabled()) {
            return ['total' => 0, 'users' => []];
        }

        try {
            $response = Http::timeout($this->timeout)->get("{$this->wsUrl}/active-users");

            if ($response->successful()) {
                return $response->json();
            }

            return ['total' => 0, 'users' => [], 'error' => 'Failed to fetch active users'];
        } catch (\Exception $e) {
            Log::warning('Failed to get active WebSocket users', ['error' => $e->getMessage()]);

            return ['total' => 0, 'users' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Notificar actualización de estadísticas
     */
    public function notifyStatsUpdate(string $type, array $stats, ?int $userId = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = [
            'type' => $type,
            'stats' => $stats,
            'user_id' => $userId,
        ];

        return $this->sendRequest('/stats-update', $payload, "stats update ({$type})");
    }

    /**
     * Enviar notificación de crédito
     */
    protected function sendCreditNotification(string $action, Credit $credit, ?User $manager, User $cobrador): bool
    {
        $payload = [
            'action' => $action,
            'credit' => [
                'id' => $credit->id,
                'amount' => $credit->amount,
                'total_amount' => $credit->total_amount,
                'balance' => $credit->balance,
                'frequency' => $credit->frequency,
                'status' => $credit->status,
                'start_date' => $credit->start_date?->toDateString(),
                'end_date' => $credit->end_date?->toDateString(),
                'entrega_inmediata' => $credit->immediate_delivery_requested ?? false,
                'scheduled_delivery_date' => $credit->scheduled_delivery_date?->toDateString(),
            ],
            'cobrador' => [
                'id' => (string) $cobrador->id,
                'name' => $cobrador->name,
                'email' => $cobrador->email,
            ],
        ];

        if ($manager) {
            $payload['manager'] = [
                'id' => (string) $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
            ];
        }

        // Incluir información del cliente
        if ($credit->client) {
            $payload['credit']['client_name'] = $credit->client->name;
            $payload['credit']['client_id'] = $credit->client->id;
        }

        return $this->sendRequest('/credit-notification', $payload, "credit {$action}");
    }

    /**
     * Enviar request HTTP al WebSocket con retry automático
     */
    protected function sendRequest(string $endpoint, array $payload, string $logContext = 'request'): bool
    {
        if (! $this->wsSecret) {
            Log::error('WebSocket secret not configured', ['endpoint' => $endpoint]);

            return false;
        }

        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders(['x-ws-secret' => $this->wsSecret])
                    ->post("{$this->wsUrl}{$endpoint}", $payload);

                if ($response->successful()) {
                    $data = $response->json();

                    Log::info("WebSocket {$logContext} sent successfully", [
                        'endpoint' => $endpoint,
                        'attempt' => $attempt,
                        'response' => $data,
                    ]);

                    return true;
                }

                Log::warning("WebSocket {$logContext} failed", [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'attempt' => $attempt,
                    'response' => $response->body(),
                ]);

                // Si es error 401 (auth), no reintentar
                if ($response->status() === 401) {
                    Log::error('WebSocket authentication failed - check WS_SECRET', [
                        'endpoint' => $endpoint,
                    ]);

                    return false;
                }
            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning("WebSocket {$logContext} attempt {$attempt} failed", [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                ]);
            }

            // Esperar antes de reintentar (excepto en el último intento)
            if ($attempt < $this->maxRetries) {
                usleep($this->retryDelay * 1000 * $attempt); // Backoff exponencial
            }
        }

        // Todos los intentos fallaron
        Log::error("WebSocket {$logContext} failed after {$this->maxRetries} attempts", [
            'endpoint' => $endpoint,
            'payload' => $payload,
            'last_error' => $lastException?->getMessage(),
        ]);

        return false;
    }

    /**
     * Notificar que una caja fue cerrada automáticamente
     */
    public function notifyCashBalanceAutoClosed(CashBalance $cashBalance, User $cobrador, ?User $manager = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = [
            'action' => 'auto_closed',
            'cash_balance' => [
                'id' => $cashBalance->id,
                'date' => $cashBalance->date->toDateString(),
                'initial_amount' => $cashBalance->initial_amount,
                'collected_amount' => $cashBalance->collected_amount,
                'lent_amount' => $cashBalance->lent_amount,
                'final_amount' => $cashBalance->final_amount,
                'status' => $cashBalance->status,
                'auto_closed_at' => $cashBalance->auto_closed_at?->toISOString(),
                'closure_notes' => $cashBalance->closure_notes,
            ],
            'cobrador' => [
                'id' => (string) $cobrador->id,
                'name' => $cobrador->name,
                'email' => $cobrador->email,
            ],
        ];

        if ($manager) {
            $payload['manager'] = [
                'id' => (string) $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
            ];
        }

        return $this->sendRequest('/cash-balance-notification', $payload, 'cash balance auto-closed');
    }

    /**
     * Notificar que una caja fue auto-creada
     */
    public function notifyCashBalanceAutoCreated(CashBalance $cashBalance, User $cobrador, ?User $manager = null, string $reason = 'payment'): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = [
            'action' => 'auto_created',
            'reason' => $reason,
            'cash_balance' => [
                'id' => $cashBalance->id,
                'date' => $cashBalance->date->toDateString(),
                'status' => $cashBalance->status,
                'requires_reconciliation' => $cashBalance->requires_reconciliation,
            ],
            'cobrador' => [
                'id' => (string) $cobrador->id,
                'name' => $cobrador->name,
                'email' => $cobrador->email,
            ],
        ];

        if ($manager) {
            $payload['manager'] = [
                'id' => (string) $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
            ];
        }

        return $this->sendRequest('/cash-balance-notification', $payload, 'cash balance auto-created');
    }

    /**
     * Notificar que una caja requiere conciliación
     */
    public function notifyCashBalanceRequiresReconciliation(CashBalance $cashBalance, User $cobrador, ?User $manager = null, string $reason = ''): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = [
            'action' => 'requires_reconciliation',
            'reason' => $reason,
            'cash_balance' => [
                'id' => $cashBalance->id,
                'date' => $cashBalance->date->toDateString(),
                'initial_amount' => $cashBalance->initial_amount,
                'collected_amount' => $cashBalance->collected_amount,
                'lent_amount' => $cashBalance->lent_amount,
                'final_amount' => $cashBalance->final_amount,
                'status' => $cashBalance->status,
                'requires_reconciliation' => $cashBalance->requires_reconciliation,
            ],
            'cobrador' => [
                'id' => (string) $cobrador->id,
                'name' => $cobrador->name,
                'email' => $cobrador->email,
            ],
        ];

        if ($manager) {
            $payload['manager'] = [
                'id' => (string) $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
            ];
        }

        return $this->sendRequest('/cash-balance-notification', $payload, 'cash balance requires reconciliation');
    }

    /**
     * Invalidar cache de salud
     */
    public function invalidateHealthCache(): void
    {
        Cache::forget('websocket_health');
    }
}
