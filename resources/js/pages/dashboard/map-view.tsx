import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, MapPin, DollarSign, AlertCircle, CheckCircle, Clock } from 'lucide-react';

interface Client {
  id: number;
  name: string;
  location: [number, number]; // [lat, lng]
  credits: Credit[];
  payments: Payment[];
}

interface Credit {
  id: number;
  amount: number;
  balance: number;
  status: 'active' | 'completed' | 'overdue';
  start_date: string;
  end_date: string;
}

interface Payment {
  id: number;
  amount: number;
  payment_date: string;
  status: 'paid' | 'pending' | 'overdue';
  payment_method: 'cash' | 'qr' | 'transfer';
}

interface MapViewProps {
  auth: {
    user: {
      id: number;
      name: string;
      email: string;
      roles: string[];
    };
  };
}

const MapView: React.FC<MapViewProps> = ({ auth }) => {
  const [clients, setClients] = useState<Client[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filterStatus, setFilterStatus] = useState<string>('all');
  const [selectedClient, setSelectedClient] = useState<Client | null>(null);
  const [map, setMap] = useState<any>(null);
  const [markers, setMarkers] = useState<any[]>([]);

  useEffect(() => {
    loadClients();
    initializeMap();
  }, []);

  const loadClients = async () => {
    try {
      setLoading(true);
      const response = await fetch('/api/users?role=client&with=credits,payments');
      if (!response.ok) throw new Error('Error al cargar clientes');
      
      const data = await response.json();
      setClients(data.data || []);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Error desconocido');
    } finally {
      setLoading(false);
    }
  };

  const initializeMap = () => {
    // Cargar Leaflet dinámicamente
    const script = document.createElement('script');
    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    script.onload = () => {
      const L = (window as any).L;
      
      // Cargar CSS de Leaflet
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
      document.head.appendChild(link);

      // Inicializar mapa
      const mapInstance = L.map('map').setView([19.4326, -99.1332], 10); // México City por defecto
      
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
      }).addTo(mapInstance);

      setMap(mapInstance);
    };
    document.head.appendChild(script);
  };

  useEffect(() => {
    if (!map || clients.length === 0) return;

    // Limpiar marcadores existentes
    markers.forEach(marker => map.removeLayer(marker));
    const newMarkers: any[] = [];

    clients.forEach(client => {
      if (!client.location || client.location.length !== 2) return;

      const [lat, lng] = client.location;
      
      // Determinar el estado del cliente
      const hasOverduePayments = client.payments.some(p => p.status === 'overdue');
      const hasPendingPayments = client.payments.some(p => p.status === 'pending');
      const allPaid = client.payments.every(p => p.status === 'paid');

      let iconColor = 'green';
      if (hasOverduePayments) iconColor = 'red';
      else if (hasPendingPayments) iconColor = 'orange';

      // Crear marcador personalizado
      const marker = L.marker([lat, lng], {
        icon: L.divIcon({
          className: 'custom-marker',
          html: `<div style="background-color: ${iconColor}; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
          iconSize: [20, 20],
          iconAnchor: [10, 10]
        })
      });

      marker.addTo(map);
      newMarkers.push(marker);

      // Agregar popup con información del cliente
      const popupContent = `
        <div style="min-width: 200px;">
          <h3 style="margin: 0 0 10px 0; color: #333;">${client.name}</h3>
          <p style="margin: 5px 0; font-size: 12px; color: #666;">
            <strong>Créditos activos:</strong> ${client.credits.filter(c => c.status === 'active').length}
          </p>
          <p style="margin: 5px 0; font-size: 12px; color: #666;">
            <strong>Balance total:</strong> $${client.credits.reduce((sum, c) => sum + c.balance, 0).toFixed(2)}
          </p>
          <p style="margin: 5px 0; font-size: 12px; color: #666;">
            <strong>Estado:</strong> 
            ${hasOverduePayments ? '<span style="color: red;">Atrasado</span>' : 
              hasPendingPayments ? '<span style="color: orange;">Pendiente</span>' : 
              '<span style="color: green;">Al día</span>'}
          </p>
          <button onclick="window.selectClient(${client.id})" 
                  style="background: #007bff; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 11px;">
            Ver detalles
          </button>
        </div>
      `;

      marker.bindPopup(popupContent);
    });

    setMarkers(newMarkers);
  }, [map, clients, filterStatus]);

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'paid':
        return <CheckCircle className="h-4 w-4 text-green-500" />;
      case 'pending':
        return <Clock className="h-4 w-4 text-yellow-500" />;
      case 'overdue':
        return <AlertCircle className="h-4 w-4 text-red-500" />;
      default:
        return <DollarSign className="h-4 w-4 text-gray-500" />;
    }
  };

  const getStatusBadge = (status: string) => {
    const variants = {
      paid: 'bg-green-100 text-green-800',
      pending: 'bg-yellow-100 text-yellow-800',
      overdue: 'bg-red-100 text-red-800'
    };
    
    return (
      <Badge className={variants[status as keyof typeof variants] || 'bg-gray-100 text-gray-800'}>
        {status === 'paid' ? 'Pagado' : status === 'pending' ? 'Pendiente' : 'Atrasado'}
      </Badge>
    );
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-8 w-8 animate-spin" />
        <span className="ml-2">Cargando mapa...</span>
      </div>
    );
  }

  if (error) {
    return (
      <Alert variant="destructive">
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>{error}</AlertDescription>
      </Alert>
    );
  }

  return (
    <>
      <Head title="Vista de Mapa - Cobrador" />
      
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Vista de Mapa</h1>
            <p className="text-muted-foreground">
              Visualiza la ubicación de los clientes y su estado de pago
            </p>
          </div>
          
          <div className="flex items-center space-x-2">
            <Select value={filterStatus} onValueChange={setFilterStatus}>
              <SelectTrigger className="w-48">
                <SelectValue placeholder="Filtrar por estado" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos los clientes</SelectItem>
                <SelectItem value="paid">Pagados</SelectItem>
                <SelectItem value="pending">Pendientes</SelectItem>
                <SelectItem value="overdue">Atrasados</SelectItem>
              </SelectContent>
            </Select>
            
            <Button onClick={loadClients} variant="outline" size="sm">
              Actualizar
            </Button>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Mapa */}
          <div className="lg:col-span-2">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center">
                  <MapPin className="h-5 w-5 mr-2" />
                  Mapa de Clientes
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div id="map" className="h-96 w-full rounded-md border"></div>
              </CardContent>
            </Card>
          </div>

          {/* Panel de información */}
          <div className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle>Resumen</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Total de clientes:</span>
                  <Badge variant="secondary">{clients.length}</Badge>
                </div>
                
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Al día:</span>
                  <Badge className="bg-green-100 text-green-800">
                    {clients.filter(c => !c.payments.some(p => p.status === 'overdue' || p.status === 'pending')).length}
                  </Badge>
                </div>
                
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Pendientes:</span>
                  <Badge className="bg-yellow-100 text-yellow-800">
                    {clients.filter(c => c.payments.some(p => p.status === 'pending')).length}
                  </Badge>
                </div>
                
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Atrasados:</span>
                  <Badge className="bg-red-100 text-red-800">
                    {clients.filter(c => c.payments.some(p => p.status === 'overdue')).length}
                  </Badge>
                </div>
              </CardContent>
            </Card>

            {selectedClient && (
              <Card>
                <CardHeader>
                  <CardTitle>Detalles del Cliente</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div>
                    <h4 className="font-medium">{selectedClient.name}</h4>
                    <p className="text-sm text-muted-foreground">
                      {selectedClient.location ? `${selectedClient.location[0]}, ${selectedClient.location[1]}` : 'Sin ubicación'}
                    </p>
                  </div>
                  
                  <div className="space-y-2">
                    <h5 className="text-sm font-medium">Créditos Activos:</h5>
                    {selectedClient.credits.filter(c => c.status === 'active').map(credit => (
                      <div key={credit.id} className="text-sm p-2 bg-gray-50 rounded">
                        <div className="flex justify-between">
                          <span>${credit.amount.toFixed(2)}</span>
                          <span>Balance: ${credit.balance.toFixed(2)}</span>
                        </div>
                      </div>
                    ))}
                  </div>
                  
                  <div className="space-y-2">
                    <h5 className="text-sm font-medium">Pagos Recientes:</h5>
                    {selectedClient.payments.slice(0, 3).map(payment => (
                      <div key={payment.id} className="flex items-center justify-between text-sm">
                        <span>${payment.amount.toFixed(2)}</span>
                        {getStatusBadge(payment.status)}
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        </div>
      </div>

      <style jsx>{`
        .custom-marker {
          background: transparent;
          border: none;
        }
      `}</style>
    </>
  );
};

export default MapView; 