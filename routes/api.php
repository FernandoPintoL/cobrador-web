<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashBalanceController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\CreditWaitingListController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InterestRateController;
use App\Http\Controllers\Api\LoanFrequencyController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

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
Route::post('/register', [AuthController::class, 'register'])->name('api.register');
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/check-exists', [AuthController::class, 'checkExists'])->name('api.check-exists');

// Eliminado: configuración de Reverb ya no disponible

// Ruta temporal para testing (sin autenticación)
// Route::get('/users-test', [UserController::class, 'index'])->name('api.users.test');

// Rutas protegidas (requiere autenticación y tenant activo)
Route::middleware(['auth:sanctum', 'tenant_active'])->group(function () {
    // Autenticación
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::get('/tenant/status', [AuthController::class, 'tenantStatus'])->name('api.tenant.status');

    // Usuarios
    Route::apiResource('users', UserController::class)->names([
        'index'   => 'api.users.index',
        'store'   => 'api.users.store',
        'show'    => 'api.users.show',
        'update'  => 'api.users.update',
        'destroy' => 'api.users.destroy',
    ]);
    Route::get('/users/{user}/roles', [UserController::class, 'getRoles'])->name('api.users.roles');
    Route::post('/users/{user}/profile-image', [UserController::class, 'updateProfileImage'])->name('api.users.profile-image');
    Route::patch('/users/{user}/change-password', [UserController::class, 'changePassword'])->name('api.users.change-password');
    // Fotos múltiples de usuario (documentos)
    Route::get('/users/{user}/photos', [UserController::class, 'getPhotos'])->name('api.users.photos.index');
    Route::post('/users/{user}/photos', [UserController::class, 'uploadPhotos'])->name('api.users.photos.store');
    Route::delete('/users/{user}/photos/{photo}', [UserController::class, 'deletePhoto'])->name('api.users.photos.destroy');
    Route::get('/users/by-roles', [UserController::class, 'getUsersByRoles'])->name('api.users.by-roles');
    Route::get('/users/by-multiple-roles', [UserController::class, 'getUsersByMultipleRoles'])->name('api.users.by-multiple-roles');

    // Rutas para asignación de clientes a cobradores
    Route::get('/users/{cobrador}/clients', [UserController::class, 'getClientsByCobrador'])->name('api.users.cobrador.clients');
    Route::post('/users/{cobrador}/assign-clients', [UserController::class, 'assignClientsToCobrador'])->name('api.users.cobrador.assign-clients');
    Route::delete('/users/{cobrador}/clients/{client}', [UserController::class, 'removeClientFromCobrador'])->name('api.users.cobrador.remove-client');
    Route::get('/users/{client}/cobrador', [UserController::class, 'getCobradorByClient'])->name('api.users.client.cobrador');

    // Rutas para asignación de cobradores a managers
    Route::get('/users/{manager}/cobradores', [UserController::class, 'getCobradoresByManager'])->name('api.users.manager.cobradores');
    Route::post('/users/{manager}/assign-cobradores', [UserController::class, 'assignCobradoresToManager'])->name('api.users.manager.assign-cobradores');
    Route::delete('/users/{manager}/cobradores/{cobrador}', [UserController::class, 'removeCobradorFromManager'])->name('api.users.manager.remove-cobrador');
    Route::get('/users/{cobrador}/manager', [UserController::class, 'getManagerByCobrador'])->name('api.users.cobrador.manager');

    // Rutas para asignación directa de clientes a managers
    Route::get('/users/{manager}/clients-direct', [UserController::class, 'getClientsByManager'])->name('api.users.manager.clients-direct');
    Route::get('/users/{manager}/manager-clients', [UserController::class, 'getAllClientsByManager'])->name('api.users.manager.clients'); // Nueva ruta
    Route::post('/users/{manager}/assign-clients-direct', [UserController::class, 'assignClientsToManager'])->name('api.users.manager.assign-clients-direct');
    Route::delete('/users/{manager}/clients-direct/{client}', [UserController::class, 'removeClientFromManager'])->name('api.users.manager.remove-client-direct');
    Route::get('/users/{client}/manager-direct', [UserController::class, 'getManagerByClient'])->name('api.users.client.manager-direct');

    // Rutas para categorías de clientes
    Route::get('/client-categories', [UserController::class, 'getClientCategories'])->name('api.client-categories.index');
    Route::patch('/users/{client}/category', [UserController::class, 'updateClientCategory'])->name('api.users.update-category');
    Route::get('/clients/by-category', [UserController::class, 'getClientsByCategory'])->name('api.clients.by-category');
    Route::get('/client-categories/statistics', [UserController::class, 'getClientCategoryStatistics'])->name('api.client-categories.statistics');
    Route::post('/clients/bulk-update-categories', [UserController::class, 'bulkUpdateClientCategories'])->name('api.clients.bulk-update-categories');

    // Rutas
    Route::apiResource('routes', RouteController::class)->names([
        'index'   => 'api.routes.index',
        'store'   => 'api.routes.store',
        'show'    => 'api.routes.show',
        'update'  => 'api.routes.update',
        'destroy' => 'api.routes.destroy',
    ]);
    Route::get('/routes/cobrador/{cobrador}', [RouteController::class, 'getByCobrador'])->name('api.routes.by-cobrador');
    Route::get('/routes/available-clients', [RouteController::class, 'getAvailableClients'])->name('api.routes.available-clients');

    // Tasas de interés
    Route::apiResource('interest-rates', InterestRateController::class)->names([
        'index'   => 'api.interest-rates.index',
        'store'   => 'api.interest-rates.store',
        'show'    => 'api.interest-rates.show',
        'update'  => 'api.interest-rates.update',
        'destroy' => 'api.interest-rates.destroy',
    ]);
    Route::get('/interest-rates/active', [InterestRateController::class, 'active'])->name('api.interest-rates.active');

    // Frecuencias de pago (configuración para formulario de créditos)
    Route::get('/loan-frequencies', [LoanFrequencyController::class, 'index'])->name('api.loan-frequencies.index');
    Route::get('/loan-frequencies/{code}', [LoanFrequencyController::class, 'show'])->name('api.loan-frequencies.show');

    // Créditos - Rutas específicas PRIMERO (antes del resource)
    Route::get('/credits/form-config', [CreditController::class, 'formConfig'])->name('api.credits.form-config');
    Route::get('/credits/frequencies/available', [CreditController::class, 'getAvailableFrequencies'])->name('api.credits.frequencies');
    Route::get('/credits/{credit}/remaining-installments', [CreditController::class, 'getRemainingInstallments'])->name('api.credits.remaining-installments');
    Route::get('/credits/{credit}/payment-schedule', [CreditController::class, 'getPaymentSchedule'])->name('api.credits.payment-schedule');
    Route::get('/credits/client/{client}', [CreditController::class, 'getByClient'])->name('api.credits.by-client');
    Route::get('/credits/cobrador/{cobrador}', [CreditController::class, 'getByCobrador'])->name('api.credits.by-cobrador');
    Route::get('/credits/cobrador/{cobrador}/stats', [CreditController::class, 'getCobradorStats'])->name('api.credits.cobrador.stats');
    Route::get('/credits/manager/{manager}/stats', [CreditController::class, 'getManagerStats'])->name('api.credits.manager.stats');
    Route::get('/credits-requiring-attention', [CreditController::class, 'getCreditsRequiringAttention'])->name('api.credits.requiring-attention');

    // Créditos - Resource route AL FINAL (para evitar conflictos)
    Route::apiResource('credits', CreditController::class)->names([
        'index'   => 'api.credits.index',
        'store'   => 'api.credits.store',
        'show'    => 'api.credits.show',
        'update'  => 'api.credits.update',
        'destroy' => 'api.credits.destroy',
    ]);

    // Gestión avanzada de pagos de créditos
    Route::get('/credits/{credit}/details', [CreditController::class, 'show'])->name('api.credits.details');

    // Sistema de Lista de Espera para Créditos - UNIFICADO
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

    // Pagos - Rutas principales
    Route::apiResource('payments', PaymentController::class)->names([
        'index'   => 'api.payments.index',
        'store'   => 'api.payments.store',
        'show'    => 'api.payments.show',
        'update'  => 'api.payments.update',
        'destroy' => 'api.payments.destroy',
    ]);
    Route::get('/payments/credit/{credit}', [PaymentController::class, 'getByCredit'])->name('api.payments.by-credit');
    Route::get('/payments/cobrador/{cobrador}', [PaymentController::class, 'getByCobrador'])->name('api.payments.by-cobrador');
    Route::get('/payments/cobrador/{cobrador}/stats', [PaymentController::class, 'getCobradorStats'])->name('api.payments.cobrador.stats');
    Route::get('/payments/recent', [PaymentController::class, 'getRecent'])->name('api.payments.recent');
    Route::get('/payments/today-summary', [PaymentController::class, 'getTodaySummary'])->name('api.payments.today-summary');

    // Balances de efectivo - RUTAS ESPECÍFICAS PRIMERO (antes del resource)
    Route::get('/cash-balances/current-status', [CashBalanceController::class, 'getCurrentStatus'])
        ->name('api.cash-balances.current-status');
    Route::get('/cash-balances/pending-closures', [CashBalanceController::class, 'getPendingClosures'])
        ->name('api.cash-balances.pending-closures');
    Route::get('/cash-balances/cobrador/{cobrador}', [CashBalanceController::class, 'getByCobrador'])
        ->name('api.cash-balances.by-cobrador');
    Route::get('/cash-balances/cobrador/{cobrador}/summary', [CashBalanceController::class, 'getSummary'])
        ->name('api.cash-balances.summary');
    Route::get('/cash-balances/{cashBalance}/detailed', [CashBalanceController::class, 'getDetailedBalance'])
        ->name('api.cash-balances.detailed');
    Route::post('/cash-balances/auto-calculate', [CashBalanceController::class, 'createWithAutoCalculation'])
        ->name('api.cash-balances.auto-calculate');
    Route::post('/cash-balances/open', [CashBalanceController::class, 'open'])
        ->name('api.cash-balances.open');
    Route::post('/cash-balances/{cashBalance}/close', [CashBalanceController::class, 'close'])
        ->name('api.cash-balances.close');

    // RESOURCE ROUTE AL FINAL (para evitar conflictos con las rutas específicas)
    Route::apiResource('cash-balances', CashBalanceController::class)->names([
        'index'   => 'api.cash-balances.index',
        'store'   => 'api.cash-balances.store',
        'show'    => 'api.cash-balances.show',
        'update'  => 'api.cash-balances.update',
        'destroy' => 'api.cash-balances.destroy',
    ]);

    // Notificaciones
    Route::apiResource('notifications', NotificationController::class)->names([
        'index'   => 'api.notifications.index',
        'store'   => 'api.notifications.store',
        'show'    => 'api.notifications.show',
        'update'  => 'api.notifications.update',
        'destroy' => 'api.notifications.destroy',
    ]);
    Route::patch('/notifications/{notification}/mark-read', [NotificationController::class, 'markAsRead'])->name('api.notifications.mark-read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.mark-all-read');
    Route::get('/notifications/user/{user}', [NotificationController::class, 'getByUser'])->name('api.notifications.by-user');
    Route::get('/notifications/user/{user}/unread-count', [NotificationController::class, 'getUnreadCount'])->name('api.notifications.unread-count');

    // Mapa y visualización
    Route::get('/map/clients', [MapController::class, 'getClientsWithLocations'])->name('api.map.clients');
    Route::get('/map/coordinates', [MapController::class, 'getClientCoordinates'])->name('api.map.coordinates');
    Route::get('/map/stats', [MapController::class, 'getMapStats'])->name('api.map.stats');
    Route::get('/map/clients-by-area', [MapController::class, 'getClientsByArea'])->name('api.map.clients-by-area');
    Route::get('/map/cobrador-routes', [MapController::class, 'getCobradorRoutes'])->name('api.map.cobrador-routes');
    Route::get('/map/location-clusters', [MapController::class, 'getLocationClusters'])->name('api.map.location-clusters');
    Route::get('/map/clients-to-visit-today', [MapController::class, 'getClientsToVisitToday'])->name('api.map.clients-to-visit-today');

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats'])->name('api.dashboard.stats');
    Route::get('/dashboard/stats-by-cobrador', [DashboardController::class, 'getStatsByCobrador'])->name('api.dashboard.stats-by-cobrador');
    Route::get('/dashboard/recent-activity', [DashboardController::class, 'getRecentActivity'])->name('api.dashboard.recent-activity');
    Route::get('/dashboard/alerts', [DashboardController::class, 'getAlerts'])->name('api.dashboard.alerts');
    Route::get('/dashboard/performance-metrics', [DashboardController::class, 'getPerformanceMetrics'])->name('api.dashboard.performance-metrics');
    Route::get('/dashboard/manager-stats', [DashboardController::class, 'getManagerStats'])->name('api.dashboard.manager-stats');
    Route::get('/dashboard/financial-summary', [DashboardController::class, 'getFinancialSummary'])->name('api.dashboard.financial-summary');
    Route::get('/dashboard/map-stats', [DashboardController::class, 'getMapStats'])->name('api.dashboard.map-stats');

    // Reportes
    Route::get('/reports/types', [ReportController::class, 'getReportTypes'])->name('api.reports.types');
    Route::get('/reports/payments', [ReportController::class, 'paymentsReport'])->name('api.reports.payments');
    Route::get('/reports/payments/daily-summary', [ReportController::class, 'paymentsDailySummary'])->name('api.reports.payments.daily-summary');
    Route::get('/reports/credits', [ReportController::class, 'creditsReport'])->name('api.reports.credits');
    Route::get('/reports/credits/attention-needed', [ReportController::class, 'creditsAttentionNeeded'])->name('api.reports.credits.attention-needed');
    Route::get('/reports/users', [ReportController::class, 'usersReport'])->name('api.reports.users');
    Route::get('/reports/users/category-stats', [ReportController::class, 'usersCategoryStats'])->name('api.reports.users.category-stats');
    Route::get('/reports/balances', [ReportController::class, 'balancesReport'])->name('api.reports.balances');
    Route::get('/reports/balances/reconciliation', [ReportController::class, 'balancesReconciliation'])->name('api.reports.balances.reconciliation');
    Route::get('/reports/overdue', [ReportController::class, 'overdueReport'])->name('api.reports.overdue');
    Route::get('/reports/performance', [ReportController::class, 'performanceReport'])->name('api.reports.performance');
    Route::get('/reports/cash-flow-forecast', [ReportController::class, 'cashFlowForecastReport'])->name('api.reports.cash-flow-forecast');
    Route::get('/reports/waiting-list', [ReportController::class, 'waitingListReport'])->name('api.reports.waiting-list');
    Route::get('/reports/daily-activity', [ReportController::class, 'dailyActivityReport'])->name('api.reports.daily-activity');
    Route::get('/reports/portfolio', [ReportController::class, 'portfolioReport'])->name('api.reports.portfolio');
    Route::get('/reports/commissions', [ReportController::class, 'commissionsReport'])->name('api.reports.commissions');

    // WebSocket Notifications eliminadas
});

// ============================================================================
// Rutas de Super Admin para Multi-Tenancy
// ============================================================================
Route::middleware(['auth:web', 'super_admin'])->prefix('super-admin')->group(function () {
    // Gestión de Tenants (Empresas)
    Route::apiResource('tenants', App\Http\Controllers\Api\TenantController::class)->names([
        'index'   => 'api.super-admin.tenants.index',
        'store'   => 'api.super-admin.tenants.store',
        'show'    => 'api.super-admin.tenants.show',
        'update'  => 'api.super-admin.tenants.update',
        'destroy' => 'api.super-admin.tenants.destroy',
    ]);
    Route::post('/tenants/{tenant}/activate', [App\Http\Controllers\Api\TenantController::class, 'activate'])
        ->name('api.super-admin.tenants.activate');
    Route::get('/tenants/{tenant}/statistics', [App\Http\Controllers\Api\TenantController::class, 'statistics'])
        ->name('api.super-admin.tenants.statistics');

    // Gestión de Suscripciones/Facturas
    Route::get('/subscriptions', [App\Http\Controllers\Api\TenantSubscriptionController::class, 'index'])
        ->name('api.super-admin.subscriptions.index');
    Route::post('/subscriptions', [App\Http\Controllers\Api\TenantSubscriptionController::class, 'store'])
        ->name('api.super-admin.subscriptions.store');
    Route::get('/subscriptions/{subscription}', [App\Http\Controllers\Api\TenantSubscriptionController::class, 'show'])
        ->name('api.super-admin.subscriptions.show');
    Route::put('/subscriptions/{subscription}', [App\Http\Controllers\Api\TenantSubscriptionController::class, 'update'])
        ->name('api.super-admin.subscriptions.update');
    Route::delete('/subscriptions/{subscription}', [App\Http\Controllers\Api\TenantSubscriptionController::class, 'destroy'])
        ->name('api.super-admin.subscriptions.destroy');
    Route::post('/subscriptions/{subscription}/mark-paid', [App\Http\Controllers\Api\TenantSubscriptionController::class, 'markAsPaid'])
        ->name('api.super-admin.subscriptions.mark-paid');
    Route::post('/subscriptions/{subscription}/cancel', [App\Http\Controllers\Api\TenantSubscriptionController::class, 'cancel'])
        ->name('api.super-admin.subscriptions.cancel');
    Route::get('/subscriptions/statistics/overview', [App\Http\Controllers\Api\TenantSubscriptionController::class, 'statistics'])
        ->name('api.super-admin.subscriptions.statistics');

    // Suscripciones por Tenant
    Route::get('/tenants/{tenant}/subscriptions', [App\Http\Controllers\Api\TenantSubscriptionController::class, 'indexByTenant'])
        ->name('api.super-admin.tenants.subscriptions');

    // Configuraciones de Tenants
    Route::get('/tenants/{tenant}/settings', [App\Http\Controllers\Api\TenantSettingController::class, 'index'])
        ->name('api.super-admin.tenants.settings.index');
    Route::get('/tenants/{tenant}/settings/{key}', [App\Http\Controllers\Api\TenantSettingController::class, 'show'])
        ->name('api.super-admin.tenants.settings.show');
    Route::post('/tenants/{tenant}/settings', [App\Http\Controllers\Api\TenantSettingController::class, 'store'])
        ->name('api.super-admin.tenants.settings.store');
    Route::post('/tenants/{tenant}/settings/bulk', [App\Http\Controllers\Api\TenantSettingController::class, 'bulkUpdate'])
        ->name('api.super-admin.tenants.settings.bulk');
    Route::delete('/tenants/{tenant}/settings/{key}', [App\Http\Controllers\Api\TenantSettingController::class, 'destroy'])
        ->name('api.super-admin.tenants.settings.destroy');
    Route::get('/settings/available', [App\Http\Controllers\Api\TenantSettingController::class, 'availableSettings'])
        ->name('api.super-admin.settings.available');

    // Dashboard de Facturación
    Route::get('/billing/overview', [App\Http\Controllers\Api\BillingDashboardController::class, 'overview'])
        ->name('api.super-admin.billing.overview');
    Route::get('/billing/monthly-revenue', [App\Http\Controllers\Api\BillingDashboardController::class, 'monthlyRevenue'])
        ->name('api.super-admin.billing.monthly-revenue');
    Route::get('/billing/tenant-growth', [App\Http\Controllers\Api\BillingDashboardController::class, 'tenantGrowth'])
        ->name('api.super-admin.billing.tenant-growth');
    Route::get('/billing/overdue-report', [App\Http\Controllers\Api\BillingDashboardController::class, 'overdueReport'])
        ->name('api.super-admin.billing.overdue-report');
    Route::get('/billing/trials-expiring', [App\Http\Controllers\Api\BillingDashboardController::class, 'trialsExpiring'])
        ->name('api.super-admin.billing.trials-expiring');
    Route::get('/billing/top-tenants', [App\Http\Controllers\Api\BillingDashboardController::class, 'topTenants'])
        ->name('api.super-admin.billing.top-tenants');
    Route::get('/billing/payment-compliance', [App\Http\Controllers\Api\BillingDashboardController::class, 'paymentCompliance'])
        ->name('api.super-admin.billing.payment-compliance');
    Route::get('/billing/churn-rate', [App\Http\Controllers\Api\BillingDashboardController::class, 'churnRate'])
        ->name('api.super-admin.billing.churn-rate');
    Route::get('/billing/export', [App\Http\Controllers\Api\BillingDashboardController::class, 'exportData'])
        ->name('api.super-admin.billing.export');
});
