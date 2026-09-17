<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

$app = require __DIR__.'/bootstrap.php';

// Migrate only the isolated in-memory database; never the application database.
Artisan::call('migrate', ['--force' => true]);
$user = new User(['name' => 'Operator Uji', 'email' => 'ui-test@example.test']);
$role = $argv[3] ?? 'operator';
if (! in_array($role, ['operator', 'viewer'], true)) {
    throw new InvalidArgumentException('Invalid fixture role');
}
$user->forceFill(['role' => $role]);
auth()->setUser($user);
$request = Request::create($argv[1].($argv[2] ?? '/dashboard'));
$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request);

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, 'Fixture HTTP '.$response->getStatusCode());
    exit(1);
}

echo json_encode([
    'body' => $response->getContent(),
    'headers' => [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Content-Security-Policy' => $response->headers->get('Content-Security-Policy'),
    ],
], JSON_THROW_ON_ERROR);
$kernel->terminate($request, $response);
