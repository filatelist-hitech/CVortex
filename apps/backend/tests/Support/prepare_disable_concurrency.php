<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$password = Hash::make('a very long safe passphrase');
$ids = [];
foreach (['disable-one@example.test', 'disable-two@example.test'] as $email) {
    $user = new User;
    $user->forceFill([
        'email' => $email,
        'password' => $password,
        'role' => User::ROLE_ADMIN,
        'status' => User::STATUS_ACTIVE,
    ])->save();
    $ids[] = $user->id;
    DB::table('sessions')->insert([
        'id' => 'session-'.$user->id,
        'user_id' => $user->id,
        'payload' => 'payload',
        'last_activity' => time(),
    ]);
}

echo implode(PHP_EOL, $ids).PHP_EOL;
