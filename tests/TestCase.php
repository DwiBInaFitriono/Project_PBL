<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // This runs before RefreshDatabase can execute migrations.
        $this->assertSafeDatabase($app);

        return $app;
    }

    protected function assertSafeDatabase(Application $app): void
    {
        $config = $app['config'];

        if ($config->get('database.default') !== 'sqlite'
            || $config->get('database.connections.sqlite.database') !== ':memory:'
            || ! empty($config->get('database.connections.sqlite.url'))) {
            throw new LogicException('Tests require SQLite :memory: with an empty DB_URL. Refusing to modify another database.');
        }
    }
}
