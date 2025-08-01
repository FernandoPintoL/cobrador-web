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
import { Plus, Search, Edit, Trash2, Eye, Filter, Download, Calculator, TrendingUp, TrendingDown, DollarSign } from 'lucide-react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

interface CashBalance {
  id: number;
  cobrador: {
    id: number;
    name: string;
  };
  date: string;
  initial_amount: number;
  collected_amount: number;
  lent_amount: number;
  final_amount: number;
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

interface CashBalancesResponse {
  data: CashBalance[];
  pagination: PaginationData;
  filters: {
    cobrador_id: string;
    date_from: string;
    date_to: string;
  };
}

export default function CashBalancesIndex() {
  const [cashBalances, setCashBalances] = useState<CashBalance[]>([]);
  const [pagination, setPagination] = useState<PaginationData | null>(null);
  const [loading, setLoading] = useState(true);
  const [cobradorFilter, setCobradorFilter] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [showFilters, setShowFilters] = useState(false);
  const [deleteDialog, setDeleteDialog] = useState<{ show: boolean; cashBalance: CashBalance | null }>({
    show: false,
    cashBalance: null,
  });
  const [cobradores, setCobradores] = useState([]);

  const fetchCashBalances = async (page = 1) => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        page: page.toString(),
        cobrador_id: cobradorFilter,
        date_from: dateFrom,
        date_to: dateTo,
      });

      const response = await fetch(`/api/cash-balances?${params}`);
      const data: CashBalancesResponse = await response.json();

      if (data.data) {
        setCashBalances(data.data);
        setPagination(data.pagination);
      }
    } catch (error) {
      console.error('Error fetching cash balances:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCashBalances();
    
    // Cargar cobradores para el filtro
    fetch('/api/users?role=cobrador')
      .then(res => res.json())
      .then(data => setCobradores(data.data || []));
  }, [cobradorFilter, dateFrom, dateTo]);

  const handleDelete = async (cashBalance: CashBalance) => {
    try {
      const response = await fetch(`/api/cash-balances/${cashBalance.id}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
        },
      });

      if (response.ok) {
        fetchCashBalances(pagination?.current_page || 1);
        setDeleteDialog({ show: false, cashBalance: null });
      }
    } catch (error) {
      console.error('Error deleting cash balance:', error);
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN',
    }).format(amount);
  };

  const formatDate = (date: string) => {
    return format(new Date(date), 'dd/MM/yyyy', { locale: es });
  };

  const calculateDifference = (cashBalance: CashBalance) => {
    const expected = cashBalance.initial_amount + cashBalance.collected_amount - cashBalance.lent_amount;
    const actual = cashBalance.final_amount;
    return actual - expected;
  };

  const getDifferenceBadge = (difference: number) => {
    if (difference === 0) {
      return <Badge variant="default">Balanceado</Badge>;
    } else if (difference > 0) {
      return <Badge variant="secondary" className="text-green-600">+{formatCurrency(difference)}</Badge>;
    } else {
      return <Badge variant="destructive">{formatCurrency(difference)}</Badge>;
    }
  };

  return (
    <>
      <Head title="Arqueo de Caja" />
      
      <div className="space-y-6">
        {/* Header */}
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Arqueo de Caja</h1>
            <p className="text-muted-foreground">
              Gestiona el arqueo diario de cada cobrador
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => setShowFilters(!showFilters)}>
              <Filter className="h-4 w-4 mr-2" />
              Filtros
            </Button>
            <Button variant="outline">
              <Download className="h-4 w-4 mr-2" />
              Exportar
            </Button>
            <Dialog>
              <DialogTrigger asChild>
                <Button>
                  <Plus className="h-4 w-4 mr-2" />
                  Nuevo Arqueo
                </Button>
              </DialogTrigger>
              <DialogContent className="max-w-2xl">
                <DialogHeader>
                  <DialogTitle>Crear Nuevo Arqueo de Caja</DialogTitle>
                </DialogHeader>
                <CashBalanceForm onSuccess={() => fetchCashBalances()} />
              </DialogContent>
            </Dialog>
            <Dialog>
              <DialogTrigger asChild>
                <Button variant="secondary">
                  <Calculator className="h-4 w-4 mr-2" />
                  Auto Calcular
                </Button>
              </DialogTrigger>
              <DialogContent className="max-w-md">
                <DialogHeader>
                  <DialogTitle>Auto Calcular Arqueo</DialogTitle>
                </DialogHeader>
                <AutoCalculateForm onSuccess={() => fetchCashBalances()} />
              </DialogContent>
            </Dialog>
          </div>
        </div>

        {/* Filters */}
        {showFilters && (
          <Card>
            <CardContent className="pt-6">
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Select value={cobradorFilter} onValueChange={setCobradorFilter}>
                  <SelectTrigger>
                    <SelectValue placeholder="Cobrador" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="">Todos los cobradores</SelectItem>
                    {cobradores.map((cobrador: any) => (
                      <SelectItem key={cobrador.id} value={cobrador.id.toString()}>
                        {cobrador.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Input
                  type="date"
                  placeholder="Desde"
                  value={dateFrom}
                  onChange={(e) => setDateFrom(e.target.value)}
                />
                <Input
                  type="date"
                  placeholder="Hasta"
                  value={dateTo}
                  onChange={(e) => setDateTo(e.target.value)}
                />
                <Button
                  variant="outline"
                  onClick={() => {
                    setCobradorFilter('');
                    setDateFrom('');
                    setDateTo('');
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
                <DollarSign className="h-4 w-4 text-muted-foreground" />
                <div className="space-y-1">
                  <p className="text-sm font-medium leading-none">Total Recaudado</p>
                  <p className="text-2xl font-bold">
                    {formatCurrency(cashBalances.reduce((sum, cb) => sum + cb.collected_amount, 0))}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <TrendingUp className="h-4 w-4 text-green-600" />
                <div className="space-y-1">
                  <p className="text-sm font-medium leading-none">Total Prestado</p>
                  <p className="text-2xl font-bold text-green-600">
                    {formatCurrency(cashBalances.reduce((sum, cb) => sum + cb.lent_amount, 0))}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <TrendingDown className="h-4 w-4 text-red-600" />
                <div className="space-y-1">
                  <p className="text-sm font-medium leading-none">Diferencia Total</p>
                  <p className="text-2xl font-bold text-red-600">
                    {formatCurrency(cashBalances.reduce((sum, cb) => sum + calculateDifference(cb), 0))}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <Calculator className="h-4 w-4 text-blue-600" />
                <div className="space-y-1">
                  <p className="text-sm font-medium leading-none">Arqueos</p>
                  <p className="text-2xl font-bold text-blue-600">
                    {cashBalances.length}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Cash Balances Table */}
        <Card>
          <CardHeader>
            <CardTitle>
              Lista de Arqueos
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
                      <TableHead>Cobrador</TableHead>
                      <TableHead>Fecha</TableHead>
                      <TableHead>Monto Inicial</TableHead>
                      <TableHead>Recaudado</TableHead>
                      <TableHead>Prestado</TableHead>
                      <TableHead>Monto Final</TableHead>
                      <TableHead>Diferencia</TableHead>
                      <TableHead className="text-right">Acciones</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {cashBalances.map((cashBalance) => (
                      <TableRow key={cashBalance.id}>
                        <TableCell>
                          <div className="font-medium">{cashBalance.cobrador.name}</div>
                        </TableCell>
                        <TableCell>{formatDate(cashBalance.date)}</TableCell>
                        <TableCell className="font-medium">
                          {formatCurrency(cashBalance.initial_amount)}
                        </TableCell>
                        <TableCell className="text-green-600 font-medium">
                          +{formatCurrency(cashBalance.collected_amount)}
                        </TableCell>
                        <TableCell className="text-red-600 font-medium">
                          -{formatCurrency(cashBalance.lent_amount)}
                        </TableCell>
                        <TableCell className="font-medium">
                          {formatCurrency(cashBalance.final_amount)}
                        </TableCell>
                        <TableCell>
                          {getDifferenceBadge(calculateDifference(cashBalance))}
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex justify-end gap-2">
                            <Button variant="ghost" size="sm" asChild>
                              <Link href={`/cash-balances/${cashBalance.id}`}>
                                <Eye className="h-4 w-4" />
                              </Link>
                            </Button>
                            <Button variant="ghost" size="sm" asChild>
                              <Link href={`/cash-balances/${cashBalance.id}/edit`}>
                                <Edit className="h-4 w-4" />
                              </Link>
                            </Button>
                            <AlertDialog>
                              <AlertDialogTrigger asChild>
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => setDeleteDialog({ show: true, cashBalance })}
                                >
                                  <Trash2 className="h-4 w-4" />
                                </Button>
                              </AlertDialogTrigger>
                              <AlertDialogContent>
                                <AlertDialogHeader>
                                  <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                                  <AlertDialogDescription>
                                    Esta acción no se puede deshacer. Se eliminará permanentemente el arqueo
                                    de {cashBalance.cobrador.name} del {formatDate(cashBalance.date)}.
                                  </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                  <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                  <AlertDialogAction
                                    onClick={() => handleDelete(cashBalance)}
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
                                fetchCashBalances(pagination.current_page - 1);
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
                                    fetchCashBalances(page);
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
                                fetchCashBalances(pagination.current_page + 1);
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

// Componente del formulario de arqueo de caja
function CashBalanceForm({ onSuccess }: { onSuccess: () => void }) {
  const [formData, setFormData] = useState({
    cobrador_id: '',
    date: new Date().toISOString().split('T')[0],
    initial_amount: '',
    collected_amount: '',
    lent_amount: '',
    final_amount: '',
  });
  const [cobradores, setCobradores] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    // Cargar cobradores
    fetch('/api/users?role=cobrador')
      .then(res => res.json())
      .then(data => setCobradores(data.data || []));
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const response = await fetch('/api/cash-balances', {
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
      console.error('Error creating cash balance:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="text-sm font-medium">Cobrador</label>
          <Select value={formData.cobrador_id} onValueChange={(value) => setFormData({ ...formData, cobrador_id: value })}>
            <SelectTrigger>
              <SelectValue placeholder="Seleccionar cobrador" />
            </SelectTrigger>
            <SelectContent>
              {cobradores.map((cobrador: any) => (
                <SelectItem key={cobrador.id} value={cobrador.id.toString()}>
                  {cobrador.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        
        <div>
          <label className="text-sm font-medium">Fecha</label>
          <Input
            type="date"
            value={formData.date}
            onChange={(e) => setFormData({ ...formData, date: e.target.value })}
          />
        </div>
        
        <div>
          <label className="text-sm font-medium">Monto Inicial</label>
          <Input
            type="number"
            step="0.01"
            value={formData.initial_amount}
            onChange={(e) => setFormData({ ...formData, initial_amount: e.target.value })}
            placeholder="0.00"
          />
        </div>
        
        <div>
          <label className="text-sm font-medium">Monto Recaudado</label>
          <Input
            type="number"
            step="0.01"
            value={formData.collected_amount}
            onChange={(e) => setFormData({ ...formData, collected_amount: e.target.value })}
            placeholder="0.00"
          />
        </div>
        
        <div>
          <label className="text-sm font-medium">Monto Prestado</label>
          <Input
            type="number"
            step="0.01"
            value={formData.lent_amount}
            onChange={(e) => setFormData({ ...formData, lent_amount: e.target.value })}
            placeholder="0.00"
          />
        </div>
        
        <div>
          <label className="text-sm font-medium">Monto Final</label>
          <Input
            type="number"
            step="0.01"
            value={formData.final_amount}
            onChange={(e) => setFormData({ ...formData, final_amount: e.target.value })}
            placeholder="0.00"
          />
        </div>
      </div>
      
      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline">
          Cancelar
        </Button>
        <Button type="submit" disabled={loading}>
          {loading ? 'Creando...' : 'Crear Arqueo'}
        </Button>
      </div>
    </form>
  );
}

// Componente para auto calcular arqueo
function AutoCalculateForm({ onSuccess }: { onSuccess: () => void }) {
  const [formData, setFormData] = useState({
    cobrador_id: '',
    date: new Date().toISOString().split('T')[0],
    initial_amount: '',
  });
  const [cobradores, setCobradores] = useState([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    // Cargar cobradores
    fetch('/api/users?role=cobrador')
      .then(res => res.json())
      .then(data => setCobradores(data.data || []));
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const response = await fetch('/api/cash-balances/auto-calculate', {
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
      console.error('Error auto calculating cash balance:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="space-y-4">
        <div>
          <label className="text-sm font-medium">Cobrador</label>
          <Select value={formData.cobrador_id} onValueChange={(value) => setFormData({ ...formData, cobrador_id: value })}>
            <SelectTrigger>
              <SelectValue placeholder="Seleccionar cobrador" />
            </SelectTrigger>
            <SelectContent>
              {cobradores.map((cobrador: any) => (
                <SelectItem key={cobrador.id} value={cobrador.id.toString()}>
                  {cobrador.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        
        <div>
          <label className="text-sm font-medium">Fecha</label>
          <Input
            type="date"
            value={formData.date}
            onChange={(e) => setFormData({ ...formData, date: e.target.value })}
          />
        </div>
        
        <div>
          <label className="text-sm font-medium">Monto Inicial</label>
          <Input
            type="number"
            step="0.01"
            value={formData.initial_amount}
            onChange={(e) => setFormData({ ...formData, initial_amount: e.target.value })}
            placeholder="0.00"
          />
        </div>
      </div>
      
      <div className="text-sm text-muted-foreground">
        <p>El sistema calculará automáticamente:</p>
        <ul className="list-disc list-inside mt-2 space-y-1">
          <li>Monto recaudado (suma de pagos del día)</li>
          <li>Monto prestado (suma de créditos nuevos del día)</li>
          <li>Monto final esperado</li>
        </ul>
      </div>
      
      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline">
          Cancelar
        </Button>
        <Button type="submit" disabled={loading}>
          {loading ? 'Calculando...' : 'Auto Calcular'}
        </Button>
      </div>
    </form>
  );
} 