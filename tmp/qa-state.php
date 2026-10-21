<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$f=json_decode(file_get_contents(__DIR__.'/qa-fixture.json'),true);
$ids=array_unique(array_merge($f['rentals']??[],[$f['rental']]));
echo json_encode(['rentals'=>App\Models\Rental::with(['items','payments','guarantees'])->whereIn('id',$ids)->get(), 'product'=>App\Models\Product::with('sizes')->find($f['product']), 'category'=>App\Models\Category::find($f['category']), 'branch'=>App\Models\Branch::find($f['branch'])],JSON_PRETTY_PRINT);
