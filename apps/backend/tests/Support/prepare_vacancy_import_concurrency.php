<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::query()->create([
    'email' => 'vacancy-import-race@example.test',
    'password' => 'synthetic-password',
]);

echo $user->id.PHP_EOL;
