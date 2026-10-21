<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$notifications = \App\Models\Notification::where('title', 'Broadcast: Test InApp Campaign')->get();
foreach ($notifications as $n) {
    echo "ID: {$n->id}, Notifiable Type: {$n->notifiable_type}, Notifiable ID: {$n->notifiable_id}, Title: {$n->title}, Message: {$n->message}, Branch ID: {$n->branch_id}\n";
}