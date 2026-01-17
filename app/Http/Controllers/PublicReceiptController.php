<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PublicReceiptController extends Controller
{
    /**
     * Genera un token seguro para un pago específico.
     * Este token permite acceso público al recibo sin autenticación.
     */
    public static function generateToken(int $paymentId): string
    {
        $secret = config('app.key');
        return substr(hash('sha256', $paymentId . $secret), 0, 16);
    }

    /**
     * Verifica si el token es válido para el pago.
     */
    public static function validateToken(int $paymentId, string $token): bool
    {
        return hash_equals(self::generateToken($paymentId), $token);
    }

    /**
     * Genera la URL pública completa para un recibo.
     */
    public static function getPublicUrl(int $paymentId): string
    {
        $token = self::generateToken($paymentId);
        return url("/recibo/{$paymentId}/{$token}");
    }

    /**
     * Muestra el recibo de pago en formato PDF.
     * Esta ruta es pública y no requiere autenticación.
     */
    public function show(Request $request, Payment $payment, string $token)
    {
        // Validar el token
        if (!self::validateToken($payment->id, $token)) {
            abort(403, 'Token inválido o expirado');
        }

        // Cargar relaciones necesarias
        $payment->load(['credit.client', 'receivedBy']);

        // Obtener información del tenant desde el crédito
        $tenant = $payment->credit?->client?->tenant;

        $data = [
            'payment' => $payment,
            'tenant' => $tenant,
        ];

        $format = $request->get('format', 'pdf');

        if ($format === 'html') {
            return response()->view('receipts.payment', $data)
                ->header('Content-Type', 'text/html');
        }

        // Default: PDF
        $pdf = Pdf::loadView('receipts.payment', $data);
        $pdf->setPaper([0, 0, 226.77, 600], 'portrait'); // 80mm width

        // Si se solicita descarga
        if ($request->get('download')) {
            return $pdf->download("recibo-pago-{$payment->id}.pdf");
        }

        // Por defecto, mostrar inline (en el navegador)
        return $pdf->stream("recibo-pago-{$payment->id}.pdf");
    }
}
