<?php

// Test-only server. Database isolation is enforced before providers boot.
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../vendor/autoload.php';

$directory = getenv('REBUNG_TEST_DIRECTORY');
$password = getenv('REBUNG_TEST_PASSWORD');
$directory = is_string($directory) ? realpath($directory) : false;
if ($directory === false || ! str_starts_with(basename($directory), 'rebung-api-e2e-')
    || realpath(dirname($directory)) !== realpath(sys_get_temp_dir()) || ! is_string($password) || strlen($password) < 24) {
    throw new RuntimeException('Isolated test environment required.');
}
$database = $directory.DIRECTORY_SEPARATOR.'database.sqlite';
if (! is_file($database)) {
    throw new RuntimeException('Test database file required.');
}
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($database, $directory): void {
    $app['config']->set([
        'app.env' => 'testing', 'app.debug' => false,
        'database.default' => 'sqlite', 'database.connections.sqlite.database' => $database,
        'database.connections.sqlite.url' => null,
        'cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync',
        'mail.default' => 'array', 'logging.default' => 'null', 'view.compiled' => $directory,
        'mqtt.enabled' => false,
    ]);
});
$app->make(Kernel::class)->bootstrap();
if (PHP_SAPI === 'cli') {
    Artisan::call('migrate', ['--force' => true]);
    User::factory()->operator()->create(['email' => 'operator@integration.test', 'password' => $password]);
    fwrite(STDOUT, 'Isolated REST fixture ready.');
} else {
    $app->handleRequest(Request::capture());
}
