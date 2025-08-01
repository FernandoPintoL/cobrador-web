import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Plus, Search, Edit, Trash2, Users } from 'lucide-react';

interface Route {
  id: number;
  name: string;
  description?: string;
  cobrador: {
    id: number;
    name: string;
    email: string;
  };
  clients: Array<{
    id: number;
    name: string;
    email: string;
  }>;
  created_at: string;
}

interface User {
  id: number;
  name: string;
  email: string;
}

export default function RoutesIndex() {
  const [routes, setRoutes] = useState<Route[]>([]);
  const [cobradores, setCobradores] = useState<User[]>([]);
  const [clients, setClients] = useState<User[]>([]);
  const [search, setSearch] = useState('');
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [selectedRoute, setSelectedRoute] = useState<Route | null>(null);
  const [formData, setFormData] = useState({
    cobrador_id: '',
    name: '',
    description: '',
    client_ids: [] as number[],
  });

  useEffect(() => {
    fetchRoutes();
    fetchCobradores();
    fetchClients();
  }, []);

  const fetchRoutes = async () => {
    try {
      const response = await fetch('/api/routes');
      const data = await response.json();
      if (data.success) {
        setRoutes(data.data.data);
      }
    } catch (error) {
      console.error('Error fetching routes:', error);
    }
  };

  const fetchCobradores = async () => {
    try {
      const response = await fetch('/api/users?role=cobrador');
      const data = await response.json();
      if (data.success) {
        setCobradores(data.data.data);
      }
    } catch (error) {
      console.error('Error fetching cobradores:', error);
    }
  };

  const fetchClients = async () => {
    try {
      const response = await fetch('/api/routes/available-clients');
      const data = await response.json();
      if (data.success) {
        setClients(data.data);
      }
    } catch (error) {
      console.error('Error fetching clients:', error);
    }
  };

  const handleCreateRoute = async () => {
    try {
      const response = await fetch('/api/routes', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
        body: JSON.stringify(formData),
      });
      const data = await response.json();
      if (data.success) {
        setIsCreateDialogOpen(false);
        setFormData({
          cobrador_id: '',
          name: '',
          description: '',
          client_ids: [],
        });
        fetchRoutes();
      }
    } catch (error) {
      console.error('Error creating route:', error);
    }
  };

  const handleUpdateRoute = async () => {
    if (!selectedRoute) return;
    
    try {
      const response = await fetch(`/api/routes/${selectedRoute.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
        body: JSON.stringify(formData),
      });
      const data = await response.json();
      if (data.success) {
        setIsEditDialogOpen(false);
        setSelectedRoute(null);
        setFormData({
          cobrador_id: '',
          name: '',
          description: '',
          client_ids: [],
        });
        fetchRoutes();
      }
    } catch (error) {
      console.error('Error updating route:', error);
    }
  };

  const handleDeleteRoute = async (routeId: number) => {
    if (!confirm('¿Estás seguro de que quieres eliminar esta ruta?')) return;
    
    try {
      const response = await fetch(`/api/routes/${routeId}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
      });
      const data = await response.json();
      if (data.success) {
        fetchRoutes();
      }
    } catch (error) {
      console.error('Error deleting route:', error);
    }
  };

  const openEditDialog = (route: Route) => {
    setSelectedRoute(route);
    setFormData({
      cobrador_id: route.cobrador.id.toString(),
      name: route.name,
      description: route.description || '',
      client_ids: route.clients.map(client => client.id),
    });
    setIsEditDialogOpen(true);
  };

  const filteredRoutes = routes.filter(route =>
    route.name.toLowerCase().includes(search.toLowerCase()) ||
    route.cobrador.name.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <>
      <Head title="Rutas" />
      
      <div className="container mx-auto py-6">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-3xl font-bold">Gestión de Rutas</h1>
          <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
            <DialogTrigger asChild>
              <Button>
                <Plus className="w-4 h-4 mr-2" />
                Nueva Ruta
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Crear Nueva Ruta</DialogTitle>
              </DialogHeader>
              <div className="space-y-4">
                <div>
                  <Label htmlFor="cobrador">Cobrador</Label>
                  <Select
                    value={formData.cobrador_id}
                    onValueChange={(value) => setFormData({ ...formData, cobrador_id: value })}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Seleccionar cobrador" />
                    </SelectTrigger>
                    <SelectContent>
                      {cobradores.map((cobrador) => (
                        <SelectItem key={cobrador.id} value={cobrador.id.toString()}>
                          {cobrador.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label htmlFor="name">Nombre de la Ruta</Label>
                  <Input
                    id="name"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  />
                </div>
                <div>
                  <Label htmlFor="description">Descripción</Label>
                  <Textarea
                    id="description"
                    value={formData.description}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  />
                </div>
                <div>
                  <Label htmlFor="clients">Clientes</Label>
                  <Select
                    value=""
                    onValueChange={(value) => {
                      const clientId = parseInt(value);
                      if (!formData.client_ids.includes(clientId)) {
                        setFormData({
                          ...formData,
                          client_ids: [...formData.client_ids, clientId],
                        });
                      }
                    }}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Agregar cliente" />
                    </SelectTrigger>
                    <SelectContent>
                      {clients.map((client) => (
                        <SelectItem key={client.id} value={client.id.toString()}>
                          {client.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <div className="mt-2 flex flex-wrap gap-2">
                    {formData.client_ids.map((clientId) => {
                      const client = clients.find(c => c.id === clientId);
                      return client ? (
                        <Badge key={clientId} variant="secondary">
                          {client.name}
                          <button
                            onClick={() => setFormData({
                              ...formData,
                              client_ids: formData.client_ids.filter(id => id !== clientId),
                            })}
                            className="ml-1"
                          >
                            ×
                          </button>
                        </Badge>
                      ) : null;
                    })}
                  </div>
                </div>
                <Button onClick={handleCreateRoute} className="w-full">
                  Crear Ruta
                </Button>
              </div>
            </DialogContent>
          </Dialog>
        </div>

        <div className="mb-4">
          <div className="relative">
            <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Buscar rutas..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-10"
            />
          </div>
        </div>

        <div className="grid gap-4">
          {filteredRoutes.map((route) => (
            <Card key={route.id}>
              <CardContent className="p-6">
                <div className="flex justify-between items-start">
                  <div className="flex-1">
                    <h3 className="text-lg font-semibold">{route.name}</h3>
                    {route.description && (
                      <p className="text-muted-foreground mt-1">{route.description}</p>
                    )}
                    <div className="mt-2">
                      <p className="text-sm">
                        <strong>Cobrador:</strong> {route.cobrador.name}
                      </p>
                      <div className="flex items-center gap-2 mt-1">
                        <Users className="w-4 h-4" />
                        <span className="text-sm text-muted-foreground">
                          {route.clients.length} clientes
                        </span>
                      </div>
                      <div className="flex flex-wrap gap-1 mt-2">
                        {route.clients.map((client) => (
                          <Badge key={client.id} variant="outline" className="text-xs">
                            {client.name}
                          </Badge>
                        ))}
                      </div>
                    </div>
                  </div>
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => openEditDialog(route)}
                    >
                      <Edit className="w-4 h-4" />
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleDeleteRoute(route.id)}
                    >
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Editar Ruta</DialogTitle>
            </DialogHeader>
            <div className="space-y-4">
              <div>
                <Label htmlFor="edit-cobrador">Cobrador</Label>
                <Select
                  value={formData.cobrador_id}
                  onValueChange={(value) => setFormData({ ...formData, cobrador_id: value })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Seleccionar cobrador" />
                  </SelectTrigger>
                  <SelectContent>
                    {cobradores.map((cobrador) => (
                      <SelectItem key={cobrador.id} value={cobrador.id.toString()}>
                        {cobrador.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label htmlFor="edit-name">Nombre de la Ruta</Label>
                <Input
                  id="edit-name"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                />
              </div>
              <div>
                <Label htmlFor="edit-description">Descripción</Label>
                <Textarea
                  id="edit-description"
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                />
              </div>
              <div>
                <Label htmlFor="edit-clients">Clientes</Label>
                <Select
                  value=""
                  onValueChange={(value) => {
                    const clientId = parseInt(value);
                    if (!formData.client_ids.includes(clientId)) {
                      setFormData({
                        ...formData,
                        client_ids: [...formData.client_ids, clientId],
                      });
                    }
                  }}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Agregar cliente" />
                  </SelectTrigger>
                  <SelectContent>
                    {clients.map((client) => (
                      <SelectItem key={client.id} value={client.id.toString()}>
                        {client.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <div className="mt-2 flex flex-wrap gap-2">
                  {formData.client_ids.map((clientId) => {
                    const client = clients.find(c => c.id === clientId);
                    return client ? (
                      <Badge key={clientId} variant="secondary">
                        {client.name}
                        <button
                          onClick={() => setFormData({
                            ...formData,
                            client_ids: formData.client_ids.filter(id => id !== clientId),
                          })}
                          className="ml-1"
                        >
                          ×
                        </button>
                      </Badge>
                    ) : null;
                  })}
                </div>
              </div>
              <Button onClick={handleUpdateRoute} className="w-full">
                Actualizar Ruta
              </Button>
            </div>
          </DialogContent>
        </Dialog>
      </div>
    </>
  );
} 