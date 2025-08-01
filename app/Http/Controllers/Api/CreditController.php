<?php

namespace App\Http\Controllers\Api;

use App\Models\Credit;
use App\Models\User;
use Illuminate\Http\Request;

class CreditController extends BaseController
{
    /**
     * Display a listing of credits.
     */
    public function index(Request $request)
    {
        $credits = Credit::with(['client', 'payments'])
            ->when($request->client_id, function ($query, $clientId) {
                $query->where('client_id', $clientId);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->search, function ($query, $search) {
                $query->whereHas('client', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            })
            ->paginate(15);

        return $this->sendResponse($credits);
    }

    /**
     * Store a newly created credit.
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'balance' => 'required|numeric|min:0',
            'frequency' => 'required|in:daily,weekly,biweekly,monthly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'in:active,completed,defaulted,cancelled',
        ]);

        $credit = Credit::create([
            'client_id' => $request->client_id,
            'amount' => $request->amount,
            'balance' => $request->balance,
            'frequency' => $request->frequency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status ?? 'active',
        ]);

        $credit->load(['client', 'payments']);

        return $this->sendResponse($credit, 'Crédito creado exitosamente');
    }

    /**
     * Display the specified credit.
     */
    public function show(Credit $credit)
    {
        $credit->load(['client', 'payments']);
        return $this->sendResponse($credit);
    }

    /**
     * Update the specified credit.
     */
    public function update(Request $request, Credit $credit)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'balance' => 'required|numeric|min:0',
            'frequency' => 'required|in:daily,weekly,biweekly,monthly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'in:active,completed,defaulted,cancelled',
        ]);

        $credit->update([
            'client_id' => $request->client_id,
            'amount' => $request->amount,
            'balance' => $request->balance,
            'frequency' => $request->frequency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status,
        ]);

        $credit->load(['client', 'payments']);

        return $this->sendResponse($credit, 'Crédito actualizado exitosamente');
    }

    /**
     * Remove the specified credit.
     */
    public function destroy(Credit $credit)
    {
        $credit->delete();
        return $this->sendResponse([], 'Crédito eliminado exitosamente');
    }

    /**
     * Get credits by client.
     */
    public function getByClient(User $client)
    {
        $credits = $client->credits()->with(['payments'])->get();
        return $this->sendResponse($credits);
    }

    /**
     * Get remaining installments for a credit.
     */
    public function getRemainingInstallments(Credit $credit)
    {
        $remaining = $credit->getRemainingInstallments();
        return $this->sendResponse(['remaining_installments' => $remaining]);
    }
} 