<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$result = ['base_path'=>base_path(),'php'=>PHP_VERSION,'database'=>DB::connection()->getDatabaseName(),'version'=>DB::selectOne('select version() as version')->version];
$result['test_database_exists'] = DB::select("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'rental_test'");
$result['counts'] = [];
foreach (['users','branches','customers','products','product_sizes','rentals','payments','notifications','activity_logs','broadcast_campaigns'] as $table) $result['counts'][$table] = DB::table($table)->count();
$result['users'] = App\Models\User::get(['id','email','role','branch_id','is_active'])->map(fn($u)=>array_merge($u->toArray(),['accessible_branch_ids'=>$u->accessible_branch_ids]));
$result['branches'] = DB::table('branches')->get(['id','name','is_active']);
$result['rentals'] = DB::table('rentals')->get(['id','branch_id','created_by','customer_id','invoice_number','rental_status','payment_status']);
$result['products'] = App\Models\Product::with('sizes')->get(['id','branch_id','category_id','name','stock_total','stock_available','status']);
$result['categories'] = DB::table('categories')->get(['id','name','sort_order']);
file_put_contents(__DIR__.'/qa-discovery.json', json_encode($result, JSON_PRETTY_PRINT));
echo json_encode(['base_path'=>$result['base_path'],'php'=>$result['php'],'database'=>$result['database'],'version'=>$result['version'],'test_database_exists'=>$result['test_database_exists'],'counts'=>$result['counts'],'users'=>$result['users'],'branches'=>$result['branches']],JSON_PRETTY_PRINT);
