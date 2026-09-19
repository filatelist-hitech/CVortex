<?php

use App\Models\User;
use App\Services\CareerFactService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$user = User::query()->create(['email' => 'supersession-race@example.test', 'password' => 'synthetic-password']);
$original = app(CareerFactService::class)->createManual($user, 'skill', 'Original concurrency assertion.');
echo $user->id.PHP_EOL.$original->id.PHP_EOL;
