<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Auth::setUser(App\Models\User::findOrFail(1));
$rental=App\Models\Rental::findOrFail(17);
$response=app(App\Http\Controllers\RentalController::class)->exportPdf($rental);
echo 'Status: '.$response->getStatusCode().' Bytes: '.strlen($response->getContent()).PHP_EOL;
file_put_contents(__DIR__.'/qa-results/cli-invoice.pdf',$response->getContent());
