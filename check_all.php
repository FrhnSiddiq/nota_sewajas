<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check WhatsApp campaign (campaign 2)
echo "=== Campaign 2 (WhatsApp) Logs ===\n";
$logs = \App\Models\Broadcast\BroadcastLog::where('campaign_id', 2)->get();
foreach ($logs as $log) {
    echo "ID: {$log->id}, Recipient Type: {$log->recipient_type}, Recipient ID: {$log->recipient_id}, Phone: {$log->phone}, Status: {$log->status}, Provider: {$log->provider}\n";
}

// Check In-App campaign (campaign 4)
echo "\n=== Campaign 4 (In-App) Logs ===\n";
$logs = \App\Models\Broadcast\BroadcastLog::where('campaign_id', 4)->get();
foreach ($logs as $log) {
    echo "ID: {$log->id}, Recipient Type: {$log->recipient_type}, Recipient ID: {$log->recipient_id}, Phone: {$log->phone}, Status: {$log->status}, Provider: {$log->provider}\n";
}

// Check notifications
echo "\n=== Notifications ===\n";
$notifications = \App\Models\Notification::all();
foreach ($notifications as $n) {
    echo "ID: {$n->id}, Notifiable Type: {$n->notifiable_type}, Notifiable ID: {$n->notifiable_id}, Title: {$n->title}, Message: {$n->message}, Branch ID: {$n->branch_id}\n";
}