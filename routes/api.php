<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\CashBalanceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\WebSocketNotificationController;
use App\Http\Controllers\Api\CreditWaitingListController;
use App\Http\Controllers\CreditPaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas públicas de autenticación
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/check-exists', [AuthController::class, 'checkExists']);

// Ruta temporal para testing (sin autenticación)
Route::get('/users-test', [UserController::class, 'index'])->name('api.users.test');

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    // Autenticación
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Usuarios
    Route::apiResource('users', UserController::class)->names([
        'index' => 'api.users.index',
        'store' => 'api.users.store',
        'show' => 'api.users.show',
        'update' => 'api.users.update',
        'destroy' => 'api.users.destroy',
    ]);
    Route::get('/users/{user}/roles', [UserController::class, 'getRoles'])->name('api.users.roles');
    Route::post('/users/{user}/profile-image', [UserController::class, 'updateProfileImage'])->name('api.users.profile-image');
    Route::get('/users/by-roles', [UserController::class, 'getUsersByRoles'])->name('api.users.by-roles');
    Route::get('/users/by-multiple-roles', [UserController::class, 'getUsersByMultipleRoles'])->name('api.users.by-multiple-roles');
    
    // Rutas para asignación de clientes a cobradores
    Route::get('/users/{cobrador}/clients', [UserController::class, 'getClientsByCobrador'])->name('api.users.cobrador.clients');
    Route::post('/users/{cobrador}/assign-clients', [UserController::class, 'assignClientsToCobrador'])->name('api.users.cobrador.assign-clients');
    Route::delete('/users/{cobrador}/clients/{client}', [UserController::class, 'removeClientFromCobrador'])->name('api.users.cobrador.remove-client');
    Route::get('/users/{client}/cobrador', [UserController::class, 'getCobradorByClient'])->name('api.users.client.cobrador');

    // Rutas
    Route::apiResource('routes', RouteController::class)->names([
        'index' => 'api.routes.index',
        'store' => 'api.routes.store',
        'show' => 'api.routes.show',
        'update' => 'api.routes.update',
        'destroy' => 'api.routes.destroy',
    ]);
    Route::get('/routes/cobrador/{cobrador}', [RouteController::class, 'getByCobrador'])->name('api.routes.by-cobrador');
    Route::get('/routes/available-clients', [RouteController::class, 'getAvailableClients'])->name('api.routes.available-clients');

    // Créditos
    Route::apiResource('credits', CreditController::class)->names([
        'index' => 'api.credits.index',
        'store' => 'api.credits.store',
        'show' => 'api.credits.show',
        'update' => 'api.credits.update',
        'destroy' => 'api.credits.destroy',
    ]);
    Route::get('/credits/client/{client}', [CreditController::class, 'getByClient'])->name('api.credits.by-client');
    Route::get('/credits/{credit}/remaining-installments', [CreditController::class, 'getRemainingInstallments'])->name('api.credits.remaining-installments');
    Route::get('/credits/cobrador/{cobrador}', [CreditController::class, 'getByCobrador'])->name('api.credits.by-cobrador');
    Route::get('/credits/cobrador/{cobrador}/stats', [CreditController::class, 'getCobradorStats'])->name('api.credits.cobrador-stats');
    Route::get('/credits-requiring-attention', [CreditController::class, 'getCreditsRequiringAttention'])->name('api.credits.requiring-attention');

    // Pagos
    Route::apiResource('payments', PaymentController::class)->names([
        'index' => 'api.payments.index',
        'store' => 'api.payments.store',
        'show' => 'api.payments.show',
        'update' => 'api.payments.update',
        'destroy' => 'api.payments.destroy',
    ]);
    Route::get('/payments/client/{client}', [PaymentController::class, 'getByClient'])->name('api.payments.by-client');
    Route::get('/payments/cobrador/{cobrador}', [PaymentController::class, 'getByCobrador'])->name('api.payments.by-cobrador');
    Route::get('/payments/credit/{credit}', [PaymentController::class, 'getByCredit'])->name('api.payments.by-credit');

    // Balances de efectivo
    Route::apiResource('cash-balances', CashBalanceController::class)->names([
        'index' => 'api.cash-balances.index',
        'store' => 'api.cash-balances.store',
        'show' => 'api.cash-balances.show',
        'update' => 'api.cash-balances.update',
        'destroy' => 'api.cash-balances.destroy',
    ]);
    Route::get('/cash-balances/cobrador/{cobrador}', [CashBalanceController::class, 'getByCobrador'])->name('api.cash-balances.by-cobrador');
    Route::get('/cash-balances/cobrador/{cobrador}/summary', [CashBalanceController::class, 'getSummary'])->name('api.cash-balances.summary');
    Route::get('/cash-balances/{cashBalance}/detailed', [CashBalanceController::class, 'getDetailedBalance'])->name('api.cash-balances.detailed');
    Route::post('/cash-balances/auto-calculate', [CashBalanceController::class, 'createWithAutoCalculation'])->name('api.cash-balances.auto-calculate');

    // Notificaciones
    Route::apiResource('notifications', NotificationController::class)->names([
        'index' => 'api.notifications.index',
        'store' => 'api.notifications.store',
        'show' => 'api.notifications.show',
        'update' => 'api.notifications.update',
        'destroy' => 'api.notifications.destroy',
    ]);
    Route::patch('/notifications/{notification}/mark-read', [NotificationController::class, 'markAsRead'])->name('api.notifications.mark-read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.mark-all-read');
    Route::get('/notifications/user/{user}', [NotificationController::class, 'getByUser'])->name('api.notifications.by-user');
    Route::get('/notifications/user/{user}/unread-count', [NotificationController::class, 'getUnreadCount'])->name('api.notifications.unread-count');

    // Mapa y visualización
    Route::get('/map/clients', [MapController::class, 'getClientsWithLocations'])->name('api.map.clients');
    Route::get('/map/stats', [MapController::class, 'getMapStats'])->name('api.map.stats');
    Route::get('/map/clients-by-area', [MapController::class, 'getClientsByArea'])->name('api.map.clients-by-area');
    Route::get('/map/cobrador-routes', [MapController::class, 'getCobradorRoutes'])->name('api.map.cobrador-routes');

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats'])->name('api.dashboard.stats');
    Route::get('/dashboard/stats-by-cobrador', [DashboardController::class, 'getStatsByCobrador'])->name('api.dashboard.stats-by-cobrador');
    Route::get('/dashboard/recent-activity', [DashboardController::class, 'getRecentActivity'])->name('api.dashboard.recent-activity');
    Route::get('/dashboard/alerts', [DashboardController::class, 'getAlerts'])->name('api.dashboard.alerts');
    Route::get('/dashboard/performance-metrics', [DashboardController::class, 'getPerformanceMetrics'])->name('api.dashboard.performance-metrics');

    // Gestión avanzada de pagos de créditos
    Route::prefix('credits/{credit}')->group(function () {
        Route::post('/payments', [CreditPaymentController::class, 'processPayment'])->name('api.credits.process-payment');
        Route::get('/details', [CreditPaymentController::class, 'getCreditDetails'])->name('api.credits.details');
        Route::post('/simulate-payment', [CreditPaymentController::class, 'simulatePayment'])->name('api.credits.simulate-payment');
        Route::get('/payment-schedule', [CreditPaymentController::class, 'getPaymentSchedule'])->name('api.credits.payment-schedule');
    });
    
    // Créditos atrasados
    Route::get('/credits/overdue', [CreditPaymentController::class, 'getOverdueCredits'])->name('api.credits.overdue');
    
    // Sistema de Lista de Espera para Créditos
    Route::prefix('credits/waiting-list')->group(function () {
        Route::get('/pending-approval', [CreditWaitingListController::class, 'pendingApproval'])->name('api.credits.waiting-list.pending-approval');
        Route::get('/waiting-delivery', [CreditWaitingListController::class, 'waitingForDelivery'])->name('api.credits.waiting-list.waiting-delivery');
        Route::get('/ready-today', [CreditWaitingListController::class, 'readyForDeliveryToday'])->name('api.credits.waiting-list.ready-today');
        Route::get('/overdue-delivery', [CreditWaitingListController::class, 'overdueForDelivery'])->name('api.credits.waiting-list.overdue-delivery');
        Route::get('/summary', [CreditWaitingListController::class, 'getSummary'])->name('api.credits.waiting-list.summary');
    });
    
    Route::prefix('credits/{credit}/waiting-list')->group(function () {
        Route::post('/approve', [CreditWaitingListController::class, 'approve'])->name('api.credits.waiting-list.approve');
        Route::post('/reject', [CreditWaitingListController::class, 'reject'])->name('api.credits.waiting-list.reject');
        Route::post('/deliver', [CreditWaitingListController::class, 'deliver'])->name('api.credits.waiting-list.deliver');
        Route::post('/reschedule', [CreditWaitingListController::class, 'reschedule'])->name('api.credits.waiting-list.reschedule');
        Route::get('/status', [CreditWaitingListController::class, 'getDeliveryStatus'])->name('api.credits.waiting-list.status');
    });
    
    // WebSocket Notifications
    Route::prefix('websocket')->group(function () {
        Route::post('/credit-attention/{credit}', [WebSocketNotificationController::class, 'sendCreditAttentionNotification'])->name('api.websocket.credit-attention');
        Route::post('/payment-notification', [WebSocketNotificationController::class, 'sendPaymentNotification'])->name('api.websocket.payment-notification');
        Route::get('/notifications', [WebSocketNotificationController::class, 'getRealtimeNotifications'])->name('api.websocket.notifications');
        Route::post('/test', [WebSocketNotificationController::class, 'testWebSocket'])->name('api.websocket.test');
        Route::get('/test-connection', [WebSocketNotificationController::class, 'testDirectWebSocket'])->name('api.websocket.test-connection');
    });
}); 