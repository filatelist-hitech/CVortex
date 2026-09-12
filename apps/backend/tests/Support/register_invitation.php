<?php

use App\Services\InvitationService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$token = trim((string) fgets(STDIN));
$email = $argv[1] ?? '';

try {
    app(InvitationService::class)->register($token, $email, 'a very long safe passphrase');
    fwrite(STDOUT, "registered\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDOUT, get_class($exception)."\n");
    exit(1);
}
