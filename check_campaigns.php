<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Broadcast\BroadcastCampaign;
use App\Models\Broadcast\BroadcastLog;

$campaigns = BroadcastCampaign::latest()->take(3)->get();
foreach($campaigns as $camp) {
    echo "Campaign ID: {$camp->id}, Name: {$camp->name}, Channel: " . json_encode($camp->channels) . ", Status: {$camp->status}, Total Target: {$camp->total_target}\n";
    $logs = BroadcastLog::where('campaign_id', $camp->id)->get();
    echo "  Logs count: " . $logs->count() . "\n";
    foreach($logs as $log) {
        echo "    - Log ID: {$log->id}, Recipient: {$log->recipient_type}#{$log->recipient_id}, Status: {$log->status}, Provider: {$log->provider}\n";
    }
}