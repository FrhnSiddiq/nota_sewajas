<?php

namespace App\Console\Commands;

use App\Models\Rental;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteRentalCommand extends Command
{
    protected $signature = 'delete:rental {invoice : Rental invoice_number} {--path=storage/backups/ : Where backups are stored} {--force=false : Delete without interactive confirmation}';
    protected $description = 'Safely delete rental (and rental_items) after ensuring a backup exists.';


    public function handle(): int
    {
        $invoice = (string) $this->argument('invoice');
        $basePath = (string) $this->option('path');
        if ($basePath === '') {
            $basePath = 'storage/backups/';
        }
        $basePath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR;

        // Load rental for preview + safety checks
        $rental = Rental::query()
            ->with(['items', 'payments'])
            ->where('invoice_number', $invoice)
            ->first();

        if (!$rental) {
            $this->error("Rental not found for invoice: {$invoice}");
            return 1;
        }

        // Safety check: backup must exist
        $pattern = $rental->invoice_number . '_backup_*.json';
        $globPath = base_path($basePath . $pattern);
        $matches = glob($globPath) ?: [];

        if (empty($matches)) {
            $this->error('Backup file not found. Aborting delete.');
            $this->line('Expected backup pattern: ' . ($basePath . $pattern));
            return 1;
        }

        // Preview what will be deleted
        $itemsCount = $rental->items()->count();
        $paymentsCount = $rental->payments()->count();

        $this->newLine();
        $this->info('Preview delete');
        $this->table(
            ['field', 'value'],
            [
                ['invoice_number', $rental->invoice_number],
                ['rental_status', $rental->rental_status],
                ['total_amount', (string) $rental->total_amount],
                ['rental_items_count', (string) $itemsCount],
                ['payments_count', (string) $paymentsCount],
                ['deleted?(trashed)', 'no'],
            ]
        );

        // Robustly interpret --force.
        // In this codebase, signature uses `{--force=false ...}` so --force should arrive as "true"/"false" or boolean.
        $forceOption = $this->option('force');
        $force = $forceOption === true || $forceOption === 'true' || $forceOption === 1 || $forceOption === '1';

        if (!$force) {
            $this->warn('Aborted: please run with --force to delete without interactive confirmation.');
            return 1;
        }



        // Delete inside transaction and verify results
        $rentalId = $rental->id;

        DB::transaction(function () use ($rentalId, $invoice) {

            // Reload inside transaction (avoid stale model instances)
            $r = Rental::query()->with(['items'])
                ->where('id', $rentalId)
                ->first();

            if (!$r) {
                throw new \RuntimeException("Rental id {$rentalId} not found during delete transaction for invoice {$invoice}");
            }

            // Restore stock only if rental_status is NOT returned
            // (use original rental_status for safety)
            if ($r->rental_status !== 'returned') {
                foreach ($r->items as $item) {
                    $product = $item->product->fresh();
                    $newStock = $product->stock_available + $item->quantity;
                    $product->update([
                        'stock_available' => $newStock,
                        'status' => ($product->status === 'rented' && $newStock > 0) ? 'available' : $product->status,
                    ]);
                }
            }

            // Delete items first
            $deletedItems = $r->items()->delete();

            // Delete rental-related polymorphic records explicitly
            \App\Models\StatusHistory::where('model_type', \App\Models\Rental::class)->where('model_id', $r->id)->delete();
            \App\Models\ActivityLog::where('model_type', \App\Models\Rental::class)->where('model_id', $r->id)->delete();
            \App\Models\Laundry::where('transaksi_id', $r->id)->delete();

            $r->delete();

            $this->line('--- Delete summary (inside transaction) ---');
            $this->line('items deleted: ' . (string) $deletedItems);
        });

        // Verify rental is actually gone
        $stillExists = Rental::query()->where('invoice_number', $invoice)->count();

        $backupFile = end($matches);
        $this->newLine();

        if ($stillExists > 0) {
            $this->error("Delete FAILED: rental still exists for invoice {$invoice} (count={$stillExists}). Backup: {$backupFile}");
            return 1;
        }

        $this->info("Rental {$invoice} berhasil dihapus, backup tersimpan di: {$backupFile}");
        return 0;
    }
}

