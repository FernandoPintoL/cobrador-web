import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import SuperAdminLayout from '@/layouts/super-admin-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ArrowLeft, Save } from 'lucide-react';

interface Tenant {
    id: number;
    name: string;
    slug: string;
    status: string;
    monthly_price: number;
    trial_ends_at?: string;
}

interface TenantFormProps {
    tenant?: Tenant | null;
    isEdit: boolean;
    defaultTrialEnd?: string;
}

export default function TenantForm({ tenant, isEdit, defaultTrialEnd }: TenantFormProps) {
    const [processing, setProcessing] = useState(false);
    const [data, setDataState] = useState({
        name: tenant?.name || '',
        slug: tenant?.slug || '',
        status: tenant?.status || 'trial',
        monthly_price: tenant?.monthly_price?.toString() || '200.00',
        trial_ends_at: tenant?.trial_ends_at || defaultTrialEnd || '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});

    const setData = (key: string, value: string) => {
        setDataState(prev => ({ ...prev, [key]: value }));
    };

    const generateSlug = (name: string) => {
        return name
            .toLowerCase()
            .trim()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
    };

    const handleNameChange = (value: string) => {
        setDataState(prev => ({
            ...prev,
            name: value,
            slug: isEdit ? prev.slug : generateSlug(value),
        }));
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            // PASO 1: Obtener el cookie CSRF de Sanctum (requerido para SPAs)
            await axios.get('/sanctum/csrf-cookie');

            // Convertir monthly_price a número antes de enviar
            const submitData = {
                ...data,
                monthly_price: parseFloat(data.monthly_price),
            };

            const url = isEdit
                ? `/api/super-admin/tenants/${tenant?.id}`
                : '/api/super-admin/tenants';

            const method = isEdit ? 'put' : 'post';

            // PASO 2: Hacer la petición API (ahora con las cookies CSRF correctas)
            const response = await axios[method](url, submitData, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                withCredentials: true, // CRÍTICO: Incluir cookies de sesión
            });

            // El BaseController retorna { success: true, data: {...}, message: "..." }
            if (response.data?.success) {
                router.visit('/super-admin/tenants');
            } else {
                if (response.data?.data) {
                    setErrors(response.data.data);
                }
            }
        } catch (error: any) {
            console.error('Error saving tenant:', error);

            if (error.response?.data?.data) {
                setErrors(error.response.data.data);
            } else if (error.response?.data?.message) {
                setErrors({ general: error.response.data.message });
            } else {
                setErrors({ general: 'Error al guardar la empresa' });
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <SuperAdminLayout
            breadcrumbs={[
                { label: 'Empresas', href: '/super-admin/tenants' },
                { label: isEdit ? 'Editar' : 'Nueva Empresa' },
            ]}
        >
            <Head title={`${isEdit ? 'Editar' : 'Nueva'} Empresa - Super Admin`} />

            <div className="p-8 space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.visit('/super-admin/tenants')}
                    >
                        <ArrowLeft className="h-4 w-4 mr-2" />
                        Volver
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold">
                            {isEdit ? 'Editar Empresa' : 'Nueva Empresa'}
                        </h1>
                        <p className="text-muted-foreground mt-2">
                            {isEdit
                                ? 'Actualiza la información de la empresa'
                                : 'Crea una nueva empresa en el sistema'}
                        </p>
                    </div>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Información Básica</CardTitle>
                            <CardDescription>
                                Datos principales de la empresa
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Name */}
                            <div className="space-y-2">
                                <Label htmlFor="name">Nombre de la Empresa *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => handleNameChange(e.target.value)}
                                    placeholder="Ej: Empresa ABC"
                                    required
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">{errors.name}</p>
                                )}
                            </div>

                            {/* Slug */}
                            <div className="space-y-2">
                                <Label htmlFor="slug">Slug (identificador único) *</Label>
                                <Input
                                    id="slug"
                                    value={data.slug}
                                    onChange={(e) => setData('slug', e.target.value)}
                                    placeholder="empresa-abc"
                                    required
                                />
                                <p className="text-xs text-muted-foreground">
                                    Solo letras minúsculas, números y guiones. Se genera automáticamente del nombre.
                                </p>
                                {errors.slug && (
                                    <p className="text-sm text-red-500">{errors.slug}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Suscripción</CardTitle>
                            <CardDescription>
                                Configuración de suscripción y pagos
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Status */}
                            <div className="space-y-2">
                                <Label htmlFor="status">Estado *</Label>
                                <Select
                                    value={data.status}
                                    onValueChange={(value) => setData('status', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona un estado" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="trial">Período de Prueba</SelectItem>
                                        <SelectItem value="active">Activo</SelectItem>
                                        <SelectItem value="suspended">Suspendido</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.status && (
                                    <p className="text-sm text-red-500">{errors.status}</p>
                                )}
                            </div>

                            {/* Monthly Price */}
                            <div className="space-y-2">
                                <Label htmlFor="monthly_price">Precio Mensual (Bs) *</Label>
                                <Input
                                    id="monthly_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.monthly_price}
                                    onChange={(e) => setData('monthly_price', e.target.value)}
                                    placeholder="200.00"
                                    required
                                />
                                {errors.monthly_price && (
                                    <p className="text-sm text-red-500">{errors.monthly_price}</p>
                                )}
                            </div>

                            {/* Trial End Date */}
                            {data.status === 'trial' && (
                                <div className="space-y-2">
                                    <Label htmlFor="trial_ends_at">Fecha de Fin de Prueba</Label>
                                    <Input
                                        id="trial_ends_at"
                                        type="date"
                                        value={data.trial_ends_at}
                                        onChange={(e) => setData('trial_ends_at', e.target.value)}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Si está vacío, se establecerá en 30 días desde hoy
                                    </p>
                                    {errors.trial_ends_at && (
                                        <p className="text-sm text-red-500">{errors.trial_ends_at}</p>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex justify-end gap-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.visit('/super-admin/tenants')}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save className="h-4 w-4 mr-2" />
                            {processing ? 'Guardando...' : 'Guardar Empresa'}
                        </Button>
                    </div>
                </form>
            </div>
        </SuperAdminLayout>
    );
}
