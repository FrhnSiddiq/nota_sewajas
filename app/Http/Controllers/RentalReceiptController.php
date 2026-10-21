<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Services\InvoiceDocumentService;
use App\Services\RentalReceiptService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class RentalReceiptController extends Controller
{
    public function __construct(protected RentalReceiptService $service) {}

    public function show(Rental $rental)
    {
        $this->authorize('view', $rental);

        $receipt = $this->service->getReceiptPayload($rental);
        return view('rentals.receipt', compact('rental', 'receipt'));
    }

    public function pdf(Rental $rental)
    {
        $this->authorize('view', $rental);

        return $this->service->downloadPdf($rental);
    }

    public function qr(Rental $rental)
    {
        $this->authorize('view', $rental);

        $qr = $this->service->getQrPayload($rental);
        return response()->json($qr);
    }

    public function whatsapp(Rental $rental)
    {
        $this->authorize('view', $rental);

        return $this->service->sendWhatsapp($rental);
    }

    /**
     * Unduh Invoice A4 — format identik dengan invoice di Riwayat Penyewaan
     * (view yang sama), data live dari rental.
     */
    public function invoice(Rental $rental, InvoiceDocumentService $invoices)
    {
        $this->authorize('view', $rental);

        $pdf = Pdf::loadView('rental_histories.invoice', [
                'history' => $invoices->forRental($rental),
            ])
            ->setPaper('a4', 'portrait');

        return $pdf->download($rental->invoice_number . '-invoice.pdf');
    }
}

