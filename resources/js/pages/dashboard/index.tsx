import React, { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, MapPin, DollarSign, Users, TrendingUp, AlertCircle, CheckCircle, Clock } from 'lucide-react';
import MapView from './map-view';
import CashReconciliation from './cash-reconciliation';

interface DashboardStats {
  total_clients: number;
  total_cobradores: number;
  total_credits: number;
  total_payments: number;
  overdue_payments: number;
  pending_payments: number;
  total_balance: number;
  today_collections: number;
}

interface DashboardProps {
  auth: {
    user: {
      id: number;
      name: string;
      email: string;
      roles: string[];
    };
  };
}

const Dashboard: React.FC<DashboardProps> = ({ auth }) => {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState('overview');

  useEffect(() => {
    loadDashboardStats();
  }, []);

  const loadDashboardStats = async () => {
    try {
      setLoading(true);
      const response = await fetch('/api/dashboard/stats');
      if (!response.ok) throw new Error('Error al cargar estadísticas');
      
      const data = await response.json();
      setStats(data.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Error desconocido');
    } finally {
      setLoading(false);
    }
  };

  const isManager = auth.user.roles.includes('admin') || auth.user.roles.includes('manager');

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-8 w-8 animate-spin" />
        <span className="ml-2">Cargando dashboard...</span>
      </div>
    );
  }

  return (
    <>
      <Head title="Dashboard - Cobrador" />
      
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
            <p className="text-muted-foreground">
              Bienvenido, {auth.user.name}
            </p>
          </div>
          
          <div className="flex items-center space-x-2">
            <Button onClick={loadDashboardStats} variant="outline" size="sm">
              Actualizar
            </Button>
          </div>
        </div>

        {error && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {/* Estadísticas rápidas */}
        {stats && (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total Clientes</CardTitle>
                <Users className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats.total_clients}</div>
                <p className="text-xs text-muted-foreground">
                  Registrados en el sistema
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Balance Total</CardTitle>
                <DollarSign className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">${stats.total_balance.toFixed(2)}</div>
                <p className="text-xs text-muted-foreground">
                  En créditos activos
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Pagos Pendientes</CardTitle>
                <Clock className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-yellow-600">{stats.pending_payments}</div>
                <p className="text-xs text-muted-foreground">
                  Requieren atención
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Pagos Atrasados</CardTitle>
                <AlertCircle className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-red-600">{stats.overdue_payments}</div>
                <p className="text-xs text-muted-foreground">
                  Requieren cobro urgente
                </p>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Tabs principales */}
        <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="overview">Resumen</TabsTrigger>
            {isManager && (
              <>
                <TabsTrigger value="map">Vista de Mapa</TabsTrigger>
                <TabsTrigger value="reconciliation">Arqueo de Caja</TabsTrigger>
              </>
            )}
          </TabsList>

          <TabsContent value="overview" className="space-y-4">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Actividad reciente */}
              <Card>
                <CardHeader>
                  <CardTitle>Actividad Reciente</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-2">
                        <CheckCircle className="h-4 w-4 text-green-500" />
                        <span className="text-sm">Pagos procesados hoy</span>
                      </div>
                      <Badge variant="secondary">{stats?.today_collections || 0}</Badge>
                    </div>
                    
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-2">
                        <DollarSign className="h-4 w-4 text-blue-500" />
                        <span className="text-sm">Créditos activos</span>
                      </div>
                      <Badge variant="secondary">{stats?.total_credits || 0}</Badge>
                    </div>
                    
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-2">
                        <Users className="h-4 w-4 text-purple-500" />
                        <span className="text-sm">Cobradores activos</span>
                      </div>
                      <Badge variant="secondary">{stats?.total_cobradores || 0}</Badge>
                    </div>
                  </div>
                </CardContent>
              </Card>

              {/* Acciones rápidas */}
              <Card>
                <CardHeader>
                  <CardTitle>Acciones Rápidas</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-3">
                    <Link href="/users">
                      <Button variant="outline" className="w-full justify-start">
                        <Users className="h-4 w-4 mr-2" />
                        Gestionar Clientes
                      </Button>
                    </Link>
                    
                    <Link href="/routes">
                      <Button variant="outline" className="w-full justify-start">
                        <MapPin className="h-4 w-4 mr-2" />
                        Gestionar Rutas
                      </Button>
                    </Link>
                    
                    {isManager && (
                      <Link href="/dashboard?tab=map">
                        <Button variant="outline" className="w-full justify-start">
                          <MapPin className="h-4 w-4 mr-2" />
                          Ver Mapa de Clientes
                        </Button>
                      </Link>
                    )}
                    
                    {isManager && (
                      <Link href="/dashboard?tab=reconciliation">
                        <Button variant="outline" className="w-full justify-start">
                          <DollarSign className="h-4 w-4 mr-2" />
                          Arqueo de Caja
                        </Button>
                      </Link>
                    )}
                  </div>
                </CardContent>
              </Card>
            </div>

            {/* Alertas importantes */}
            {(stats?.overdue_payments || 0) > 0 && (
              <Alert>
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>
                  Tienes {stats.overdue_payments} pagos atrasados que requieren atención inmediata.
                </AlertDescription>
              </Alert>
            )}
          </TabsContent>

          {isManager && (
            <TabsContent value="map">
              <MapView auth={auth} />
            </TabsContent>
          )}

          {isManager && (
            <TabsContent value="reconciliation">
              <CashReconciliation auth={auth} />
            </TabsContent>
          )}
        </Tabs>
      </div>
    </>
  );
};

export default Dashboard; 