import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import SuperAdminLayout from '@/layouts/super-admin-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Search, MoreHorizontal, CheckCircle2, XCircle } from 'lucide-react';

interface Subscription {
    id: number;
    tenant_id: number;
    tenant_name: string;
    amount: number;
    amount_formatted: string;
    period_start: string;
    period_start_formatted: string;
    period_end: string;
    period_end_formatted: string;
    status: string;
    status_label: string;
    days_overdue?: number;
}

interface PaginatedResponse {
    data: Subscription[];
    current_page: number;
    last_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface SubscriptionsListProps {
    subscriptions: PaginatedResponse;
    filters: {
        search?: string;
        status?: string;
    };
}

export default function SubscriptionsList({ subscriptions, filters }: SubscriptionsListProps) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');

    // Debounce search
    useEffect(() => {
        const timeout = setTimeout(() => {
            router.get(
                '/super-admin/subscriptions',
                { search, status: statusFilter !== 'all' ? statusFilter : undefined },
                { preserveState: true, preserveScroll: true }
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, statusFilter]);

    const handleMarkAsPaid = async (subscriptionId: number) => {
        if (!confirm('¿Marcar esta factura como pagada?')) return;

        try {
            // PASO 1: Obtener cookie CSRF de Sanctum
            await axios.get('/sanctum/csrf-cookie');

            // PASO 2: Marcar como pagada
            const response = await axios.post(
                `/api/super-admin/subscriptions/${subscriptionId}/mark-paid`,
                {},
                {
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    withCredentials: true,
                }
            );

            if (response.data?.success) {
                alert(response.data.message || 'Factura marcada como pagada exitosamente');
                router.reload({ only: ['subscriptions'] });
            }
        } catch (error: any) {
            console.error('Error marking as paid:', error);
            alert(error.response?.data?.message || 'Error al marcar como pagada');
        }
    };

    const handleCancel = async (subscriptionId: number) => {
        if (!confirm('¿Cancelar esta factura?')) return;

        try {
            // PASO 1: Obtener cookie CSRF de Sanctum
            await axios.get('/sanctum/csrf-cookie');

            // PASO 2: Cancelar factura
            const response = await axios.post(
                `/api/super-admin/subscriptions/${subscriptionId}/cancel`,
                {},
                {
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    withCredentials: true,
                }
            );

            if (response.data?.success) {
                alert(response.data.message || 'Factura cancelada exitosamente');
                router.reload({ only: ['subscriptions'] });
            }
        } catch (error: any) {
            console.error('Error cancelling subscription:', error);
            alert(error.response?.data?.message || 'Error al cancelar factura');
        }
    };

    const getStatusBadge = (status: string, daysOverdue?: number) => {
        const variants: Record<string, string> = {
            paid: 'bg-green-100 text-green-800',
            pending: 'bg-yellow-100 text-yellow-800',
            overdue: 'bg-red-100 text-red-800',
            cancelled: 'bg-gray-100 text-gray-800',
        };

        return (
            <div className="flex items-center gap-2">
                <Badge className={variants[status] || ''}>
                    {status === 'paid'
                        ? 'Pagado'
                        : status === 'pending'
                        ? 'Pendiente'
                        : status === 'overdue'
                        ? 'Vencido'
                        : 'Cancelado'}
                </Badge>
                {daysOverdue && daysOverdue > 0 && (
                    <span className="text-xs text-red-600">{daysOverdue} días</span>
                )}
            </div>
        );
    };

    return (
        <SuperAdminLayout breadcrumbs={[{ label: 'Facturación' }]}>
            <Head title="Facturación - Super Admin" />

            <div className="p-8 space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold">Facturación</h1>
                    <p className="text-muted-foreground mt-2">
                        Gestiona todas las facturas del sistema
                    </p>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex gap-4">
                            <div className="flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Buscar por empresa..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-9"
                                    />
                                </div>
                            </div>
                            <Select value={statusFilter} onValueChange={setStatusFilter}>
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem value="paid">Pagados</SelectItem>
                                    <SelectItem value="pending">Pendientes</SelectItem>
                                    <SelectItem value="overdue">Vencidos</SelectItem>
                                    <SelectItem value="cancelled">Cancelados</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {subscriptions.total || 0} Factura{subscriptions.total !== 1 ? 's' : ''}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {subscriptions.data.length > 0 ? (
                            <>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>ID</TableHead>
                                            <TableHead>Empresa</TableHead>
                                            <TableHead>Período</TableHead>
                                            <TableHead>Monto</TableHead>
                                            <TableHead>Estado</TableHead>
                                            <TableHead className="text-right">Acciones</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {subscriptions.data.map((sub) => (
                                            <TableRow key={sub.id}>
                                                <TableCell className="font-mono text-sm">{sub.id}</TableCell>
                                                <TableCell>
                                                    <button
                                                        onClick={() => router.visit(`/super-admin/tenants/${sub.tenant_id}`)}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {sub.tenant_name}
                                                    </button>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        <div>{sub.period_start_formatted}</div>
                                                        <div className="text-muted-foreground">
                                                            {sub.period_end_formatted}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {sub.amount_formatted}
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(sub.status, sub.days_overdue)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="sm">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuLabel>Acciones</DropdownMenuLabel>
                                                            {sub.status !== 'paid' && sub.status !== 'cancelled' && (
                                                                <DropdownMenuItem
                                                                    onClick={() => handleMarkAsPaid(sub.id)}
                                                                    className="text-green-600"
                                                                >
                                                                    <CheckCircle2 className="h-4 w-4 mr-2" />
                                                                    Marcar como Pagada
                                                                </DropdownMenuItem>
                                                            )}
                                                            {sub.status !== 'paid' && sub.status !== 'cancelled' && (
                                                                <DropdownMenuItem
                                                                    onClick={() => handleCancel(sub.id)}
                                                                    className="text-red-600"
                                                                >
                                                                    <XCircle className="h-4 w-4 mr-2" />
                                                                    Cancelar
                                                                </DropdownMenuItem>
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>

                                {/* Pagination */}
                                {subscriptions.last_page > 1 && (
                                    <div className="flex items-center justify-between mt-4">
                                        <p className="text-sm text-muted-foreground">
                                            Página {subscriptions.current_page} de {subscriptions.last_page}
                                        </p>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.get(
                                                        '/super-admin/subscriptions',
                                                        {
                                                            page: subscriptions.current_page - 1,
                                                            search,
                                                            status: statusFilter !== 'all' ? statusFilter : undefined,
                                                        },
                                                        { preserveState: true, preserveScroll: true }
                                                    )
                                                }
                                                disabled={subscriptions.current_page === 1}
                                            >
                                                Anterior
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.get(
                                                        '/super-admin/subscriptions',
                                                        {
                                                            page: subscriptions.current_page + 1,
                                                            search,
                                                            status: statusFilter !== 'all' ? statusFilter : undefined,
                                                        },
                                                        { preserveState: true, preserveScroll: true }
                                                    )
                                                }
                                                disabled={subscriptions.current_page === subscriptions.last_page}
                                            >
                                                Siguiente
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </>
                        ) : (
                            <div className="text-center py-12">
                                <p className="text-muted-foreground">No se encontraron facturas</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </SuperAdminLayout>
    );
}
