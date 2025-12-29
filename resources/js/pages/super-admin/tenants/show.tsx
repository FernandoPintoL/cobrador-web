import React from 'react';
import { Head, router } from '@inertiajs/react';
import SuperAdminLayout from '@/layouts/super-admin-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ArrowLeft, Edit, Ban, CheckCircle2, Settings as SettingsIcon } from 'lucide-react';

interface Tenant {
    id: number;
    name: string;
    slug: string;
    status: string;
    status_label: string;
    monthly_price: number;
    monthly_price_formatted: string;
    trial_ends_at?: string;
    trial_ends_at_formatted?: string;
    created_at_formatted: string;
    updated_at_formatted: string;
}

interface Stats {
    users_count: number;
    credits_count: number;
    active_credits_count: number;
    total_credit_amount: number;
    subscriptions_count: number;
    paid_subscriptions_count: number;
    pending_subscriptions_count: number;
    overdue_subscriptions_count: number;
    total_revenue: number;
    pending_revenue: number;
    overdue_revenue: number;
}

interface Subscription {
    id: number;
    amount_formatted: string;
    status: string;
    status_label: string;
    period_start_formatted: string;
    period_end_formatted: string;
    created_at_formatted: string;
}

interface TenantDetailsProps {
    tenant: Tenant;
    stats: Stats;
    recentSubscriptions: Subscription[];
}

export default function TenantDetails({ tenant, stats, recentSubscriptions }: TenantDetailsProps) {
    const handleSuspend = () => {
        if (!confirm('¿Estás seguro de que quieres suspender esta empresa?')) return;

        router.delete(`/api/super-admin/tenants/${tenant.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['tenant'] });
            },
        });
    };

    const handleActivate = () => {
        router.post(
            `/api/super-admin/tenants/${tenant.id}/activate`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['tenant'] });
                },
            }
        );
    };

    const getStatusBadge = (status: string) => {
        const variants = {
            active: 'bg-green-100 text-green-800',
            trial: 'bg-blue-100 text-blue-800',
            suspended: 'bg-red-100 text-red-800',
        };
        return <Badge className={variants[status as keyof typeof variants]}>{tenant.status_label}</Badge>;
    };

    return (
        <SuperAdminLayout
            breadcrumbs={[
                { label: 'Empresas', href: '/super-admin/tenants' },
                { label: tenant.name },
            ]}
        >
            <Head title={`${tenant.name} - Super Admin`} />

            <div className="p-8 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" onClick={() => router.visit('/super-admin/tenants')}>
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Volver
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold">{tenant.name}</h1>
                            <p className="text-muted-foreground mt-1">{tenant.slug}</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => router.visit(`/super-admin/tenants/${tenant.id}/settings`)}>
                            <SettingsIcon className="h-4 w-4 mr-2" />
                            Configurar
                        </Button>
                        <Button variant="outline" onClick={() => router.visit(`/super-admin/tenants/${tenant.id}/edit`)}>
                            <Edit className="h-4 w-4 mr-2" />
                            Editar
                        </Button>
                        {tenant.status === 'suspended' ? (
                            <Button onClick={handleActivate} variant="default">
                                <CheckCircle2 className="h-4 w-4 mr-2" />
                                Activar
                            </Button>
                        ) : (
                            <Button onClick={handleSuspend} variant="destructive">
                                <Ban className="h-4 w-4 mr-2" />
                                Suspender
                            </Button>
                        )}
                    </div>
                </div>

                {/* Overview Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Estado</CardTitle>
                        </CardHeader>
                        <CardContent>{getStatusBadge(tenant.status)}</CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Precio Mensual</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">{tenant.monthly_price_formatted}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Usuarios</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">{stats.users_count || 0}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">Ingresos Totales</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">Bs. {stats.total_revenue ? stats.total_revenue.toFixed(2) : '0.00'}</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Stats Grid */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Estadísticas de Usuarios</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Total</span>
                                <span className="font-medium">{stats.users_count}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Estadísticas de Créditos</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Total Créditos</span>
                                <span className="font-medium">{stats.credits_count}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Créditos Activos</span>
                                <span className="font-medium">{stats.active_credits_count}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Monto Total</span>
                                <span className="font-medium">Bs. {stats.total_credit_amount.toFixed(2)}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Estadísticas de Facturación</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Total Facturas</span>
                                <span className="font-medium">{stats.subscriptions_count}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Pagadas</span>
                                <span className="font-medium text-green-600">{stats.paid_subscriptions_count}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Pendientes</span>
                                <span className="font-medium text-yellow-600">{stats.pending_subscriptions_count}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Vencidas</span>
                                <span className="font-medium text-red-600">{stats.overdue_subscriptions_count}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Información</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Creado</span>
                                <span className="font-medium">{tenant.created_at_formatted}</span>
                            </div>
                            {tenant.trial_ends_at_formatted && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Trial Expira</span>
                                    <span className="font-medium">{tenant.trial_ends_at_formatted}</span>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Subscriptions */}
                {recentSubscriptions && recentSubscriptions.length > 0 && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle>Facturas Recientes</CardTitle>
                                <Button variant="outline" size="sm" onClick={() => router.visit(`/super-admin/subscriptions?tenant_id=${tenant.id}`)}>
                                    Ver Todas
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Período</TableHead>
                                        <TableHead>Monto</TableHead>
                                        <TableHead>Estado</TableHead>
                                        <TableHead>Fecha</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentSubscriptions.map((sub) => (
                                        <TableRow key={sub.id}>
                                            <TableCell>
                                                {sub.period_start_formatted} - {sub.period_end_formatted}
                                            </TableCell>
                                            <TableCell>{sub.amount_formatted}</TableCell>
                                            <TableCell>
                                                <Badge
                                                    className={
                                                        sub.status === 'paid'
                                                            ? 'bg-green-100 text-green-800'
                                                            : sub.status === 'pending'
                                                            ? 'bg-yellow-100 text-yellow-800'
                                                            : 'bg-red-100 text-red-800'
                                                    }
                                                >
                                                    {sub.status_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {sub.created_at_formatted}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>
        </SuperAdminLayout>
    );
}
