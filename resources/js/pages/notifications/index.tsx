import React, { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from '@/components/ui/pagination';
import { Plus, Search, Edit, Trash2, Eye, Filter, Download, Bell, Check, CheckCheck } from 'lucide-react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

interface Notification {
  id: number;
  user: {
    id: number;
    name: string;
  };
  payment?: {
    id: number;
    amount: number;
    client: {
      name: string;
    };
  };
  type: 'payment_reminder' | 'overdue_payment' | 'credit_approved' | 'credit_rejected' | 'system_alert';
  message: string;
  status: 'unread' | 'read' | 'archived';
  created_at: string;
  updated_at: string;
}

interface PaginationData {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

interface NotificationsResponse {
  data: Notification[];
  pagination: PaginationData;
  filters: {
    status: string;
    type: string;
    search: string;
  };
}

export default function NotificationsIndex() {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [pagination, setPagination] = useState<PaginationData | null>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [showFilters, setShowFilters] = useState(false);
  const [deleteDialog, setDeleteDialog] = useState<{ show: boolean; notification: Notification | null }>({
    show: false,
    notification: null,
  });

  const fetchNotifications = async (page = 1) => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        page: page.toString(),
        search,
        status: statusFilter,
        type: typeFilter,
      });

      const response = await fetch(`/api/notifications?${params}`);
      const data: NotificationsResponse = await response.json();

      if (data.data) {
        setNotifications(data.data);
        setPagination(data.pagination);
      }
    } catch (error) {
      console.error('Error fetching notifications:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchNotifications();
  }, [search, statusFilter, typeFilter]);

  const handleDelete = async (notification: Notification) => {
    try {
      const response = await fetch(`/api/notifications/${notification.id}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
        },
      });

      if (response.ok) {
        fetchNotifications(pagination?.current_page || 1);
        setDeleteDialog({ show: false, notification: null });
      }
    } catch (error) {
      console.error('Error deleting notification:', error);
    }
  };

  const handleMarkAsRead = async (notification: Notification) => {
    try {
      const response = await fetch(`/api/notifications/${notification.id}/mark-read`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
        },
      });

      if (response.ok) {
        fetchNotifications(pagination?.current_page || 1);
      }
    } catch (error) {
      console.error('Error marking notification as read:', error);
    }
  };

  const handleMarkAllAsRead = async () => {
    try {
      const response = await fetch('/api/notifications/mark-all-read', {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
        },
      });

      if (response.ok) {
        fetchNotifications(pagination?.current_page || 1);
      }
    } catch (error) {
      console.error('Error marking all notifications as read:', error);
    }
  };

  const getStatusBadge = (status: string) => {
    const statusConfig = {
      unread: { variant: 'default', text: 'No leída', icon: Bell },
      read: { variant: 'secondary', text: 'Leída', icon: Check },
      archived: { variant: 'outline', text: 'Archivada', icon: CheckCheck },
    };

    const config = statusConfig[status as keyof typeof statusConfig] || { variant: 'outline', text: status, icon: Bell };
    const Icon = config.icon;
    return (
      <Badge variant={config.variant as any}>
        <Icon className="h-3 w-3 mr-1" />
        {config.text}
      </Badge>
    );
  };

  const getTypeBadge = (type: string) => {
    const typeConfig = {
      payment_reminder: { variant: 'default', text: 'Recordatorio de Pago' },
      overdue_payment: { variant: 'destructive', text: 'Pago Vencido' },
      credit_approved: { variant: 'secondary', text: 'Crédito Aprobado' },
      credit_rejected: { variant: 'outline', text: 'Crédito Rechazado' },
      system_alert: { variant: 'secondary', text: 'Alerta del Sistema' },
    };

    const config = typeConfig[type as keyof typeof typeConfig] || { variant: 'outline', text: type };
    return <Badge variant={config.variant as any}>{config.text}</Badge>;
  };

  const formatDate = (date: string) => {
    return format(new Date(date), 'dd/MM/yyyy HH:mm', { locale: es });
  };

  const unreadCount = notifications.filter(n => n.status === 'unread').length;

  return (
    <>
      <Head title="Notificaciones" />
      
      <div className="space-y-6">
        {/* Header */}
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Notificaciones</h1>
            <p className="text-muted-foreground">
              Gestiona todas las notificaciones del sistema
              {unreadCount > 0 && (
                <span className="ml-2 text-sm font-medium text-primary">
                  ({unreadCount} sin leer)
                </span>
              )}
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => setShowFilters(!showFilters)}>
              <Filter className="h-4 w-4 mr-2" />
              Filtros
            </Button>
            {unreadCount > 0 && (
              <Button variant="outline" onClick={handleMarkAllAsRead}>
                <CheckCheck className="h-4 w-4 mr-2" />
                Marcar Todo como Leído
              </Button>
            )}
            <Button variant="outline">
              <Download className="h-4 w-4 mr-2" />
              Exportar
            </Button>
            <Dialog>
              <DialogTrigger asChild>
                <Button>
                  <Plus className="h-4 w-4 mr-2" />
                  Nueva Notificación
                </Button>
              </DialogTrigger>
              <DialogContent className="max-w-2xl">
                <DialogHeader>
                  <DialogTitle>Crear Nueva Notificación</DialogTitle>
                </DialogHeader>
                <NotificationForm onSuccess={() => fetchNotifications()} />
              </DialogContent>
            </Dialog>
          </div>
        </div>

        {/* Filters */}
        {showFilters && (
          <Card>
            <CardContent className="pt-6">
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="relative">
                  <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                  <Input
                    placeholder="Buscar en mensajes..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="pl-10"
                  />
                </div>
                <Select value={statusFilter} onValueChange={setStatusFilter}>
                  <SelectTrigger>
                    <SelectValue placeholder="Estado" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="">Todos los estados</SelectItem>
                    <SelectItem value="unread">No leídas</SelectItem>
                    <SelectItem value="read">Leídas</SelectItem>
                    <SelectItem value="archived">Archivadas</SelectItem>
                  </SelectContent>
                </Select>
                <Select value={typeFilter} onValueChange={setTypeFilter}>
                  <SelectTrigger>
                    <SelectValue placeholder="Tipo" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="">Todos los tipos</SelectItem>
                    <SelectItem value="payment_reminder">Recordatorio de Pago</SelectItem>
                    <SelectItem value="overdue_payment">Pago Vencido</SelectItem>
                    <SelectItem value="credit_approved">Crédito Aprobado</SelectItem>
                    <SelectItem value="credit_rejected">Crédito Rechazado</SelectItem>
                    <SelectItem value="system_alert">Alerta del Sistema</SelectItem>
                  </SelectContent>
                </Select>
                <Button
                  variant="outline"
                  onClick={() => {
                    setSearch('');
                    setStatusFilter('');
                    setTypeFilter('');
                  }}
                >
                  Limpiar
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <Bell className="h-4 w-4 text-muted-foreground" />
                <div className="space-y-1">
                  <p className="text-sm font-medium leading-none">Total</p>
                  <p className="text-2xl font-bold">
                    {notifications.length}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <Bell className="h-4 w-4 text-primary" />
                <div className="space-y-1">
                  <p className="text-sm font-medium leading-none">Sin Leer</p>
                  <p className="text-2xl font-bold text-primary">
                    {unreadCount}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <Check className="h-4 w-4 text-green-600" />
                <div className="space-y-1">
                  <p className="text-sm font-medium leading-none">Leídas</p>
                  <p className="text-2xl font-bold text-green-600">
                    {notifications.filter(n => n.status === 'read').length}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <CheckCheck className="h-4 w-4 text-blue-600" />
                <div className="space-y-1">
                  <p className="text-sm font-medium leading-none">Archivadas</p>
                  <p className="text-2xl font-bold text-blue-600">
                    {notifications.filter(n => n.status === 'archived').length}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Notifications Table */}
        <Card>
          <CardHeader>
            <CardTitle>
              Lista de Notificaciones
              {pagination && (
                <span className="text-sm font-normal text-muted-foreground ml-2">
                  ({pagination.from}-{pagination.to} de {pagination.total})
                </span>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="flex justify-center items-center h-32">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
              </div>
            ) : (
              <>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Usuario</TableHead>
                      <TableHead>Tipo</TableHead>
                      <TableHead>Mensaje</TableHead>
                      <TableHead>Estado</TableHead>
                      <TableHead>Fecha</TableHead>
                      <TableHead className="text-right">Acciones</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {notifications.map((notification) => (
                      <TableRow key={notification.id} className={notification.status === 'unread' ? 'bg-muted/50' : ''}>
                        <TableCell>
                          <div className="font-medium">{notification.user.name}</div>
                        </TableCell>
                        <TableCell>{getTypeBadge(notification.type)}</TableCell>
                        <TableCell>
                          <div className="max-w-md">
                            <p className="text-sm">{notification.message}</p>
                            {notification.payment && (
                              <p className="text-xs text-muted-foreground mt-1">
                                Pago: {notification.payment.client.name} - {formatCurrency(notification.payment.amount)}
                              </p>
                            )}
                          </div>
                        </TableCell>
                        <TableCell>{getStatusBadge(notification.status)}</TableCell>
                        <TableCell>{formatDate(notification.created_at)}</TableCell>
                        <TableCell className="text-right">
                          <div className="flex justify-end gap-2">
                            {notification.status === 'unread' && (
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleMarkAsRead(notification)}
                              >
                                <Check className="h-4 w-4" />
                              </Button>
                            )}
                            <Button variant="ghost" size="sm" asChild>
                              <Link href={`/notifications/${notification.id}`}>
                                <Eye className="h-4 w-4" />
                              </Link>
                            </Button>
                            <Button variant="ghost" size="sm" asChild>
                              <Link href={`/notifications/${notification.id}/edit`}>
                                <Edit className="h-4 w-4" />
                              </Link>
                            </Button>
                            <AlertDialog>
                              <AlertDialogTrigger asChild>
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => setDeleteDialog({ show: true, notification })}
                                >
                                  <Trash2 className="h-4 w-4" />
                                </Button>
                              </AlertDialogTrigger>
                              <AlertDialogContent>
                                <AlertDialogHeader>
                                  <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                                  <AlertDialogDescription>
                                    Esta acción no se puede deshacer. Se eliminará permanentemente la notificación
                                    para {notification.user.name}.
                                  </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                  <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                  <AlertDialogAction
                                    onClick={() => handleDelete(notification)}
                                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                  >
                                    Eliminar
                                  </AlertDialogAction>
                                </AlertDialogFooter>
                              </AlertDialogContent>
                            </AlertDialog>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>

                {/* Pagination */}
                {pagination && pagination.last_page > 1 && (
                  <div className="mt-6">
                    <Pagination>
                      <PaginationContent>
                        <PaginationItem>
                          <PaginationPrevious
                            href="#"
                            onClick={(e) => {
                              e.preventDefault();
                              if (pagination.current_page > 1) {
                                fetchNotifications(pagination.current_page - 1);
                              }
                            }}
                            className={pagination.current_page <= 1 ? 'pointer-events-none opacity-50' : ''}
                          />
                        </PaginationItem>
                        
                        {Array.from({ length: pagination.last_page }, (_, i) => i + 1)
                          .filter(page => 
                            page === 1 || 
                            page === pagination.last_page || 
                            Math.abs(page - pagination.current_page) <= 2
                          )
                          .map((page, index, array) => (
                            <React.Fragment key={page}>
                              {index > 0 && array[index - 1] !== page - 1 && (
                                <PaginationItem>
                                  <span className="px-4 py-2">...</span>
                                </PaginationItem>
                              )}
                              <PaginationItem>
                                <PaginationLink
                                  href="#"
                                  onClick={(e) => {
                                    e.preventDefault();
                                    fetchNotifications(page);
                                  }}
                                  isActive={page === pagination.current_page}
                                >
                                  {page}
                                </PaginationLink>
                              </PaginationItem>
                            </React.Fragment>
                          ))}
                        
                        <PaginationItem>
                          <PaginationNext
                            href="#"
                            onClick={(e) => {
                              e.preventDefault();
                              if (pagination.current_page < pagination.last_page) {
                                fetchNotifications(pagination.current_page + 1);
                              }
                            }}
                            className={pagination.current_page >= pagination.last_page ? 'pointer-events-none opacity-50' : ''}
                          />
                        </PaginationItem>
                      </PaginationContent>
                    </Pagination>
                  </div>
                )}
              </>
            )}
          </CardContent>
        </Card>
      </div>
    </>
  );
}

// Componente del formulario de notificación
function NotificationForm({ onSuccess }: { onSuccess: () => void }) {
  const [formData, setFormData] = useState({
    user_id: '',
    payment_id: '',
    type: 'system_alert',
    message: '',
  });
  const [users, setUsers] = useState([]);
  const [payments, setPayments] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    // Cargar usuarios
    fetch('/api/users')
      .then(res => res.json())
      .then(data => setUsers(data.data || []));

    // Cargar pagos
    fetch('/api/payments')
      .then(res => res.json())
      .then(data => setPayments(data.data || []));
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const response = await fetch('/api/notifications', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData),
      });

      if (response.ok) {
        onSuccess();
        // Cerrar modal
      }
    } catch (error) {
      console.error('Error creating notification:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="text-sm font-medium">Usuario</label>
          <Select value={formData.user_id} onValueChange={(value) => setFormData({ ...formData, user_id: value })}>
            <SelectTrigger>
              <SelectValue placeholder="Seleccionar usuario" />
            </SelectTrigger>
            <SelectContent>
              {users.map((user: any) => (
                <SelectItem key={user.id} value={user.id.toString()}>
                  {user.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        
        <div>
          <label className="text-sm font-medium">Tipo</label>
          <Select value={formData.type} onValueChange={(value) => setFormData({ ...formData, type: value })}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="payment_reminder">Recordatorio de Pago</SelectItem>
              <SelectItem value="overdue_payment">Pago Vencido</SelectItem>
              <SelectItem value="credit_approved">Crédito Aprobado</SelectItem>
              <SelectItem value="credit_rejected">Crédito Rechazado</SelectItem>
              <SelectItem value="system_alert">Alerta del Sistema</SelectItem>
            </SelectContent>
          </Select>
        </div>
        
        <div>
          <label className="text-sm font-medium">Pago (Opcional)</label>
          <Select value={formData.payment_id} onValueChange={(value) => setFormData({ ...formData, payment_id: value })}>
            <SelectTrigger>
              <SelectValue placeholder="Seleccionar pago" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">Sin pago asociado</SelectItem>
              {payments.map((payment: any) => (
                <SelectItem key={payment.id} value={payment.id.toString()}>
                  {payment.client.name} - {formatCurrency(payment.amount)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>
      
      <div>
        <label className="text-sm font-medium">Mensaje</label>
        <textarea
          className="w-full min-h-[100px] p-3 border border-input rounded-md resize-none"
          value={formData.message}
          onChange={(e) => setFormData({ ...formData, message: e.target.value })}
          placeholder="Escribe el mensaje de la notificación..."
        />
      </div>
      
      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline">
          Cancelar
        </Button>
        <Button type="submit" disabled={loading}>
          {loading ? 'Creando...' : 'Crear Notificación'}
        </Button>
      </div>
    </form>
  );
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
  }).format(amount);
}; 