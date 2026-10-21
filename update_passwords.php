<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$hash = '$2y$12$Oz5JibHnrNYtHpCGEkaP6uutSlIkogrHPMW6CWUiuRM0RWwINFkui';

\App\Models\User::whereIn('role', ['admin_toko', 'sales', 'super_admin'])->update(['password' => $hash]);
echo "Passwords updated\n";