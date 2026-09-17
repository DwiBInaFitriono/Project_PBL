<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Vite;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';

// Isolate before service providers or request middleware can open any database.
$app->afterBootstrapping(LoadConfiguration::class, function ($app): void {
    $app['config']->set([
        'app.env' => 'testing',
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'database.connections.sqlite.url' => null,
        'session.driver' => 'cookie',
        'session.cookie' => 'rebung_browser_test_session',
        'cache.default' => 'array',
        'queue.default' => 'sync',
        'mail.default' => 'array',
        'logging.default' => 'null',
        'view.compiled' => getenv('VIEW_COMPILED_PATH') ?: sys_get_temp_dir(),
    ]);
});

$app->make(Kernel::class)->bootstrap();
Vite::useHotFile(__DIR__.'/no-vite-hot-file');

return $app;
