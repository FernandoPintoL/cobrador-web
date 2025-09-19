import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Download, FileText, Users, CreditCard, DollarSign } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reportes',
        href: '/reports',
    },
];

interface ReportType {
    name: string;
    description: string;
    filters: string[];
}

interface ReportTypes {
    payments: ReportType;
    credits: ReportType;
    users: ReportType;
    balances: ReportType;
}

export default function Reports({ reportTypes }: { reportTypes: ReportTypes }) {
    const [selectedReport, setSelectedReport] = useState<string>('');
    const [format, setFormat] = useState<string>('pdf');

    const { data, setData, processing } = useForm({
        start_date: '',
        end_date: '',
        cobrador_id: '',
        client_id: '',
        status: '',
        role: '',
        client_category: '',
    });

    const handleDownload = () => {
        if (!selectedReport) return;

        const params = new URLSearchParams();
        params.append('format', format);

        // Add filters based on selected report
        if (data.start_date) params.append('start_date', data.start_date);
        if (data.end_date) params.append('end_date', data.end_date);
        if (data.cobrador_id) params.append('cobrador_id', data.cobrador_id);
        if (data.client_id) params.append('client_id', data.client_id);
        if (data.status) params.append('status', data.status);
        if (data.role) params.append('role', data.role);
        if (data.client_category) params.append('client_category', data.client_category);

        const url = `/api/reports/${selectedReport}?${params.toString()}`;
        window.open(url, '_blank');
    };

    const getReportIcon = (reportType: string) => {
        switch (reportType) {
            case 'payments':
                return <DollarSign className="h-8 w-8 text-green-600" />;
            case 'credits':
                return <CreditCard className="h-8 w-8 text-blue-600" />;
            case 'users':
                return <Users className="h-8 w-8 text-purple-600" />;
            case 'balances':
                return <FileText className="h-8 w-8 text-orange-600" />;
            default:
                return <FileText className="h-8 w-8 text-gray-600" />;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reportes" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Reportes del Sistema</h1>
                        <p className="text-muted-foreground">
                            Genera reportes detallados de pagos, créditos, usuarios y balances
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {Object.entries(reportTypes).map(([key, report]) => (
                        <Card
                            key={key}
                            className={`cursor-pointer transition-colors ${selectedReport === key ? 'ring-2 ring-primary' : ''
                                }`}
                            onClick={() => setSelectedReport(key)}
                        >
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                {getReportIcon(key)}
                                <CardTitle className="text-sm font-medium">{report.name}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <CardDescription className="text-xs">
                                    {report.description}
                                </CardDescription>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {selectedReport && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Generar Reporte: {reportTypes[selectedReport as keyof ReportTypes]?.name}</CardTitle>
                            <CardDescription>
                                Configura los filtros y formato del reporte
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="format">Formato</Label>
                                    <Select value={format} onValueChange={setFormat}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar formato" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="pdf">PDF</SelectItem>
                                            <SelectItem value="html">HTML (Vista previa)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {/* Date filters for most reports */}
                            {(selectedReport === 'payments' || selectedReport === 'balances') && (
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="start_date">Fecha Inicio</Label>
                                        <Input
                                            id="start_date"
                                            type="date"
                                            value={data.start_date}
                                            onChange={(e) => setData('start_date', e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="end_date">Fecha Fin</Label>
                                        <Input
                                            id="end_date"
                                            type="date"
                                            value={data.end_date}
                                            onChange={(e) => setData('end_date', e.target.value)}
                                        />
                                    </div>
                                </div>
                            )}

                            {/* Status filter for credits */}
                            {selectedReport === 'credits' && (
                                <div className="space-y-2">
                                    <Label htmlFor="status">Estado</Label>
                                    <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar estado" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Todos</SelectItem>
                                            <SelectItem value="active">Activo</SelectItem>
                                            <SelectItem value="completed">Completado</SelectItem>
                                            <SelectItem value="pending_approval">Pendiente Aprobación</SelectItem>
                                            <SelectItem value="waiting_delivery">Esperando Entrega</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {/* Role filter for users */}
                            {selectedReport === 'users' && (
                                <div className="space-y-2">
                                    <Label htmlFor="role">Rol</Label>
                                    <Select value={data.role} onValueChange={(value) => setData('role', value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar rol" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Todos</SelectItem>
                                            <SelectItem value="admin">Admin</SelectItem>
                                            <SelectItem value="manager">Manager</SelectItem>
                                            <SelectItem value="cobrador">Cobrador</SelectItem>
                                            <SelectItem value="client">Cliente</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {/* Client category filter for users */}
                            {selectedReport === 'users' && (
                                <div className="space-y-2">
                                    <Label htmlFor="client_category">Categoría de Cliente</Label>
                                    <Select value={data.client_category} onValueChange={(value) => setData('client_category', value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Seleccionar categoría" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Todas</SelectItem>
                                            <SelectItem value="A">A - Premium</SelectItem>
                                            <SelectItem value="B">B - Normal</SelectItem>
                                            <SelectItem value="C">C - Restringido</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            <div className="flex justify-end">
                                <Button
                                    onClick={handleDownload}
                                    disabled={processing || !selectedReport}
                                    className="flex items-center gap-2"
                                >
                                    <Download className="h-4 w-4" />
                                    {processing ? 'Generando...' : 'Descargar Reporte'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}