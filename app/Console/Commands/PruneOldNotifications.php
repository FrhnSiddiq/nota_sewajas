<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class PruneOldNotifications extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Delete notifications older than 30 days in batches';

    public function handle(): int
    {
        $cutoff = now()->subDays(30);
        $deleted = 0;
        $batchSize = 500;

        do {
            $notificationIds = Notification::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($notificationIds->isEmpty()) {
                break;
            }

            $deletedInBatch = Notification::query()
                ->whereIn('id', $notificationIds)
                ->where('created_at', '<', $cutoff)
                ->delete();

            $deleted += $deletedInBatch;
        } while ($deletedInBatch > 0);

        $this->info("Deleted {$deleted} expired notifications.");

        return self::SUCCESS;
    }
}
