<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (Illuminate\Support\Facades\DB::selectOne('select current_database() as name')->name !== 'cvortex_qa004_synthetic') {
    throw new RuntimeException('Refusing fixture writes outside isolated QA004 database');
}
$password = getenv('QA_SYNTHETIC_PASSWORD');
if (! $password) { throw new RuntimeException('Missing synthetic password'); }
foreach (['user-a', 'user-b'] as $name) {
    App\Models\User::query()->create(['email' => $name.'-'.getenv('QA_RUN_ID').'@qa004.example.test', 'password' => Illuminate\Support\Facades\Hash::make($password)]);
}
echo "Synthetic users created; no Career/Claim status fixtures.\n";
