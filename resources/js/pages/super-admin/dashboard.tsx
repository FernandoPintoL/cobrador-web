import React from 'react';
import { Head } from '@inertiajs/react';
import SuperAdminLayout from '@/layouts/super-admin-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Building2,
    CreditCard,
    TrendingUp,
    AlertCircle,
    DollarSign,
    Users,
    Clock,
    CheckCircle2
} from 'lucide-react';

interface DashboardStats {
    total_tenants: number;
    active_tenants: number;
    trial_tenants: number;
    suspended_tenants: number;
    total_invoices: number;
    paid_invoices: number;
    pending_invoices: number;
    overdue_invoices: number;
    total_revenue: number;
    pending_revenue: number;
    overdue_revenue: number;
    current_month_revenue: number;
    previous_month_revenue: number;
    revenue_growth_percentage: number;
    trials_expiring_soon: number;
}

interface BillingDashboardProps {
    stats: DashboardStats;
}

export default function BillingDashboard({ stats }: BillingDashboardProps) {
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('es-BO', {
            style: 'currency',
            currency: 'BOB',
            minimumFractionDigits: 2,
        }).format(amount);
    };

    return (
        <SuperAdminLayout breadcrumbs={[{ label: 'Dashboard' }]}>
            <Head title="Dashboard - Super Admin" />

            <div className="p-8 space-y-8">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold">Dashboard de Facturación</h1>
                    <p className="text-muted-foreground mt-2">
                        Resumen general del negocio y métricas clave
                    </p>
                </div>

                {/* Revenue Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Ingresos Totales
                            </CardTitle>
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatCurrency(stats.total_revenue)}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Todas las facturas pagadas
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Mes Actual
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatCurrency(stats.current_month_revenue)}
                            </div>
                            <div className="flex items-center text-xs mt-1">
                                {stats.revenue_growth_percentage >= 0 ? (
                                    <span className="text-green-600">
                                        +{stats.revenue_growth_percentage}% vs mes anterior
                                    </span>
                                ) : (
                                    <span className="text-red-600">
                                        {stats.revenue_growth_percentage}% vs mes anterior
                                    </span>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Por Cobrar
                            </CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatCurrency(stats.pending_revenue)}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {stats.pending_invoices} facturas pendientes
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Vencidas
                            </CardTitle>
                            <AlertCircle className="h-4 w-4 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">
                                {formatCurrency(stats.overdue_revenue)}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {stats.overdue_invoices} facturas vencidas
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Tenants Stats */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Empresas
                            </CardTitle>
                            <Building2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_tenants}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Todas las empresas registradas
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Activas
                            </CardTitle>
                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {stats.active_tenants}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Empresas con suscripción activa
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                En Prueba
                            </CardTitle>
                            <Users className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {stats.trial_tenants}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {stats.trials_expiring_soon} expiran en 7 días
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Suspendidas
                            </CardTitle>
                            <AlertCircle className="h-4 w-4 text-orange-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">
                                {stats.suspended_tenants}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Por falta de pago
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Invoices Stats */}
                <Card>
                    <CardHeader>
                        <CardTitle>Estado de Facturas</CardTitle>
                        <CardDescription>
                            Resumen de todas las facturas del sistema
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-4">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">Total</p>
                                <p className="text-2xl font-bold">{stats.total_invoices}</p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">Pagadas</p>
                                <p className="text-2xl font-bold text-green-600">{stats.paid_invoices}</p>
                                <Badge variant="outline" className="mt-1 bg-green-50">
                                    {stats.total_invoices > 0
                                        ? Math.round((stats.paid_invoices / stats.total_invoices) * 100)
                                        : 0}%
                                </Badge>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">Pendientes</p>
                                <p className="text-2xl font-bold text-yellow-600">{stats.pending_invoices}</p>
                                <Badge variant="outline" className="mt-1 bg-yellow-50">
                                    {stats.total_invoices > 0
                                        ? Math.round((stats.pending_invoices / stats.total_invoices) * 100)
                                        : 0}%
                                </Badge>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">Vencidas</p>
                                <p className="text-2xl font-bold text-red-600">{stats.overdue_invoices}</p>
                                <Badge variant="outline" className="mt-1 bg-red-50">
                                    {stats.total_invoices > 0
                                        ? Math.round((stats.overdue_invoices / stats.total_invoices) * 100)
                                        : 0}%
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Quick Actions */}
                <Card>
                    <CardHeader>
                        <CardTitle>Acciones Rápidas</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-2 md:grid-cols-3">
                            <a
                                href="/super-admin/tenants"
                                className="flex items-center gap-2 p-4 border rounded-lg hover:bg-accent transition-colors"
                            >
                                <Building2 className="h-5 w-5" />
                                <span className="font-medium">Gestionar Empresas</span>
                            </a>
                            <a
                                href="/super-admin/subscriptions?status=overdue"
                                className="flex items-center gap-2 p-4 border rounded-lg hover:bg-accent transition-colors"
                            >
                                <AlertCircle className="h-5 w-5 text-red-500" />
                                <span className="font-medium">Ver Facturas Vencidas</span>
                            </a>
                            <a
                                href="/super-admin/subscriptions"
                                className="flex items-center gap-2 p-4 border rounded-lg hover:bg-accent transition-colors"
                            >
                                <CreditCard className="h-5 w-5" />
                                <span className="font-medium">Todas las Facturas</span>
                            </a>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SuperAdminLayout>
    );
}
