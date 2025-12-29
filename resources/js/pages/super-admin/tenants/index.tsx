import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Plus, Search, MoreHorizontal, Eye, Edit, Ban, CheckCircle2, Settings } from 'lucide-react';

interface Tenant {
    id: number;
    name: string;
    slug: string;
    status: 'active' | 'trial' | 'suspended';
    status_label: string;
    monthly_price: number;
    monthly_price_formatted: string;
    trial_ends_at?: string;
    trial_ends_at_formatted?: string;
    users_count?: number;
    subscriptions_count?: number;
    created_at_formatted: string;
}

interface PaginatedResponse {
    data: Tenant[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface TenantsListProps {
    tenants: PaginatedResponse;
    filters: {
        search?: string;
        status?: string;
    };
}

export default function TenantsList({ tenants, filters }: TenantsListProps) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');

    // Debounce search
    useEffect(() => {
        const timeout = setTimeout(() => {
            router.get(
                '/super-admin/tenants',
                { search, status: statusFilter !== 'all' ? statusFilter : undefined },
                { preserveState: true, preserveScroll: true }
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, statusFilter]);

    const handleSuspend = (tenantId: number) => {
        if (!confirm('¿Estás seguro de que quieres suspender esta empresa?')) return;

        router.delete(`/api/super-admin/tenants/${tenantId}`, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['tenants'] });
            },
        });
    };

    const handleActivate = (tenantId: number) => {
        router.post(
            `/api/super-admin/tenants/${tenantId}/activate`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['tenants'] });
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

        return (
            <Badge className={variants[status as keyof typeof variants] || ''}>
                {status === 'active' ? 'Activo' : status === 'trial' ? 'Prueba' : 'Suspendido'}
            </Badge>
        );
    };

    return (
        <SuperAdminLayout breadcrumbs={[{ label: 'Empresas' }]}>
            <Head title="Empresas - Super Admin" />

            <div className="p-8 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Empresas</h1>
                        <p className="text-muted-foreground mt-2">
                            Gestiona todas las empresas del sistema
                        </p>
                    </div>
                    <Button onClick={() => router.visit('/super-admin/tenants/create')}>
                        <Plus className="h-4 w-4 mr-2" />
                        Nueva Empresa
                    </Button>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex gap-4">
                            <div className="flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Buscar por nombre o slug..."
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
                                    <SelectItem value="active">Activos</SelectItem>
                                    <SelectItem value="trial">En Prueba</SelectItem>
                                    <SelectItem value="suspended">Suspendidos</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {tenants.total || 0} Empresa{tenants.total !== 1 ? 's' : ''}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {tenants.data.length > 0 ? (
                            <>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Empresa</TableHead>
                                            <TableHead>Estado</TableHead>
                                            <TableHead>Precio Mensual</TableHead>
                                            <TableHead>Usuarios</TableHead>
                                            <TableHead>Facturas</TableHead>
                                            <TableHead>Creado</TableHead>
                                            <TableHead className="text-right">Acciones</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {tenants.data.map((tenant) => (
                                            <TableRow key={tenant.id}>
                                                <TableCell>
                                                    <div>
                                                        <p className="font-medium">{tenant.name}</p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {tenant.slug}
                                                        </p>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(tenant.status)}
                                                    {tenant.status === 'trial' && tenant.trial_ends_at_formatted && (
                                                        <p className="text-xs text-muted-foreground mt-1">
                                                            Expira: {tenant.trial_ends_at_formatted}
                                                        </p>
                                                    )}
                                                </TableCell>
                                                <TableCell>{tenant.monthly_price_formatted}</TableCell>
                                                <TableCell>{tenant.users_count || 0}</TableCell>
                                                <TableCell>{tenant.subscriptions_count || 0}</TableCell>
                                                <TableCell>{tenant.created_at_formatted}</TableCell>
                                                <TableCell className="text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="sm">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuLabel>Acciones</DropdownMenuLabel>
                                                            <DropdownMenuItem
                                                                onClick={() =>
                                                                    router.visit(`/super-admin/tenants/${tenant.id}`)
                                                                }
                                                            >
                                                                <Eye className="h-4 w-4 mr-2" />
                                                                Ver Detalles
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() =>
                                                                    router.visit(`/super-admin/tenants/${tenant.id}/edit`)
                                                                }
                                                            >
                                                                <Edit className="h-4 w-4 mr-2" />
                                                                Editar
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() =>
                                                                    router.visit(`/super-admin/tenants/${tenant.id}/settings`)
                                                                }
                                                            >
                                                                <Settings className="h-4 w-4 mr-2" />
                                                                Configuraciones
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            {tenant.status === 'suspended' ? (
                                                                <DropdownMenuItem
                                                                    onClick={() => handleActivate(tenant.id)}
                                                                    className="text-green-600"
                                                                >
                                                                    <CheckCircle2 className="h-4 w-4 mr-2" />
                                                                    Activar
                                                                </DropdownMenuItem>
                                                            ) : (
                                                                <DropdownMenuItem
                                                                    onClick={() => handleSuspend(tenant.id)}
                                                                    className="text-red-600"
                                                                >
                                                                    <Ban className="h-4 w-4 mr-2" />
                                                                    Suspender
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
                                {tenants.last_page > 1 && (
                                    <div className="flex items-center justify-between mt-4">
                                        <p className="text-sm text-muted-foreground">
                                            Página {tenants.current_page} de {tenants.last_page}
                                        </p>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.get(
                                                        '/super-admin/tenants',
                                                        {
                                                            page: tenants.current_page - 1,
                                                            search,
                                                            status: statusFilter !== 'all' ? statusFilter : undefined,
                                                        },
                                                        { preserveState: true, preserveScroll: true }
                                                    )
                                                }
                                                disabled={tenants.current_page === 1}
                                            >
                                                Anterior
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.get(
                                                        '/super-admin/tenants',
                                                        {
                                                            page: tenants.current_page + 1,
                                                            search,
                                                            status: statusFilter !== 'all' ? statusFilter : undefined,
                                                        },
                                                        { preserveState: true, preserveScroll: true }
                                                    )
                                                }
                                                disabled={tenants.current_page === tenants.last_page}
                                            >
                                                Siguiente
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </>
                        ) : (
                            <div className="text-center py-12">
                                <p className="text-muted-foreground">No se encontraron empresas</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </SuperAdminLayout>
    );
}
