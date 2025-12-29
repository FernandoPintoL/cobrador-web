import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import SuperAdminLayout from '@/layouts/super-admin-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Save, Plus, Trash2 } from 'lucide-react';

interface TenantSettingsProps {
    tenantId: number;
}

interface Setting {
    key: string;
    value: any;
    type: string;
}

interface AvailableSetting {
    key: string;
    description: string;
    type: string;
    default: any;
}

export default function TenantSettings({ tenantId }: TenantSettingsProps) {
    const [tenant, setTenant] = useState<any>(null);
    const [settings, setSettings] = useState<Setting[]>([]);
    const [availableSettings, setAvailableSettings] = useState<AvailableSetting[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        fetchTenant();
        fetchSettings();
        fetchAvailableSettings();
    }, [tenantId]);

    const fetchTenant = async () => {
        try {
            const response = await axios.get(`/api/super-admin/tenants/${tenantId}`, {
                headers: {
                    'Accept': 'application/json',
                },
                withCredentials: true,
            });
            if (response.data.success) {
                setTenant(response.data.data);
            }
        } catch (error) {
            console.error('Error fetching tenant:', error);
        }
    };

    const fetchSettings = async () => {
        try {
            const response = await axios.get(`/api/super-admin/tenants/${tenantId}/settings`, {
                headers: {
                    'Accept': 'application/json',
                },
                withCredentials: true,
            });
            if (response.data.success) {
                setSettings(response.data.data.settings || []);
            }
        } catch (error) {
            console.error('Error fetching settings:', error);
        } finally {
            setLoading(false);
        }
    };

    const fetchAvailableSettings = async () => {
        try {
            const response = await axios.get('/api/super-admin/settings/available', {
                headers: {
                    'Accept': 'application/json',
                },
                withCredentials: true,
            });
            if (response.data?.success) {
                setAvailableSettings(response.data.data);
            }
        } catch (error) {
            console.error('Error fetching available settings:', error);
        }
    };

    const handleSettingChange = (key: string, value: any, type: string) => {
        setSettings(prevSettings => {
            const existing = prevSettings.find(s => s.key === key);
            if (existing) {
                return prevSettings.map(s =>
                    s.key === key ? { ...s, value } : s
                );
            } else {
                return [...prevSettings, { key, value, type }];
            }
        });
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            const settingsPayload = settings.map(s => ({
                key: s.key,
                value: s.value,
                type: s.type,
            }));

            // PASO 1: Obtener cookie CSRF de Sanctum
            await axios.get('/sanctum/csrf-cookie');

            // PASO 2: Enviar configuraciones
            const response = await axios.post(
                `/api/super-admin/tenants/${tenantId}/settings/bulk`,
                { settings: settingsPayload },
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
                fetchSettings();
                alert('Configuraciones guardadas exitosamente');
            }
        } catch (error: any) {
            console.error('Error saving settings:', error);
            alert(error.response?.data?.message || 'Error al guardar configuraciones');
        } finally {
            setSaving(false);
        }
    };

    const handleDeleteSetting = async (key: string) => {
        if (!confirm(`¿Eliminar la configuración "${key}"?`)) return;

        try {
            // PASO 1: Obtener cookie CSRF de Sanctum
            await axios.get('/sanctum/csrf-cookie');

            // PASO 2: Eliminar setting
            const response = await axios.delete(
                `/api/super-admin/tenants/${tenantId}/settings/${key}`,
                {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    withCredentials: true,
                }
            );

            if (response.data?.success) {
                fetchSettings();
            }
        } catch (error) {
            console.error('Error deleting setting:', error);
        }
    };

    const renderSettingInput = (availSetting: AvailableSetting) => {
        const existing = settings.find(s => s.key === availSetting.key);
        const value = existing ? existing.value : availSetting.default;

        switch (availSetting.type) {
            case 'boolean':
                // Convertir a booleano explícitamente (puede venir como string "true"/"false", 1/0, etc)
                const boolValue = value === true || value === 'true' || value === 1 || value === '1';
                return (
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={boolValue}
                            onCheckedChange={(checked) =>
                                handleSettingChange(availSetting.key, checked, 'boolean')
                            }
                        />
                        <span className="text-sm">{boolValue ? 'Sí' : 'No'}</span>
                    </div>
                );

            case 'integer':
                return (
                    <Input
                        type="number"
                        step="1"
                        value={value || ''}
                        onChange={(e) =>
                            handleSettingChange(availSetting.key, parseInt(e.target.value) || 0, 'integer')
                        }
                    />
                );

            case 'decimal':
                return (
                    <Input
                        type="number"
                        step="0.01"
                        value={value || ''}
                        onChange={(e) =>
                            handleSettingChange(availSetting.key, parseFloat(e.target.value) || 0, 'decimal')
                        }
                    />
                );

            case 'string':
                return (
                    <Input
                        type="text"
                        value={value || ''}
                        onChange={(e) =>
                            handleSettingChange(availSetting.key, e.target.value, 'string')
                        }
                    />
                );

            default:
                return (
                    <Input
                        type="text"
                        value={value || ''}
                        onChange={(e) =>
                            handleSettingChange(availSetting.key, e.target.value, 'string')
                        }
                    />
                );
        }
    };

    if (loading) {
        return (
            <SuperAdminLayout
                breadcrumbs={[
                    { label: 'Empresas', href: '/super-admin/tenants' },
                    { label: 'Configuraciones' },
                ]}
            >
                <Head title="Configuraciones - Super Admin" />
                <div className="p-8"><div className="text-center">Cargando...</div></div>
            </SuperAdminLayout>
        );
    }

    return (
        <SuperAdminLayout
            breadcrumbs={[
                { label: 'Empresas', href: '/super-admin/tenants' },
                { label: tenant?.name || 'Empresa', href: `/super-admin/tenants/${tenantId}` },
                { label: 'Configuraciones' },
            ]}
        >
            <Head title={`Configuraciones - ${tenant?.name} - Super Admin`} />

            <div className="p-8 space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.visit(`/super-admin/tenants/${tenantId}`)}
                        >
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Volver
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold">Configuraciones</h1>
                            <p className="text-muted-foreground mt-1">{tenant?.name}</p>
                        </div>
                    </div>
                    <Button onClick={handleSave} disabled={saving}>
                        <Save className="h-4 w-4 mr-2" />
                        {saving ? 'Guardando...' : 'Guardar Cambios'}
                    </Button>
                </div>

                {/* Settings Grid */}
                <div className="grid gap-6 md:grid-cols-2">
                    {availableSettings.map((availSetting) => (
                        <Card key={availSetting.key}>
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <CardTitle className="text-base">{availSetting.description}</CardTitle>
                                        <CardDescription className="mt-1">
                                            <code className="text-xs">{availSetting.key}</code>
                                            <Badge variant="outline" className="ml-2 text-xs">
                                                {availSetting.type}
                                            </Badge>
                                        </CardDescription>
                                    </div>
                                    {settings.find(s => s.key === availSetting.key) && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleDeleteSetting(availSetting.key)}
                                        >
                                            <Trash2 className="h-4 w-4 text-red-500" />
                                        </Button>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent>
                                {renderSettingInput(availSetting)}
                                <p className="text-xs text-muted-foreground mt-2">
                                    Por defecto: {String(availSetting.default)}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Current Settings Summary */}
                {settings.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Configuraciones Actuales</CardTitle>
                            <CardDescription>
                                Resumen de todas las configuraciones establecidas
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {settings.map((setting) => (
                                    <div
                                        key={setting.key}
                                        className="flex items-center justify-between py-2 border-b last:border-0"
                                    >
                                        <div className="flex-1">
                                            <p className="font-medium text-sm">{setting.key}</p>
                                            <p className="text-xs text-muted-foreground">
                                                Tipo: {setting.type}
                                            </p>
                                        </div>
                                        <div className="text-sm font-mono">
                                            {String(setting.value)}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </SuperAdminLayout>
    );
}
