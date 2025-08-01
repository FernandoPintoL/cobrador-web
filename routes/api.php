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

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    // Autenticación
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Usuarios
    Route::apiResource('users', UserController::class);
    Route::get('/users/{user}/roles', [UserController::class, 'getRoles']);

    // Rutas
    Route::apiResource('routes', RouteController::class);
    Route::get('/routes/cobrador/{cobrador}', [RouteController::class, 'getByCobrador']);
    Route::get('/routes/available-clients', [RouteController::class, 'getAvailableClients']);

    // Créditos
    Route::apiResource('credits', CreditController::class);
    Route::get('/credits/client/{client}', [CreditController::class, 'getByClient']);
    Route::get('/credits/{credit}/remaining-installments', [CreditController::class, 'getRemainingInstallments']);

    // Pagos
    Route::apiResource('payments', PaymentController::class);
    Route::get('/payments/client/{client}', [PaymentController::class, 'getByClient']);
    Route::get('/payments/cobrador/{cobrador}', [PaymentController::class, 'getByCobrador']);
    Route::get('/payments/credit/{credit}', [PaymentController::class, 'getByCredit']);

    // Balances de efectivo
    Route::apiResource('cash-balances', CashBalanceController::class);
    Route::get('/cash-balances/cobrador/{cobrador}', [CashBalanceController::class, 'getByCobrador']);
    Route::get('/cash-balances/cobrador/{cobrador}/summary', [CashBalanceController::class, 'getSummary']);
    Route::get('/cash-balances/{cashBalance}/detailed', [CashBalanceController::class, 'getDetailedBalance']);
    Route::post('/cash-balances/auto-calculate', [CashBalanceController::class, 'createWithAutoCalculation']);

    // Notificaciones
    Route::apiResource('notifications', NotificationController::class);
    Route::patch('/notifications/{notification}/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/user/{user}', [NotificationController::class, 'getByUser']);
    Route::get('/notifications/user/{user}/unread-count', [NotificationController::class, 'getUnreadCount']);

    // Mapa y visualización
    Route::get('/map/clients', [MapController::class, 'getClientsWithLocations']);
    Route::get('/map/stats', [MapController::class, 'getMapStats']);
    Route::get('/map/clients-by-area', [MapController::class, 'getClientsByArea']);
    Route::get('/map/cobrador-routes', [MapController::class, 'getCobradorRoutes']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/dashboard/stats-by-cobrador', [DashboardController::class, 'getStatsByCobrador']);
    Route::get('/dashboard/recent-activity', [DashboardController::class, 'getRecentActivity']);
    Route::get('/dashboard/alerts', [DashboardController::class, 'getAlerts']);
    Route::get('/dashboard/performance-metrics', [DashboardController::class, 'getPerformanceMetrics']);
}); 