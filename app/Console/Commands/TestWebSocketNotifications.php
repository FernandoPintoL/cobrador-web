<?php

namespace App\Console\Commands;

use App\Services\WebSocketNotificationService;
use Illuminate\Console\Command;

class TestWebSocketNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websocket:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test WebSocket notification service connection';

    /**
     * Execute the console command.
     */
    public function handle(WebSocketNotificationService $ws): int
    {
        $this->info('🔍 Testing WebSocket connection...');
        $this->newLine();

        $result = $ws->testConnection();

        if ($result['success']) {
            $this->components->success('WebSocket server is reachable!');
            $this->components->info('URL: '.$result['url']);

            if (isset($result['data'])) {
                $this->newLine();
                $this->components->info('Server Data:');
                $this->table(
                    ['Key', 'Value'],
                    collect($result['data'])->map(fn ($value, $key) => [$key, is_array($value) ? json_encode($value) : $value])
                );
            }

            $this->newLine();
            $this->components->info('Testing health check...');
            $health = $ws->checkHealth();

            if ($health['status'] === 'healthy') {
                $this->components->success('Health check passed!');
            } elseif ($health['status'] === 'disabled') {
                $this->components->warn('WebSocket service is disabled');
            } else {
                $this->components->error('Health check failed: '.$health['message']);
            }

            $this->newLine();
            $this->components->info('Checking active users...');
            $activeUsers = $ws->getActiveUsers();

            if (isset($activeUsers['total']) && $activeUsers['total'] > 0) {
                $this->components->success('Active users: '.$activeUsers['total']);
                if (! empty($activeUsers['users'])) {
                    $this->table(
                        ['User ID', 'Name', 'Type', 'Socket ID'],
                        collect($activeUsers['users'])->map(fn ($user) => [
                            $user['userId'] ?? 'N/A',
                            $user['userName'] ?? 'N/A',
                            $user['userType'] ?? 'N/A',
                            $user['socketId'] ?? 'N/A',
                        ])
                    );
                }
            } else {
                $this->components->warn('No active users connected');
            }

            return Command::SUCCESS;
        }

        $this->components->error('Failed to connect to WebSocket server');
        $this->components->error('Error: '.($result['error'] ?? $result['message']));

        $this->newLine();
        $this->components->warn('Troubleshooting:');
        $this->line('  1. Ensure WebSocket server is running: cd websocket-server && npm start');
        $this->line('  2. Check WEBSOCKET_URL in .env: '.$result['url']);
        $this->line('  3. Verify WS_SECRET matches in both .env files');
        $this->line('  4. Check firewall allows port 3001');

        return Command::FAILURE;
    }
}
