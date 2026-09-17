<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Laravel\Boost\BoostManager;
use Laravel\Boost\Middleware\InjectBoost;

require __DIR__.'/../../vendor/autoload.php';

$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'local';
$_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'] = 'false';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->afterBootstrapping(LoadConfiguration::class, function ($app): void {
    $app['config']->set([
        'app.env' => 'local',
        'app.debug' => false,
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'database.connections.sqlite.url' => null,
        'session.driver' => 'array',
        'cache.default' => 'array',
        'queue.default' => 'sync',
        'mail.default' => 'array',
        'logging.default' => 'null',
        'logging.channels.browser' => ['driver' => 'null'],
        'boost.enabled' => true,
    ]);
});
$app->make(ConsoleKernel::class)->bootstrap();
$request = Request::create('http://localhost/_boost/browser-logs', 'POST', server: ['CONTENT_TYPE' => 'text/plain', 'HTTP_ACCEPT' => 'application/json'], content: '{"logs":[]}');
$response = $app->make(Kernel::class)->handle($request);
echo json_encode([
    'environment' => $app->environment(),
    'debug' => config('app.debug'),
    'boost_active' => $app->bound(BoostManager::class),
    'boost_cli' => array_key_exists('boost:mcp', Artisan::all()),
    'browser_logs_status' => $response->getStatusCode(),
    'browser_logs_route' => $app['router']->getRoutes()->hasNamedRoute('boost.browser-logs'),
    'browser_injection' => in_array(InjectBoost::class, $app['router']->getMiddlewareGroups()['web'], true),
], JSON_THROW_ON_ERROR);
