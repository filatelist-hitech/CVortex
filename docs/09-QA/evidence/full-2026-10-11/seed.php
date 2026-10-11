<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::selectOne('select current_database() as name')->name;
if ($db !== 'cvortex_qa_synthetic') { throw new RuntimeException('Wrong audit database'); }
$password = getenv('QA_SYNTHETIC_PASSWORD');
if (!$password) { throw new RuntimeException('Missing synthetic fixture password'); }
$result = [];
foreach (['admin', 'user-a', 'user-b'] as $role) {
    $user = App\Models\User::query()->create(['email' => $role.'@qa.example.test', 'password' => Illuminate\Support\Facades\Hash::make($password)]);
    if ($role === 'admin') { $user->forceFill(['role' => 'admin'])->save(); }
    $result[$role] = $user->id;
}
$invitation = app(App\Services\InvitationService::class)->create('registered@qa.example.test', 7);
$result['invitation_token'] = $invitation['token'];
echo json_encode($result, JSON_THROW_ON_ERROR);
