import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Loader2, DollarSign, Calculator, AlertCircle, CheckCircle, Clock, User, Calendar } from 'lucide-react';

interface CashBalance {
  id: number;
  cobrador_id: number;
  cobrador_name: string;
  date: string;
  initial_amount: number;
  collected_amount: number;
  lent_amount: number;
  final_amount: number;
  created_at: string;
  updated_at: string;
}

interface Payment {
  id: number;
  client_name: string;
  amount: number;
  payment_date: string;
  payment_method: 'cash' | 'qr' | 'transfer';
  status: 'paid' | 'pending' | 'overdue';
}

interface Credit {
  id: number;
  client_name: string;
  amount: number;
  start_date: string;
  status: 'active' | 'completed' | 'overdue';
}

interface CashReconciliationProps {
  auth: {
    user: {
      id: number;
      name: string;
      email: string;
      roles: string[];
    };
  };
}

const CashReconciliation: React.FC<CashReconciliationProps> = ({ auth }) => {
  const [cashBalances, setCashBalances] = useState<CashBalance[]>([]);
  const [selectedBalance, setSelectedBalance] = useState<CashBalance | null>(null);
  const [payments, setPayments] = useState<Payment[]>([]);
  const [credits, setCredits] = useState<Credit[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedDate, setSelectedDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [selectedCobrador, setSelectedCobrador] = useState<string>('all');
  const [cobradores, setCobradores] = useState<any[]>([]);
  const [isNewBalanceDialogOpen, setIsNewBalanceDialogOpen] = useState(false);
  const [newBalance, setNewBalance] = useState({
    cobrador_id: '',
    date: new Date().toISOString().split('T')[0],
    initial_amount: 0,
    collected_amount: 0,
    lent_amount: 0,
  });

  useEffect(() => {
    loadCashBalances();
    loadCobradores();
  }, [selectedDate, selectedCobrador]);

  const loadCashBalances = async () => {
    try {
      setLoading(true);
      let url = '/api/cash-balances';
      const params = new URLSearchParams();
      
      if (selectedDate) params.append('date', selectedDate);
      if (selectedCobrador !== 'all') params.append('cobrador_id', selectedCobrador);
      
      if (params.toString()) url += `?${params.toString()}`;
      
      const response = await fetch(url);
      if (!response.ok) throw new Error('Error al cargar arqueos de caja');
      
      const data = await response.json();
      setCashBalances(data.data || []);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Error desconocido');
    } finally {
      setLoading(false);
    }
  };

  const loadCobradores = async () => {
    try {
      const response = await fetch('/api/users?role=cobrador');
      if (!response.ok) throw new Error('Error al cargar cobradores');
      
      const data = await response.json();
      setCobradores(data.data || []);
    } catch (err) {
      console.error('Error al cargar cobradores:', err);
    }
  };

  const loadBalanceDetails = async (balanceId: number) => {
    try {
      // Cargar pagos del día
      const paymentsResponse = await fetch(`/api/payments?cash_balance_id=${balanceId}`);
      if (paymentsResponse.ok) {
        const paymentsData = await paymentsResponse.json();
        setPayments(paymentsData.data || []);
      }

      // Cargar créditos prestados del día
      const creditsResponse = await fetch(`/api/credits?created_date=${selectedDate}&cobrador_id=${selectedBalance?.cobrador_id}`);
      if (creditsResponse.ok) {
        const creditsData = await creditsResponse.json();
        setCredits(creditsData.data || []);
      }
    } catch (err) {
      console.error('Error al cargar detalles:', err);
    }
  };

  const handleSelectBalance = (balance: CashBalance) => {
    setSelectedBalance(balance);
    loadBalanceDetails(balance.id);
  };

  const handleCreateNewBalance = async () => {
    try {
      const response = await fetch('/api/cash-balances', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify(newBalance),
      });

      if (!response.ok) throw new Error('Error al crear arqueo de caja');
      
      const data = await response.json();
      setCashBalances(prev => [data.data, ...prev]);
      setIsNewBalanceDialogOpen(false);
      setNewBalance({
        cobrador_id: '',
        date: new Date().toISOString().split('T')[0],
        initial_amount: 0,
        collected_amount: 0,
        lent_amount: 0,
      });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Error al crear arqueo');
    }
  };

  const calculateExpectedFinal = (balance: CashBalance) => {
    return balance.initial_amount + balance.collected_amount - balance.lent_amount;
  };

  const getBalanceStatus = (balance: CashBalance) => {
    const expected = calculateExpectedFinal(balance);
    const difference = balance.final_amount - expected;
    
    if (Math.abs(difference) < 0.01) return 'balanced';
    if (difference > 0) return 'surplus';
    return 'deficit';
  };

  const getStatusBadge = (status: string) => {
    const variants = {
      balanced: 'bg-green-100 text-green-800',
      surplus: 'bg-blue-100 text-blue-800',
      deficit: 'bg-red-100 text-red-800'
    };
    
    return (
      <Badge className={variants[status as keyof typeof variants] || 'bg-gray-100 text-gray-800'}>
        {status === 'balanced' ? 'Balanceado' : status === 'surplus' ? 'Excedente' : 'Déficit'}
      </Badge>
    );
  };

  const getPaymentMethodIcon = (method: string) => {
    switch (method) {
      case 'cash':
        return <DollarSign className="h-4 w-4 text-green-500" />;
      case 'qr':
        return <CheckCircle className="h-4 w-4 text-blue-500" />;
      case 'transfer':
        return <Clock className="h-4 w-4 text-purple-500" />;
      default:
        return <DollarSign className="h-4 w-4 text-gray-500" />;
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-8 w-8 animate-spin" />
        <span className="ml-2">Cargando arqueos de caja...</span>
      </div>
    );
  }

  return (
    <>
      <Head title="Arqueo de Caja - Cobrador" />
      
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Arqueo de Caja</h1>
            <p className="text-muted-foreground">
              Reconciliación diaria de efectivo por cobrador
            </p>
          </div>
          
          <Dialog open={isNewBalanceDialogOpen} onOpenChange={setIsNewBalanceDialogOpen}>
            <DialogTrigger asChild>
              <Button>
                <Calculator className="h-4 w-4 mr-2" />
                Nuevo Arqueo
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Nuevo Arqueo de Caja</DialogTitle>
              </DialogHeader>
              <div className="space-y-4">
                <div>
                  <Label htmlFor="cobrador">Cobrador</Label>
                  <Select value={newBalance.cobrador_id} onValueChange={(value) => setNewBalance(prev => ({ ...prev, cobrador_id: value }))}>
                    <SelectTrigger>
                      <SelectValue placeholder="Seleccionar cobrador" />
                    </SelectTrigger>
                    <SelectContent>
                      {cobradores.map(cobrador => (
                        <SelectItem key={cobrador.id} value={cobrador.id.toString()}>
                          {cobrador.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                
                <div>
                  <Label htmlFor="date">Fecha</Label>
                  <Input
                    type="date"
                    value={newBalance.date}
                    onChange={(e) => setNewBalance(prev => ({ ...prev, date: e.target.value }))}
                  />
                </div>
                
                <div>
                  <Label htmlFor="initial_amount">Monto Inicial</Label>
                  <Input
                    type="number"
                    step="0.01"
                    value={newBalance.initial_amount}
                    onChange={(e) => setNewBalance(prev => ({ ...prev, initial_amount: parseFloat(e.target.value) || 0 }))}
                  />
                </div>
                
                <div>
                  <Label htmlFor="collected_amount">Monto Recaudado</Label>
                  <Input
                    type="number"
                    step="0.01"
                    value={newBalance.collected_amount}
                    onChange={(e) => setNewBalance(prev => ({ ...prev, collected_amount: parseFloat(e.target.value) || 0 }))}
                  />
                </div>
                
                <div>
                  <Label htmlFor="lent_amount">Monto Prestado</Label>
                  <Input
                    type="number"
                    step="0.01"
                    value={newBalance.lent_amount}
                    onChange={(e) => setNewBalance(prev => ({ ...prev, lent_amount: parseFloat(e.target.value) || 0 }))}
                  />
                </div>
                
                <div className="flex justify-end space-x-2">
                  <Button variant="outline" onClick={() => setIsNewBalanceDialogOpen(false)}>
                    Cancelar
                  </Button>
                  <Button onClick={handleCreateNewBalance}>
                    Crear Arqueo
                  </Button>
                </div>
              </div>
            </DialogContent>
          </Dialog>
        </div>

        {/* Filtros */}
        <Card>
          <CardContent className="pt-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <Label htmlFor="date">Fecha</Label>
                <Input
                  type="date"
                  value={selectedDate}
                  onChange={(e) => setSelectedDate(e.target.value)}
                />
              </div>
              
              <div>
                <Label htmlFor="cobrador">Cobrador</Label>
                <Select value={selectedCobrador} onValueChange={setSelectedCobrador}>
                  <SelectTrigger>
                    <SelectValue placeholder="Todos los cobradores" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos los cobradores</SelectItem>
                    {cobradores.map(cobrador => (
                      <SelectItem key={cobrador.id} value={cobrador.id.toString()}>
                        {cobrador.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              
              <div className="flex items-end">
                <Button onClick={loadCashBalances} variant="outline" className="w-full">
                  Actualizar
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>

        {error && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Lista de arqueos */}
          <div className="lg:col-span-1">
            <Card>
              <CardHeader>
                <CardTitle>Arqueos de Caja</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  {cashBalances.map(balance => (
                    <div
                      key={balance.id}
                      className={`p-3 border rounded-lg cursor-pointer transition-colors ${
                        selectedBalance?.id === balance.id 
                          ? 'border-primary bg-primary/5' 
                          : 'border-border hover:bg-muted/50'
                      }`}
                      onClick={() => handleSelectBalance(balance)}
                    >
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="font-medium">{balance.cobrador_name}</p>
                          <p className="text-sm text-muted-foreground">
                            {new Date(balance.date).toLocaleDateString()}
                          </p>
                        </div>
                        <div className="text-right">
                          <p className="font-medium">${balance.final_amount.toFixed(2)}</p>
                          {getStatusBadge(getBalanceStatus(balance))}
                        </div>
                      </div>
                    </div>
                  ))}
                  
                  {cashBalances.length === 0 && (
                    <p className="text-center text-muted-foreground py-4">
                      No hay arqueos de caja para mostrar
                    </p>
                  )}
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Detalles del arqueo seleccionado */}
          {selectedBalance && (
            <div className="lg:col-span-2 space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle>Detalles del Arqueo</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="text-center">
                      <p className="text-sm text-muted-foreground">Monto Inicial</p>
                      <p className="text-2xl font-bold text-green-600">
                        ${selectedBalance.initial_amount.toFixed(2)}
                      </p>
                    </div>
                    
                    <div className="text-center">
                      <p className="text-sm text-muted-foreground">Recaudado</p>
                      <p className="text-2xl font-bold text-blue-600">
                        ${selectedBalance.collected_amount.toFixed(2)}
                      </p>
                    </div>
                    
                    <div className="text-center">
                      <p className="text-sm text-muted-foreground">Prestado</p>
                      <p className="text-2xl font-bold text-orange-600">
                        ${selectedBalance.lent_amount.toFixed(2)}
                      </p>
                    </div>
                    
                    <div className="text-center">
                      <p className="text-sm text-muted-foreground">Final</p>
                      <p className="text-2xl font-bold text-purple-600">
                        ${selectedBalance.final_amount.toFixed(2)}
                      </p>
                    </div>
                  </div>
                  
                  <div className="mt-4 p-3 bg-muted rounded-lg">
                    <p className="text-sm">
                      <strong>Esperado:</strong> ${calculateExpectedFinal(selectedBalance).toFixed(2)} | 
                      <strong> Diferencia:</strong> ${(selectedBalance.final_amount - calculateExpectedFinal(selectedBalance)).toFixed(2)}
                    </p>
                  </div>
                </CardContent>
              </Card>

              {/* Pagos del día */}
              <Card>
                <CardHeader>
                  <CardTitle>Pagos Recaudados</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-2">
                    {payments.map(payment => (
                      <div key={payment.id} className="flex items-center justify-between p-2 border rounded">
                        <div className="flex items-center space-x-2">
                          {getPaymentMethodIcon(payment.payment_method)}
                          <div>
                            <p className="font-medium">{payment.client_name}</p>
                            <p className="text-sm text-muted-foreground">
                              {new Date(payment.payment_date).toLocaleDateString()}
                            </p>
                          </div>
                        </div>
                        <div className="text-right">
                          <p className="font-medium">${payment.amount.toFixed(2)}</p>
                          <Badge variant="outline" className="text-xs">
                            {payment.payment_method === 'cash' ? 'Efectivo' : 
                             payment.payment_method === 'qr' ? 'QR' : 'Transferencia'}
                          </Badge>
                        </div>
                      </div>
                    ))}
                    
                    {payments.length === 0 && (
                      <p className="text-center text-muted-foreground py-4">
                        No hay pagos registrados para este día
                      </p>
                    )}
                  </div>
                </CardContent>
              </Card>

              {/* Créditos prestados */}
              <Card>
                <CardHeader>
                  <CardTitle>Créditos Prestados</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-2">
                    {credits.map(credit => (
                      <div key={credit.id} className="flex items-center justify-between p-2 border rounded">
                        <div>
                          <p className="font-medium">{credit.client_name}</p>
                          <p className="text-sm text-muted-foreground">
                            {new Date(credit.start_date).toLocaleDateString()}
                          </p>
                        </div>
                        <div className="text-right">
                          <p className="font-medium text-red-600">-${credit.amount.toFixed(2)}</p>
                          <Badge variant="outline" className="text-xs">
                            {credit.status === 'active' ? 'Activo' : 'Completado'}
                          </Badge>
                        </div>
                      </div>
                    ))}
                    
                    {credits.length === 0 && (
                      <p className="text-center text-muted-foreground py-4">
                        No hay créditos prestados en este día
                      </p>
                    )}
                  </div>
                </CardContent>
              </Card>
            </div>
          )}
        </div>
      </div>
    </>
  );
};

export default CashReconciliation; 