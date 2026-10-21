<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$p = App\Models\Product::find(11);
$p->delete();
echo 'deleted_at: ' . ($p->fresh()->deleted_at ?? 'NULL') . "\n";