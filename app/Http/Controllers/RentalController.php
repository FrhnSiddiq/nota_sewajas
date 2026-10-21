<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ActivityLog;
use App\Models\StatusHistory;
use App\Models\Branch;
use App\Services\RentalService;
use App\Services\RentalReceiptService;
use App\Http\Requests\StoreRentalRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class RentalController extends Controller
{
    public function __construct(protected RentalService $rentalService) {}

    private function loadVisibleActivityLogs(Rental $rental): void
    {
        if (Auth::user()->isSuperAdmin()) {
            $rental->load('activityLogs.user');
            return;
        }

        $rental->setRelation('activityLogs', collect());
    }

    public function downloadActivityLog(Rental $rental)
    {
        $user = Auth::user();
        abort_unless($user->isSuperAdmin(), 403);

        $logs = $rental->activityLogs()
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        $lines = [];
        $lines[] = 'Rental Activity Log';
        $lines[] = '==================';
        $lines[] = 'Invoice: ' . $rental->invoice_number;
        $lines[] = 'Customer: ' . optional($rental->customer)->name;
        $lines[] = 'Generated: ' . now()->format('Y-m-d H:i:s');
        $lines[] = '';
        $lines[] = str_repeat('-', 80);

        foreach ($logs as $log) {
            $lines[] = '';
            $lines[] = '[' . $log->created_at->format('Y-m-d H:i:s') . '] ' . $log->action;
            $lines[] = 'User: ' . optional($log->user)->name . ' (ID: ' . ($log->user_id ?? '-') . ')';
            $lines[] = 'Description: ' . $log->description;
            if (!empty($log->old_values)) {
                $lines[] = 'Old Values: ' . json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
            if (!empty($log->new_values)) {
                $lines[] = 'New Values: ' . json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
            $lines[] = 'IP: ' . ($log->ip_address ?? '-');
            $lines[] = str_repeat('-', 80);
        }

        $content = implode("\n", $lines);
        $filename = 'rental_activity_log_' . $rental->invoice_number . '_' . now()->format('Y-m-d_H-i-s') . '.txt';

        return response($content, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function rentalActivityLogQuery(Request $request): Builder
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin(), 403);

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $requestedBranchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        $query = ActivityLog::query()
            ->where('model_type', Rental::class)
            ->with(['user', 'branch', 'rental.branch']);
        $query->orderByDesc('created_at');

        if ($requestedBranchId !== null) {
            $query->whereHas('rental', fn ($rental) => $rental->where('branch_id', $requestedBranchId));
        }

        return $query;
    }

    public function activityLog(Request $request)
    {
        $user = Auth::user();
        $isSuperAdmin = $user->isSuperAdmin();

        $query = $this->rentalActivityLogQuery($request);

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('rental', fn($r) => $r->where('invoice_number', 'like', "%{$search}%"));
            });
        }

        $logs = $query->paginate(50)->withQueryString();
        $branches = $isSuperAdmin ? Branch::orderBy('name')->get() : collect();

        return view('rentals.activity-log', compact('logs', 'branches', 'isSuperAdmin'));
    }

    public function exportActivityLogExcel(Request $request)
    {
        $user = Auth::user();
        $query = $this->rentalActivityLogQuery($request);

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->get();

        $filename = 'rental_activity_log_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'Waktu',
                'Cabang',
                'User',
                'Aksi',
                'Deskripsi',
                'Invoice',
                'IP Address',
            ], ';');

            foreach ($logs as $log) {
                $rental = \App\Models\Rental::find($log->model_id);
                fputcsv($handle, [
                    $log->created_at?->format('Y-m-d H:i:s'),
                    $log->rental?->branch?->name ?? $log->branch?->name,
                    $log->user?->name,
                    $log->action,
                    $log->description,
                    $rental?->invoice_number,
                    $log->ip_address,
                ], ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportActivityLogPdf(Request $request)
    {
        $user = Auth::user();
        $isSuperAdmin = $user->isSuperAdmin();
        $query = $this->rentalActivityLogQuery($request);

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->get();
        $branchName = $isSuperAdmin && $request->filled('branch_id')
            ? (Branch::find($request->input('branch_id'))?->name ?? 'Semua Cabang')
            : ($user->branch?->name ?? 'Semua Cabang');

        $pdf = Pdf::loadView('reports.pdf.rental-activity-log', [
            'logs'        => $logs,
            'branchName'  => $branchName,
            'startDate'   => $request->input('date_from'),
            'endDate'     => $request->input('date_to'),
            'generatedAt' => now()->format('d/m/Y H:i'),
            'generatedBy' => $user->name,
        ])->setPaper('a4', 'landscape');

        $filename = 'laporan-activity-log-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    private function qrisData(): array
    {
        $qrisPath = \App\Services\SettingsService::get('qris_image');
        if (!$qrisPath || !Storage::disk('public')->exists($qrisPath)) {
            return ['available' => false, 'url' => null];
        }

        $fullPath = storage_path('app/public/' . $qrisPath);
        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        $url = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($fullPath));

        return ['available' => true, 'url' => $url];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Rental::with(["customer", "items", "createdBy", "branch"]);

        if ($user->isSales() || $user->isAdminToko()) {
            $query->whereIn("branch_id", $user->accessible_branch_ids);
        }

        $query->where(function ($query) {
            $query->where("rental_status", "!=", Rental::STATUS_RETURNED)
                ->orWhere("payment_status", "!=", Rental::PAYMENT_PAID)
                ->orWhereNull("fine_status")
                ->orWhereNotIn("fine_status", [Rental::FINE_NONE, Rental::FINE_PAID]);
        });

        if ($request->has("status") && $request->status) {
            $query->where("rental_status", $request->status);
        }

        if ($request->has("search") && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where("invoice_number", "like", "%$search%")
                  ->orWhereHas("customer", function($q2) use ($search) {
                      $q2->where("name", "like", "%$search%")
                         ->orWhere("phone", "like", "%$search%");
                  });
            });
        }

        $sort = $request->get("sort", "created_at");
        $order = $request->get("order", "desc");
        $allowedSorts = ["created_at", "rental_date", "total_amount", "invoice_number"];
        if (!in_array($sort, $allowedSorts, true)) $sort = "created_at";
        $order = $order === "asc" ? "asc" : "desc";

        $rentals = $query->orderBy($sort, $order)->paginate(15)->withQueryString();
        return view("rentals.index", compact("rentals"));
    }

    public function create()
    {
        $user = Auth::user();
        $customers = Customer::when($user->role === "sales", function($q) use ($user) {
            $q->whereIn("branch_id", $user->accessible_branch_ids);
        })->when($user->isAdminToko(), function($q) use ($user) {
            $q->whereIn("branch_id", $user->accessible_branch_ids);
        })->get();

        $products = Product::when(!$user->isSuperAdmin() && ($user->isAdminToko() || $user->branch_access_type === 'specific'), function($q) use ($user) {
            $q->whereHas('branches', fn($bq) => $bq->whereIn('branches.id', $user->accessible_branch_ids));
        })->where("status", "available")->where("stock_available", ">", 0)->with('sizes')->get();

        $qris = $this->qrisData();

        return view("rentals.create", compact("customers", "products", "qris"));
    }

    public function store(StoreRentalRequest $request)
    {
        $rental = $this->rentalService->createRental($request->validated());
        return redirect()->route("rentals.show", $rental)->with("success", "Penyewaan berhasil dibuat!");
    }

    public function show(Rental $rental)
    {
        $this->authorize('view', $rental);

        $rental->load(["customer", "items.product.category", "createdBy", "payments", "guarantees", "branch"]);
        $this->loadVisibleActivityLogs($rental);
        $rentalData = $this->rentalService->buildDetailPayload($rental);
        $qris = $this->qrisData();
        return view("rentals.show", compact("rental", "rentalData", "qris"));
    }

    public function edit(Rental $rental)
    {
        $user = Auth::user();
        if (!$user || !$user->canManage()) abort(403);

        $rental->load(["customer", "items.product", "guarantees"]);
        $customers = Customer::when($user->isSales(), function($q) use ($user) {
            $q->where("branch_id", $user->branch_id);
        })->get();

        $products = Product::whereHas("category", function($q) {
            $q->where("name", "like", "Jas%");
        })->when($user->isSales(), function($q) use ($user) {
            $q->where("branch_id", $user->branch_id);
        })->with('sizes')->get();

        $qris = $this->qrisData();

        return view("rentals.edit", compact("rental", "customers", "products", "qris"));
    }

    public function update(Request $request, Rental $rental)
    {
        $user = Auth::user();
        if (!$user || !$user->canManage()) abort(403);

        $isLocked = $rental->payment_status === Rental::PAYMENT_PAID
            || $rental->rental_status === Rental::STATUS_ACTIVE;

        if ($isLocked) {
            $lockedFieldErrors = [];
            /** @var \Illuminate\Support\Carbon|null $rentalDate */
            $rentalDate = $rental->rental_date;
            /** @var \Illuminate\Support\Carbon|null $returnDueDate */
            $returnDueDate = $rental->return_due_date;

            if ((string) $request->input('customer_id') !== (string) $rental->customer_id) {
                $lockedFieldErrors[] = 'Pelanggan';
            }
            if ($request->has('rental_date') && $request->input('rental_date') !== $rentalDate?->format('Y-m-d')) {
                $lockedFieldErrors[] = 'Tanggal Sewa';
            }
            if ($request->has('return_due_date') && $request->input('return_due_date') !== $returnDueDate?->format('Y-m-d')) {
                $lockedFieldErrors[] = 'Tanggal Pengembalian';
            }

            $originalItemMap = $rental->items()
                ->get(['product_id', 'quantity'])
                ->mapWithKeys(fn($i) => [(string) $i->product_id => (string) $i->quantity])
                ->all();

            if ($request->has('items')) {
                $submittedItemMap = collect($request->input('items', []))
                    ->mapWithKeys(fn($i) => [(string) $i['product_id'] => (string) $i['quantity']])
                    ->all();

                if (count($originalItemMap) !== count($submittedItemMap) || $originalItemMap !== $submittedItemMap) {
                    $lockedFieldErrors[] = 'Item Disewa (produk/jumlah)';
                }
            }

            if ($lockedFieldErrors) {
                abort(422, 'Field berikut sudah dikunci karena penyewaan sudah dibayar/berjalan dan tidak dapat diubah: ' . implode(', ', $lockedFieldErrors));
            }
        }

        $data = $request->validate([
            "customer_id" => "required|exists:customers,id",
            "rental_date" => "required|date",
            "duration_days" => "required|integer|min:1",
            "discount" => "nullable|numeric|min:0",
            "deposit" => "nullable|numeric|min:0",
            "rental_status" => "nullable|in:waiting,active,overdue,returned,cancelled",
            "payment_status" => "nullable|in:unpaid,partial,paid",
            "return_due_date" => "nullable|date",
            "notes" => "nullable|string",
            "payment_method" => "nullable|string|in:cash,qris,transfer",
            "guarantee_type" => "nullable|string|in:ktp,sim,deposit,custom",
            "guarantee_deposit" => "nullable|numeric|min:0",
            "guarantee_id_number" => "nullable|string|max:50",
            "guarantee_notes" => "nullable|string|max:500",
            "remove_guarantee_photo" => "nullable|in:0,1",
            "guarantee_id_photo" => "nullable|image|mimes:jpeg,png,jpg|max:2048",
            "items" => "required|array|min:1",
            "items.*.product_id" => "required|exists:products,id",
            "items.*.product_size" => "required|in:XS,S,M,L,XL,XXL,3XL,4XL,5XL,6XL,7XL",
            "items.*.quantity" => "required|integer|min:1",
        ]);

        $oldValues = app(\App\Services\RentalService::class)->rentalSnapshotWhitelisted($rental);

        DB::transaction(function () use ($rental, $data, $request, $oldValues) {
            $rental->update([
                "customer_id" => $data["customer_id"],
                "rental_date" => $data["rental_date"],
                "duration_days" => (int) $data["duration_days"],
                "discount" => (float) ($data["discount"] ?? 0),
                "deposit" => (float) ($data["deposit"] ?? 0),
                "rental_status" => $data["rental_status"] ?? $rental->rental_status,
                "notes" => $data["notes"] ?? null,
            ]);

            // Validasi: Jika status diubah menjadi 'returned', pembayaran harus lunas
            if (isset($data["rental_status"]) && $data["rental_status"] === "returned" && $rental->payment_status !== Rental::PAYMENT_PAID) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payment_status' => 'Penyewaan belum dapat diselesaikan karena pembayaran belum lunas. Silakan lunasi pembayaran terlebih dahulu.'
                ]);
            }

            if (!in_array($rental->rental_status, ["cancelled","returned"], true)) {
                $rental->update([
                    "return_due_date" => Carbon::parse($data["rental_date"])->addDays((int) $data["duration_days"]),
                ]);
            }

            $durationDays = (int) $data["duration_days"];
            $submitted = collect($data["items"])->map(function ($row) {
                return [
                    "product_id" => (int) $row["product_id"],
                    "product_size" => (string) $row["product_size"],
                    "quantity" => (int) $row["quantity"],
                ];
            });

            $deduped = $submitted->groupBy(fn($r) => $r["product_id"]."|".$r["product_size"]);
            if ($deduped->count() !== $submitted->count()) {
                abort(422, "Duplicate rental items are not allowed.");
            }

            // Lock and restore old stock
            $oldItems = $rental->items()->get(["id","product_id","product_size","quantity"]);
            foreach ($oldItems as $oldItem) {
                $product = Product::whereKey($oldItem->product_id)->lockForUpdate()->firstOrFail();
                $product->incrementSizeStock($oldItem->product_size, (int)$oldItem->quantity);
            }

            $subtotal = 0;
            $existingByKey = $rental->items->keyBy(fn($it) => $it->product_id."|".$it->product_size);
            $seenKeys = [];

            // Lock and deduct new stock
            foreach ($deduped as $key => $rows) {
                $row = $rows->first();
                $seenKeys[] = $key;
                $product = Product::whereKey($row["product_id"])->lockForUpdate()->firstOrFail();
                if (!str_starts_with($product->category?->name ?? "", "Jas")) abort(422, "Invalid category for product.");

                $requestedQty = (int)$row["quantity"];
                if ($requestedQty <= 0) abort(422, "Quantity must be > 0.");
                $remaining = $product->decrementSizeStock($row["product_size"], $requestedQty);
                if ($remaining < 0) abort(422, "Stok ukuran {$row['product_size']} produk {$product->name} tidak mencukupi.");

                $itemSubtotal = (float)$product->rental_price * $requestedQty * $durationDays;
                $subtotal += $itemSubtotal;

                if ($existingByKey->has($key)) {
                    $item = $existingByKey->get($key);
                    $item->update([
                        "product_id" => $product->id,
                        "product_name" => $product->name,
                        "product_size" => $row["product_size"],
                        "product_color" => $product->color,
                        "quantity" => $requestedQty,
                        "price_per_day" => $product->rental_price,
                        "duration_days" => $durationDays,
                        "subtotal" => $itemSubtotal,
                    ]);
                } else {
                    $rental->items()->create([
                        "product_id" => $product->id,
                        "product_name" => $product->name,
                        "product_size" => $row["product_size"],
                        "product_color" => $product->color,
                        "quantity" => $requestedQty,
                        "price_per_day" => $product->rental_price,
                        "duration_days" => $durationDays,
                        "subtotal" => $itemSubtotal,
                    ]);
                }
            }

            foreach ($rental->items()->get() as $item) {
                $key = $item->product_id."|".$item->product_size;
                if (!in_array($key, $seenKeys, true)) $item->delete();
            }

            $discount = (float)($data["discount"] ?? 0);
            if ($discount > $subtotal) {
                abort(422, 'Diskon tidak boleh melebihi subtotal.');
            }
            $totalAmount = (float)$subtotal - $discount;
            $rental->update([
                "subtotal" => (float)$subtotal,
                "discount" => $discount,
                "deposit" => (float)($data["deposit"] ?? 0),
                "total_amount" => (float)$totalAmount,
            ]);

            if (!empty($data["payment_method"])) {
                $rental->update(["payment_method" => $data["payment_method"]]);
            }

            $guaranteeType = $data["guarantee_type"] ?? null;
            /** @var \App\Models\Guarantee|null $existingGuarantee */
            $existingGuarantee = $rental->guarantees()->first();

    if ($guaranteeType) {
        $guaranteeData = [
            "type" => $guaranteeType,
            "id_number" => $data["guarantee_id_number"] ?? null,
            "description" => $data["guarantee_notes"] ?? null,
            "deposit_amount" => $guaranteeType === 'deposit' ? (float) ($data["guarantee_deposit"] ?? 0) : 0,
        ];

                if ($request->hasFile("guarantee_id_photo")) {
                    if ($existingGuarantee && $existingGuarantee->id_photo) {
                        Storage::disk("public")->delete($existingGuarantee->id_photo);
                    }
                    $guaranteeData["id_photo"] = $request->file("guarantee_id_photo")->store("guarantees/id_photos", "public");
                } elseif ($data["remove_guarantee_photo"] ?? false) {
                    if ($existingGuarantee && $existingGuarantee->id_photo) {
                        Storage::disk("public")->delete($existingGuarantee->id_photo);
                    }
                    $guaranteeData["id_photo"] = null;
                }

                if ($existingGuarantee) {
                    $existingGuarantee->update($guaranteeData);
                } else {
                    $guaranteeData["rental_id"] = $rental->id;
                    $guaranteeData["deposit_amount"] = $guaranteeType === 'deposit' ? (float) ($data["guarantee_deposit"] ?? 0) : 0;
                    $guaranteeData["status"] = "held";
                    \App\Models\Guarantee::create($guaranteeData);
                }
            }

            $rental->refresh()->load("payments");
            $this->rentalService->recalculatePaymentStatus($rental);

            if (!empty($data["return_due_date"])) {
                $rental->update(["return_due_date" => Carbon::parse($data["return_due_date"])->toDateString()]);
            }
        });

        $rental->refresh();
        $newValues = app(\App\Services\RentalService::class)->rentalSnapshotWhitelisted($rental);
        ActivityLog::create([
            'user_id' => auth()->id(),
            'branch_id' => auth()->user()->branch_id,
            'action' => 'update_rental',
            'model_type' => Rental::class,
            'model_id' => $rental->id,
            'description' => \App\Services\ActivityLogFormatter::format($oldValues, $newValues, Rental::class, $rental->id, 'update_rental'),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route("rentals.show", $rental)->with("success", "Penyewaan berhasil diperbarui!");
    }

    public function destroy(Rental $rental)
    {
        $user = Auth::user();
        if (!$user || (!$user->isSuperAdmin() && !$user->isAdminToko())) abort(403);
        abort_unless($user->isSuperAdmin() || $user->canAccessBranch((int) $rental->branch_id), 403);

        // Rental 'waiting' + 'unpaid' boleh dihapus (koreksi human error / ganti size sebelum serah terima)
        $isDeletableWaiting = $rental->rental_status === Rental::STATUS_WAITING
            && $rental->payment_status === Rental::PAYMENT_UNPAID;

        if ($isDeletableWaiting) {
            // Diizinkan — akan lanjut ke proses hapus di bawah
        } elseif (in_array($rental->rental_status, ['active', 'overdue'], true)) {
            return back()->with('error', 'Penyewaan tidak dapat dihapus karena sudah dalam status aktif/berjalan.');
        } elseif ($rental->rental_status === Rental::STATUS_WAITING && $rental->payment_status !== Rental::PAYMENT_UNPAID) {
            return back()->with('error', 'Penyewaan tidak dapat dihapus karena sudah ada pembayaran. Gunakan fitur Batalkan terlebih dahulu.');
        }

        $oldValues = [
            'invoice_number' => $rental->invoice_number,
            'customer' => optional($rental->customer)->name,
            'total_amount' => (float) $rental->total_amount,
            'rental_status' => $rental->rental_status,
            'payment_status' => $rental->payment_status,
        ];
        $rentalId = $rental->id;

        DB::transaction(function () use ($rental) {
            // Kembalikan stok produk jika rental belum serah terima (waiting/cancelled)
            if (in_array($rental->rental_status, [Rental::STATUS_WAITING, Rental::STATUS_CANCELLED], true)) {
                foreach ($rental->items()->get() as $item) {
                    if ($item->product_id) {
                        $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                        if ($product) {
                            $product->incrementSizeStock($item->product_size, (int) $item->quantity);
                        }
                    }
                }
            }

            $rental->payments()->delete();
            $rental->guarantees()->delete();
            $rental->items()->delete();
            $rental->returnRecord()->delete();
            $rental->delete();
        });

        ActivityLog::create([
            'user_id' => auth()->id(),
            'branch_id' => $user->branch_id,
            'action' => 'rental_deleted',
            'model_type' => Rental::class,
            'model_id' => $rentalId,
            'description' => \App\Services\ActivityLogFormatter::format($oldValues, null, Rental::class, $rentalId, 'rental_deleted'),
            'old_values' => $oldValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route("rentals.index")->with("success", "Penyewaan berhasil dihapus!");
    }

    public function scanPage()
    {
        return view("rentals.scan");
    }

    public function scanQr(string $invoice)
    {
        $raw = trim($invoice);
        $code = strtoupper($raw);
        $rental = null;

        // Invoice produksi: INV + Ymd(8) + branchCode(min 2, bisa 3+ utk id>=100) + sequence(4)
        // -> 14 digit utk cabang <100, 15+ digit utk cabang >=100 (lihat generateInvoiceNumber).
        if (preg_match("/^(INV|RCPT-INV)\d{12,20}$/", $code)) {
            $rental = Rental::with(["customer", "branch", "items.product.category", "guarantees", "returnedBy", "createdBy"])->where("invoice_number", $code)->first();
            if (!$rental) {
                return view("rentals.scan-result", ["rental" => null, "error" => "Penyewaan dengan nomor invoice '{$code}' tidak ditemukan dalam sistem."]);
            }
        } elseif (preg_match("/^\d+$/", $raw)) {
            $rentalId = (int) $raw;
            $rental = Rental::with(["customer", "branch", "items.product.category", "guarantees", "returnedBy", "createdBy"])->find($rentalId);
            if (!$rental) {
                return view("rentals.scan-result", ["rental" => null, "error" => "Penyewaan dengan ID '{$rentalId}' tidak ditemukan dalam sistem."]);
            }
        } else {
            return view("rentals.scan-result", ["rental" => null, "error" => "Format QR Code tidak valid. Format yang diterima: INV2026070310014, RCPT-INV2026070310014, atau ID rental numerik (contoh: 93)"]);
        }

        $this->authorize('view', $rental);
        $this->loadVisibleActivityLogs($rental);

        return view("rentals.scan-result", compact("rental"));
    }

    public function processPayment(Request $request, Rental $rental)
    {
        try {
            $this->rentalService->processPayment($rental, $request->validateWithBag('payment', [
                "amount" => "required|numeric|min:0.01",
                "method" => "required|string",
                "payment_type" => ["nullable", "in:rental,late_fee,damage_fee"],
                "reference_number" => "nullable|string",
                "notes" => "nullable|string",
            ]));
            $rental->refresh();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Pembayaran berhasil diproses!']);
            }

            return back()->with("success", "Pembayaran berhasil diproses!");
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['payment' => [$e->getMessage()]],
                ], 422);
            }

            return back()
                ->withErrors(['payment' => $e->getMessage()], 'payment')
                ->withInput();
        }
    }

    public function processReturn(Request $request, Rental $rental)
    {
        $pendingItems = $rental->items()->where('is_returned', false)->count();

        $validated = $request->validate([
            "items" => ["required", "array", "size:{$pendingItems}"],
            "items.*.rental_item_id" => ["required", "integer", "distinct", \Illuminate\Validation\Rule::exists('rental_items', 'id')->where('rental_id', $rental->id)],
            "items.*.condition" => ["required", "in:good,baik,rusak_ringan,rusak_berat,lost,hilang"],
            "items.*.damage_fee" => ["nullable", "numeric", "min:0"],
            "items.*.notes" => ["nullable", "string"],
        ]);

        // Samakan dengan confirmReturnAjax: alias Inggris dipetakan ke nilai enum
        // return_condition ('baik','rusak_ringan','rusak_berat','hilang') supaya
        // kondisi 'lost' tidak jatuh ke cabang "baik" (stok kembali padahal hilang)
        // dan tidak memicu error data truncated.
        $conditionMap = ["good" => "baik", "lost" => "hilang"];
        $items = [];

        foreach ($validated["items"] as $row) {
            $condition = $conditionMap[$row["condition"]] ?? $row["condition"];

            if (in_array($condition, ["rusak_ringan", "rusak_berat", "hilang"], true)
                && (float) ($row["damage_fee"] ?? 0) <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "items" => "Denda harus diisi untuk barang yang rusak atau hilang.",
                ]);
            }

            $items[] = [
                "rental_item_id" => (int) $row["rental_item_id"],
                "condition"       => $condition,
                "damage_fee"      => (float) ($row["damage_fee"] ?? 0),
                "notes"           => $row["notes"] ?? null,
            ];
        }

        $this->rentalService->processReturn($rental, ["items" => $items]);
        return back()->with("success", "Pengembalian berhasil diproses!");
    }

public function cancel(Rental $rental)
    {
        DB::transaction(function () use ($rental) {
            $rental = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();
            
            // Hanya boleh dibatalkan jika status 'waiting'
            if ($rental->rental_status !== Rental::STATUS_WAITING) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'rental_status' => 'Penyewaan hanya dapat dibatalkan saat status "Menunggu". Status saat ini: ' . $rental->status_label
                ]);
            }
            
            if (in_array($rental->rental_status, [Rental::STATUS_CANCELLED, Rental::STATUS_RETURNED], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['rental_status' => 'Penyewaan sudah selesai atau dibatalkan.']);
            }
            $rental->update(["rental_status" => Rental::STATUS_CANCELLED]);
            
            // Restore stock for items since rental was never started
            foreach ($rental->items as $item) {
                if (!$item->is_returned) {
                    $product = Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                    $product->incrementSizeStock($item->product_size, $item->quantity);
                }
            }
        });
        return back()->with("success", "Penyewaan berhasil dibatalkan!");
    }

    public function cancelReturn(Rental $rental)
    {
        DB::transaction(function () use ($rental) {
            $rental = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();
            if ($rental->rental_status !== Rental::STATUS_RETURNED) {
                throw \Illuminate\Validation\ValidationException::withMessages(['rental_status' => 'Penyewaan belum dikembalikan.']);
            }
            /** @var \Illuminate\Support\Carbon $returnDueDate */
            $returnDueDate = $rental->return_due_date;
            $newStatus = $returnDueDate->lt(today()) ? Rental::STATUS_OVERDUE : Rental::STATUS_ACTIVE;
            $rental->update([
                "rental_status" => $newStatus,
                "returned_at" => null,
                "actual_return_date" => null,
                "returned_by" => null,
                "overdue_days" => 0,
                "late_fee" => 0,
                "fine_amount" => 0,
            ]);
            foreach ($rental->items as $item) {
                $product = Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                $maintenance = \App\Models\ProductStockMaintenance::where('rental_item_id', $item->id)
                    ->where('status', 'maintenance')->first();
                if ($maintenance) {
                    $ps = \App\Models\ProductSize::whereKey($maintenance->product_size_id)->lockForUpdate()->first();
                    if ($ps) {
                        if ($maintenance->condition === 'hilang') {
                            $ps->increment('stock_total', $maintenance->quantity);
                            $ps->decrement('stock_lost', $maintenance->quantity);
                            if ((int) $ps->stock_lost < 0) $ps->update(['stock_lost' => 0]);
                        } else {
                            $ps->decrement('stock_maintenance', $maintenance->quantity);
                            if ((int) $ps->stock_maintenance < 0) $ps->update(['stock_maintenance' => 0]);
                        }
                        $ps->save();
                    }
                    $maintenance->delete();
                    $product->load('sizes');
                    $product->stock_available = $product->available_stock;
                    $product->stock_total = $product->total_stock;
                    $product->save();
                } else {
                    $product->decrementSizeStock($item->product_size, $item->quantity);
                }
                $item->update(["is_returned" => false]);
            }

            // Pengembalian dibatalkan: status pembayaran/denda dihitung ulang
            // (fine_amount sudah direset -> fine_status kembali "none") dan riwayat
            // rental selesai yang tadi terbentuk harus ikut ditarik.
            $this->rentalService->recalculatePaymentStatus($rental->fresh());
            $rental->history()->delete();
        });
        return back()->with("success", "Pengembalian berhasil dibatalkan!");
    }

    public function exportPdf(Rental $rental)
    {
        $this->authorize('view', $rental);

        $rental->loadMissing(['customer', 'branch', 'items.product.category', 'createdBy']);
        $pdf = Pdf::loadView("rentals.pdf", compact("rental"));
        return $pdf->download($rental->invoice_number . ".pdf");
    }

    public function thermalPrint(Rental $rental)
    {
        $this->authorize('view', $rental);

        $receipt = app(RentalReceiptService::class)->getReceiptPayload($rental);

        return view('rentals.receipt', compact('rental', 'receipt'));
    }

    public function whatsapp(Rental $rental)
    {
        $this->authorize('view', $rental);

        $message = urlencode("Halo " . optional($rental->customer)->name . ",\n\nTerima kasih telah menggunakan layanan SewaJas.\n\nBerikut adalah Invoice Anda.\n\nNomor Invoice: {$rental->invoice_number}\n\nSilakan unduh PDF melalui tautan berikut:\n" . route("rentals.pdf", $rental) . "\n\nApabila ada pertanyaan, silakan hubungi kami.\nTerima kasih.");
        $waUrl = "https://wa.me/" . optional($rental->customer)->phone . "?text={$message}";
        return redirect()->away($waUrl);
    }

    public function sendReminder(Rental $rental)
    {
        $this->authorize('view', $rental);

        $message = urlencode("Halo " . optional($rental->customer)->name . ",\n\nReminder untuk pengembalian penyewaan:\nInvoice: {$rental->invoice_number}\nJatuh Tempo: " . optional($rental->return_due_date)->format('d/m/Y') . "");
        $waUrl = "https://wa.me/" . optional($rental->customer)->phone . "?text={$message}";
        return redirect()->away($waUrl);
    }

public function confirmReturnAjax(Request $request, Rental $rental)
    {
        $validated = $request->validate([
            "payment_status" => ["required", "in:unpaid,partial,paid"],
            "items" => ["required", "array", "size:" . $rental->items()->count()],
            "items.*.id" => ["required", "integer", "distinct", \Illuminate\Validation\Rule::exists('rental_items', 'id')->where('rental_id', $rental->id)],
            "items.*.condition" => ["required", "in:good,baik,rusak_ringan,rusak_berat,lost,hilang"],
            "items.*.notes" => ["nullable", "string"],
            "items.*.damage_fee" => ["nullable", "numeric", "min:0"],
        ]);

        // Validasi: Pembayaran harus lunas sebelum pengembalian
        if ($rental->payment_status !== Rental::PAYMENT_PAID) {
            return response()->json([
                "message" => "Penyewaan belum dapat diselesaikan karena pembayaran belum lunas. Silakan lunasi pembayaran terlebih dahulu."
            ], 422);
        }

        $itemsPayload = $validated["items"] ?? [];
        foreach ($itemsPayload as $row) {
            if (empty($row["condition"])) {
                return response()->json(["message" => "Masih ada barang yang belum diperiksa."], 422);
            }
            if (in_array($row["condition"], ["rusak_ringan", "rusak_berat", "lost", "hilang"]) && empty($row["damage_fee"])) {
                return response()->json(["message" => "Denda harus diisi untuk barang yang rusak atau hilang."], 422);
            }
        }

        $processPayload = [
            "items" => array_map(function ($row) {
                $conditionMap = ["good" => "baik", "rusak_ringan" => "rusak_ringan", "rusak_berat" => "rusak_berat", "lost" => "hilang"];
                return [
                    "rental_item_id" => (int) $row["id"],
                    "condition" => $conditionMap[$row["condition"]] ?? $row["condition"],
                    "notes" => $row["notes"] ?? null,
                    "damage_fee" => $row["damage_fee"] ?? 0,
                ];
            }, $itemsPayload)
        ];

        $this->rentalService->processReturn($rental, $processPayload);
        $rental->refresh();
        $rental->load(["customer", "branch", "createdBy", "items.product.category", "returnedBy", "payments"]);
        $this->loadVisibleActivityLogs($rental);
        /** @var \Illuminate\Support\Carbon|null $returnDueDate */
        $returnDueDate = $rental->return_due_date;

        return response()->json(["success" => true, "rental" => [
            "id" => $rental->id,
            "invoice_number" => $rental->invoice_number,
            "rental_status" => $rental->rental_status,
            "payment_status" => $rental->payment_status,
            "fine_status" => $rental->fine_status,
            "fine_amount" => (float) $rental->fine_amount,
            "fine_paid_amount" => (float) $rental->fine_paid_amount,
            "returned_at" => $rental->returned_at?->format("d M Y H:i"),
            "returned_by" => $rental->returnedBy?->name ?? optional($rental->createdBy)->name,
            "total_amount" => (float) $rental->total_amount,
            "paid_amount" => (float) $rental->paid_amount,
            "remaining_amount" => (float) $rental->remaining_amount,
            "late_fee" => (float) $rental->late_fee,
            "overdue_days" => (int) $rental->overdue_days,
            "subtotal" => $rental->subtotal ?? null,
            "discount" => $rental->discount ?? null,
            "deposit" => (float) ($rental->guarantees->where('type', 'deposit')->sum('deposit_amount') ?? 0),
            "return_due_date" => $returnDueDate?->format("d/m/Y"),
            "customer" => ["name" => optional($rental->customer)->name, "phone" => optional($rental->customer)->phone],
            "items" => $rental->items->map(function ($item) {
                return [
                    "id" => $item->id,
                    "product_name" => optional($item->product)->name,
                    "size" => $item->product_size,
                    "quantity" => $item->quantity,
                    "price" => (float) $item->price_per_day,
                    "subtotal" => (float) $item->subtotal,
                    "photo" => $item->product && $item->product->photo ? asset("storage/".$item->product->photo) : null,
                    "return_condition" => $item->return_condition,
                    "return_notes" => $item->return_notes,
                    "is_returned" => (bool) $item->is_returned,
                    "damage_fee" => (float) ($item->damage_fee ?? 0),
                ];
            }),
            "activity_logs" => $rental->activityLogs->take(10)->map(function ($log) {
                return [
                    "id" => $log->id,
                    "description" => $log->description,
                    "user" => optional($log->user)->name,
                    "created_at" => optional($log->created_at)->format("d M Y H:i"),
                ];
            }),
        ]]);
    }

    public function paymentUpdate(Request $request, Rental $rental, \App\Models\Payment $payment)
    {
        $this->rentalService->updatePayment($rental, $payment, $request->validate([
            "amount" => "required|numeric|min:0",
            "method" => "required|string",
            "reference_number" => "nullable|string",
            "notes" => "nullable|string",
        ]));
        return back()->with("success", "Pembayaran berhasil diperbarui!");
    }

    public function paymentDestroy(Rental $rental, \App\Models\Payment $payment)
    {
        $this->rentalService->deletePayment($rental, $payment);
        return back()->with("success", "Pembayaran berhasil dihapus!");
    }

    public function paymentRefund(Rental $rental, \App\Models\Payment $payment)
    {
        $this->rentalService->refundPayment($rental, $payment);
        return back()->with("success", "Refund berhasil diproses!");
    }

    public function paymentVoid(Rental $rental, \App\Models\Payment $payment)
    {
        $this->rentalService->voidPayment($rental, $payment);
        return back()->with("success", "Void pembayaran berhasil diproses!");
    }

    public function markRefundGiven(Request $request, Rental $rental)
    {
        $rental->refresh();
        $this->rentalService->recalculatePaymentStatus($rental);
        $rental->refresh();

        $maxRefund = (float) ($rental->change_amount ?? 0);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $maxRefund,
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->rentalService->recordRefund($rental, (float) $validated['amount'], $validated['notes'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $rental->refresh();
        $rental->load(['customer', 'branch', 'guarantees', 'payments', 'items.product']);
        $this->loadVisibleActivityLogs($rental);

        return response()->json([
            'success' => true,
            'message' => 'Kembalian berhasil dicatat!',
            'rental' => [
                'id' => $rental->id,
                'invoice_number' => $rental->invoice_number,
                'change_amount' => (float) $rental->change_amount,
                'overpayment' => (float) $rental->change_amount,
                'refund_given' => (float) abs($rental->payments()->where('type', 'refund')->sum('amount')),
                'payment_status' => $rental->payment_status,
                'fine_status' => $rental->fine_status,
                'customer' => ['name' => optional($rental->customer)->name, 'phone' => optional($rental->customer)->phone],
                'branch' => ['name' => optional($rental->branch)->name],
                'guarantees' => $rental->guarantees->map(fn($g) => ['id' => $g->id, 'type' => $g->type, 'status' => $g->status]),
                'payments' => $rental->payments->map(fn($p) => ['id' => $p->id, 'payment_number' => $p->payment_number, 'amount' => (float) $p->amount, 'method' => $p->method, 'paid_at' => $p->paid_at?->format('d/m/Y H:i')]),
                'activity_logs' => $rental->activityLogs->take(10)->map(fn($log) => ['id' => $log->id, 'description' => $log->description, 'user' => optional($log->user)->name, 'created_at' => $log->created_at?->format('d M Y H:i')]),
                'items' => $rental->items->map(fn($item) => ['id' => $item->id, 'product_name' => optional($item->product)->name, 'size' => $item->product_size, 'quantity' => $item->quantity, 'price' => (float) $item->price_per_day]),
            ],
        ]);
    }

    public function handoverRental(Request $request, Rental $rental)
    {
        if ($rental->rental_status !== Rental::STATUS_WAITING) {
            return response()->json([
                'success' => false,
                'message' => 'Rental ini sudah tidak dalam status Booking. Serah terima hanya dapat dilakukan untuk rental dengan status Booking.',
            ], 422);
        }

        $updateData = [
            'rental_status' => Rental::STATUS_ACTIVE,
        ];

        $rentalService = app(\App\Services\RentalService::class);
        $oldValues = $rentalService->rentalSnapshotWhitelisted($rental);

        DB::transaction(function () use ($rental, $oldValues, $updateData, $rentalService) {
            $rental->update($updateData);
            $rental->refresh();

            $newValues = $rentalService->rentalSnapshotWhitelisted($rental);

            $description = \App\Services\ActivityLogFormatter::format(
                $oldValues,
                $newValues,
                Rental::class,
                $rental->id,
                'handover_rental'
            );

            ActivityLog::create([
                'user_id'     => auth()->id(),
                'branch_id'   => auth()->user()->branch_id,
                'action'      => 'handover_rental',
                'model_type'  => Rental::class,
                'model_id'    => $rental->id,
                'description' => $description,
                'old_values'  => $oldValues,
                'new_values'  => $newValues,
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
            ]);
        });

        $rental->refresh();
        $rental->load(['customer', 'branch', 'createdBy', 'items.product']);
        $this->loadVisibleActivityLogs($rental);

        // Poin serah: diberikan saat serah-terima fisik (waiting -> active), bukan saat pembayaran.
        // Guard di addSalesPoints memastikan idempotent bila sudah pernah diberi via pembayaran.
        $rentalService->addSalesPoints($rental, 'serah');

        return response()->json([
            'success' => true,
            'message' => 'Serah terima berhasil! Rental sekarang aktif.',
            'rental'  => [
                'id'                     => $rental->id,
                'invoice_number'         => $rental->invoice_number,
                'rental_status'          => $rental->rental_status,
                'payment_status'         => $rental->payment_status,
                'returned_at'            => $rental->returned_at?->format('d M Y H:i'),
                'returned_by'            => optional($rental->returnedBy)->name ?? optional($rental->createdBy)->name,
                'total_amount'           => (float) $rental->total_amount,
                'paid_amount'            => (float) $rental->paid_amount,
                'remaining_amount'       => (float) $rental->remaining_amount,
                'late_fee'               => (float) $rental->late_fee,
                'overdue_days'           => (int) $rental->overdue_days,
                'customer'               => ['name' => optional($rental->customer)->name, 'phone' => optional($rental->customer)->phone],
                'branch'                 => ['name' => optional($rental->branch)->name],
                'items'                  => $rental->items->map(fn($item) => [
                    'id'           => $item->id,
                    'product_name' => optional($item->product)->name,
                    'size'         => $item->product_size,
                    'quantity'     => $item->quantity,
                    'price'        => (float) $item->price_per_day,
                ]),
                'rental_date'            => optional($rental->rental_date)->format('d/m/Y'),
                'return_due_date'        => optional($rental->return_due_date)->format('d/m/Y'),
                'activity_logs'          => $rental->activityLogs->take(10)->map(fn($log) => [
                    'id'          => $log->id,
                    'description' => $log->description,
                    'user'        => optional($log->user)->name,
                    'created_at'  => $log->created_at?->format('d M Y H:i'),
                ]),
            ],
        ]);
    }

    public function updateStatus(Request $request, Rental $rental)
    {
        $validated = $request->validate([
            "rental_status" => ["nullable", "in:active,returned,overdue"],
            "payment_status" => ["nullable", "in:unpaid,partial,paid"],
        ]);

        DB::transaction(function () use ($rental, $validated) {
            $rental = Rental::whereKey($rental->id)->lockForUpdate()->firstOrFail();
            if (in_array($rental->rental_status, [Rental::STATUS_WAITING, Rental::STATUS_CANCELLED], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['rental_status' => 'Status penyewaan tidak dapat diubah melalui aksi ini.']);
            }
            $oldValues = $rental->getAttributes();
            $updateData = [];
            $now = now();

            if (isset($validated["rental_status"])) {
                $updateData["rental_status"] = $validated["rental_status"];

                if ($validated["rental_status"] === "returned") {
                    // Validasi: Pembayaran harus lunas sebelum pengembalian
                    if ($rental->payment_status !== Rental::PAYMENT_PAID) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'payment_status' => 'Penyewaan belum dapat diselesaikan karena pembayaran belum lunas. Silakan lunasi pembayaran terlebih dahulu.'
                        ]);
                    }
                    $updateData["returned_at"] = $now;
                    $updateData["actual_return_date"] = $now->toDateString();
                    $updateData["returned_by"] = auth()->id();
                    $overdueDays = max(0, $now->diffInDays($rental->return_due_date, false) * -1);
                    $updateData["overdue_days"] = $overdueDays;
                    $lateFeePerDay = config("app.late_fee_per_day", 0);
                    $updateData["late_fee"] = (float) round($overdueDays * $lateFeePerDay);
                    $updateData["fine_amount"] = $updateData["late_fee"];
                    $updateData["fine_status"] = $updateData["fine_amount"] > 0
                        ? Rental::FINE_UNPAID
                        : Rental::FINE_NONE;

                    foreach ($rental->items as $item) {
                        if ($item->is_returned) {
                            continue;
                        }
                        $product = Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                        $product->incrementSizeStock($item->product_size, $item->quantity);
                        $item->update(["is_returned" => true]);
                    }
                } else {
                    $updateData["returned_at"] = null;
                    $updateData["actual_return_date"] = null;
                    $updateData["returned_by"] = null;
                    // Kolom overdue_days bertipe NOT NULL (default 0); mengirim null
                    // memicu Integrity constraint violation 1048.
                    $updateData["overdue_days"] = 0;
                    $updateData["late_fee"] = 0;
                    $updateData["fine_amount"] = 0;
                    $updateData["fine_status"] = Rental::FINE_NONE;
                    $updateData["fine_paid_amount"] = 0;

                    if ($rental->rental_status === 'returned') {
                        foreach ($rental->items as $item) {
                            $product = Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                            $product->decrementSizeStock($item->product_size, $item->quantity);
                            $item->update(["is_returned" => false]);
                        }
                    }
                }
            }

            if (!empty($updateData)) {
                $rental->update($updateData);
                $description = \App\Services\ActivityLogFormatter::format(
                    $oldValues,
                    $updateData,
                    Rental::class,
                    $rental->id,
                    'update_status'
                );
                ActivityLog::create([
                    "user_id" => auth()->id(),
                    "branch_id" => auth()->user()->branch_id,
                    "action" => "update_status",
                    "model_type" => Rental::class,
                    "model_id" => $rental->id,
                    "description" => $description,
                    "old_values" => $oldValues,
                    "new_values" => $updateData,
                    "ip_address" => request()->ip(),
                    "user_agent" => request()->userAgent(),
                ]);
            }
        });

        $rental->refresh();
        if (isset($validated["rental_status"])) {
            $this->rentalService->recalculatePaymentStatus($rental);
            $rental->refresh();
        }
        $rental->load(["customer", "branch", "createdBy", "items.product"]);
        $this->loadVisibleActivityLogs($rental);

        // Poin kembali hanya untuk transisi status menjadi 'returned'
        // (sebelumnya dipanggil di semua pemanggilan updateStatus).
        if (($validated["rental_status"] ?? null) === "returned") {
            $this->rentalService->addSalesPoints($rental, 'kembali');
        }

        return response()->json(["success" => true, "rental" => [
            "id" => $rental->id,
            "invoice_number" => $rental->invoice_number,
            "rental_status" => $rental->rental_status,
            "payment_status" => $rental->payment_status,
            "returned_at" => $rental->returned_at?->format("d M Y H:i"),
            "returned_by" => $rental->returnedBy?->name ?? optional($rental->createdBy)->name,
            "total_amount" => (float) $rental->total_amount,
            "paid_amount" => (float) $rental->paid_amount,
            "remaining_amount" => (float) $rental->remaining_amount,
            "late_fee" => (float) $rental->late_fee,
            "overdue_days" => (int) $rental->overdue_days,
            "customer" => ["name" => optional($rental->customer)->name, "phone" => optional($rental->customer)->phone],
            "branch" => ["name" => optional($rental->branch)->name],
            "items" => $rental->items->map(fn($item) => [
                "id" => $item->id,
                "product_name" => optional($item->product)->name,
                "size" => $item->product_size,
                "quantity" => $item->quantity,
                "price" => (float) $item->price_per_day,
            ]),
            "rental_date" => optional($rental->rental_date)->format("d/m/Y"),
            "return_due_date" => optional($rental->return_due_date)->format("d/m/Y"),
            "activity_logs" => $rental->activityLogs->take(10)->map(fn($log) => [
                "id" => $log->id,
                "description" => $log->description,
                "user" => optional($log->user)->name,
                "created_at" => optional($log->created_at)->format("d M Y H:i"),
            ]),
        ]]);
    }
}
