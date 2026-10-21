<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $service) {}

    public function store(Request $request, Rental $invoice)
    {
        $this->service->create($invoice, $request->all());
        return redirect()->route('rentals.show', $invoice)->with('success', 'Pembayaran berhasil ditambahkan!');
    }

    public function update(Request $request, Rental $invoice, Payment $payment)
    {
        $this->service->update($invoice, $payment, $request->all());
        return redirect()->route('rentals.show', $invoice)->with('success', 'Pembayaran berhasil diperbarui!');
    }

    public function destroy(Rental $invoice, Payment $payment)
    {
        try {
            $this->service->delete($invoice, $payment);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('rentals.show', $invoice)->with('error', $e->getMessage());
        }
        return redirect()->route('rentals.show', $invoice)->with('success', 'Pembayaran berhasil dihapus!');
    }

    public function void(Rental $invoice, Payment $payment)
    {
        $this->service->voidPayment($invoice, $payment);
        return redirect()->route('rentals.show', $invoice)->with('success', 'Pembayaran berhasil di-void!');
    }

    public function refund(Rental $invoice, Payment $payment)
    {
        $this->service->refundPayment($invoice, $payment);
        return redirect()->route('rentals.show', $invoice)->with('success', 'Pembayaran berhasil di-refund!');
    }
}

