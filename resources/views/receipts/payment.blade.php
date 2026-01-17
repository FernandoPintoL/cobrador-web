<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pago #{{ $payment->id }}</title>
    <style>
        /* ============================================
           ESTILOS PARA IMPRESORA TÉRMICA (58-80mm)
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: 80mm auto;
            margin: 0;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.3;
            color: #000;
            background: #fff;
            width: 80mm;
            max-width: 80mm;
            margin: 0 auto;
            padding: 5mm;
        }

        .receipt {
            width: 100%;
        }

        /* Header */
        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .receipt-header .company-name {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .receipt-header .receipt-title {
            font-size: 14px;
            font-weight: bold;
            margin: 8px 0 4px 0;
        }

        .receipt-header .receipt-number {
            font-size: 12px;
        }

        /* Información del recibo */
        .receipt-info {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #000;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 11px;
        }

        .info-row .label {
            font-weight: bold;
        }

        .info-row .value {
            text-align: right;
        }

        /* Datos del cliente */
        .client-info {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #000;
        }

        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
            text-align: center;
        }

        .client-name {
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 4px;
        }

        /* Detalle del pago */
        .payment-detail {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #000;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 11px;
        }

        .detail-row.total {
            font-size: 14px;
            font-weight: bold;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid #000;
        }

        /* Estado del crédito */
        .credit-status {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #000;
        }

        .status-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 10px;
        }

        .status-row.highlight {
            font-weight: bold;
            font-size: 11px;
        }

        /* Footer */
        .receipt-footer {
            text-align: center;
            font-size: 10px;
            margin-top: 10px;
        }

        .receipt-footer .thanks {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .receipt-footer .timestamp {
            font-size: 9px;
            color: #666;
            margin-top: 8px;
        }

        .receipt-footer .no-valid {
            font-size: 9px;
            font-style: italic;
            margin-top: 4px;
        }

        /* Separador */
        .separator {
            text-align: center;
            margin: 6px 0;
            font-size: 10px;
            color: #666;
        }

        .separator::before,
        .separator::after {
            content: "- - - - -";
        }

        /* Cobrador info */
        .cobrador-info {
            font-size: 10px;
            text-align: center;
            margin-bottom: 8px;
        }

        /* Print styles */
        @media print {
            body {
                width: 80mm;
                padding: 2mm;
            }

            .no-print {
                display: none;
            }
        }

        /* Botón de imprimir (solo visible en pantalla) */
        .print-button {
            display: block;
            width: 100%;
            padding: 10px;
            margin-top: 15px;
            background: #4472C4;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            text-align: center;
        }

        .print-button:hover {
            background: #2E5090;
        }

        @media print {
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <!-- Header -->
        <div class="receipt-header">
            <div class="company-name">{{ $tenant->name ?? 'Sistema de Cobros' }}</div>
            @if(isset($tenant->phone))
                <div style="font-size: 10px;">Tel: {{ $tenant->phone }}</div>
            @endif
            <div class="receipt-title">RECIBO DE PAGO</div>
            <div class="receipt-number">No. {{ str_pad($payment->id, 8, '0', STR_PAD_LEFT) }}</div>
        </div>

        <!-- Información del recibo -->
        <div class="receipt-info">
            <div class="info-row">
                <span class="label">Fecha:</span>
                <span class="value">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') }}</span>
            </div>
            <div class="info-row">
                <span class="label">Hora:</span>
                <span class="value">{{ \Carbon\Carbon::parse($payment->created_at)->format('H:i') }}</span>
            </div>
        </div>

        <!-- Datos del cliente -->
        <div class="client-info">
            <div class="section-title">Cliente</div>
            <div class="client-name">{{ $payment->credit->client->name ?? 'N/A' }}</div>
            @if($payment->credit->client->phone ?? false)
                <div style="text-align: center; font-size: 10px;">Tel: {{ $payment->credit->client->phone }}</div>
            @endif
        </div>

        <!-- Detalle del pago -->
        <div class="payment-detail">
            <div class="section-title">Detalle del Pago</div>
            <div class="detail-row">
                <span>Crédito #:</span>
                <span>{{ $payment->credit_id }}</span>
            </div>
            @if($payment->installment_number)
            <div class="detail-row">
                <span>Cuota #:</span>
                <span>{{ $payment->installment_number }}</span>
            </div>
            @endif
            <div class="detail-row">
                <span>Método:</span>
                <span>
                    @switch($payment->payment_method)
                        @case('cash')
                            Efectivo
                            @break
                        @case('transfer')
                            Transferencia
                            @break
                        @case('card')
                            Tarjeta
                            @break
                        @case('mobile_payment')
                            Pago Móvil
                            @break
                        @default
                            {{ $payment->payment_method }}
                    @endswitch
                </span>
            </div>
            <div class="detail-row">
                <span>Estado:</span>
                <span>
                    @switch($payment->status)
                        @case('completed')
                            Completado
                            @break
                        @case('partial')
                            Parcial
                            @break
                        @default
                            {{ $payment->status }}
                    @endswitch
                </span>
            </div>
            <div class="detail-row total">
                <span>MONTO PAGADO:</span>
                <span>Bs {{ number_format($payment->amount, 2) }}</span>
            </div>
        </div>

        <!-- Estado del crédito -->
        <div class="credit-status">
            <div class="section-title">Estado del Crédito</div>
            <div class="status-row">
                <span>Monto total:</span>
                <span>Bs {{ number_format($payment->credit->total_amount ?? 0, 2) }}</span>
            </div>
            <div class="status-row">
                <span>Total pagado:</span>
                <span>Bs {{ number_format($payment->credit->total_paid ?? 0, 2) }}</span>
            </div>
            <div class="status-row highlight">
                <span>Saldo pendiente:</span>
                <span>Bs {{ number_format($payment->credit->balance ?? 0, 2) }}</span>
            </div>
            @if($payment->credit->total_installments ?? false)
            <div class="status-row">
                <span>Cuotas pagadas:</span>
                <span>{{ $payment->credit->paid_installments ?? 0 }} / {{ $payment->credit->total_installments }}</span>
            </div>
            @endif
        </div>

        <!-- Cobrador -->
        <div class="cobrador-info">
            <strong>Cobrador:</strong> {{ $payment->receivedBy->name ?? 'N/A' }}
        </div>

        <!-- Footer -->
        <div class="receipt-footer">
            <div class="thanks">¡Gracias por su pago!</div>
            <div class="separator"></div>
            <div class="timestamp">
                Generado: {{ now()->format('d/m/Y H:i:s') }}
            </div>
            <div class="no-valid">
                Este recibo es un comprobante de pago
            </div>
        </div>

        <!-- Botón de imprimir (solo visible en pantalla) -->
        <!-- <button class="print-button no-print" onclick="window.print()">
            Imprimir Recibo
        </button> -->
    </div>
</body>
</html>
