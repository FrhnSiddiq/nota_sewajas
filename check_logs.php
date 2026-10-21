<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$logs = \App\Models\Broadcast\BroadcastLog::where('campaign_id', 2)->get();
foreach ($logs as $log) {
    echo "ID: {$log->id}, Recipient Type: {$log->recipient_type}, Recipient ID: {$log->recipient_id}, Phone: {$log->phone}, Status: {$log->status}, Provider: {$log->provider}\n";
}