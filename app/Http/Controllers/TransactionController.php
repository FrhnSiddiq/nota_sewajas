<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\Payment;
use App\Models\ActivityLog;
use App\Services\TransactionService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(protected TransactionService $service) {}

    public function index(Request $request)
    {
        $payload = $this->service->list($request);
        return view('transactions.index', $payload);
    }

    public function show(Rental $transaction)
    {
        $payload = $this->service->detail($transaction);
        return view('transactions.show', compact('transaction', 'payload'));
    }

    public function edit(Rental $transaction)
    {
        $payload = $this->service->detail($transaction);
        return view('transactions.edit', compact('transaction', 'payload'));
    }

    public function update(Request $request, Rental $transaction)
    {
        $this->service->update($transaction, $request->all());
        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaksi berhasil diperbarui!');
    }

    public function destroy(Rental $transaction)
    {
        try {
            $this->service->delete($transaction);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('transactions.index')->with('error', $e->getMessage());
        }
        return redirect()->route('transactions.index')->with('success', 'Transaksi berhasil dihapus!');
    }
}

