<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use LogicException;
use Tests\TestCase;

class TestIsolationTest extends TestCase
{
    public function test_database_environment_cannot_override_phpunit_settings(): void
    {
        $document = new DOMDocument;
        $document->load(base_path('phpunit.xml'));
        $xpath = new DOMXPath($document);

        foreach (['APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'SESSION_DRIVER', 'CACHE_STORE'] as $name) {
            $node = $xpath->query("//env[@name='{$name}']")->item(0);
            $this->assertSame('true', $node->getAttribute('force'), $name);
        }
    }

    public function test_browser_server_does_not_reuse_the_application_server(): void
    {
        $configuration = file_get_contents(base_path('playwright.config.js'));

        $this->assertStringContainsString('reuseExistingServer: false', $configuration);
        $this->assertStringContainsString('tests/Browser/support/server.php', $configuration);
    }

    public function test_effective_database_is_in_memory(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
    }

    public function test_file_backed_database_is_rejected_before_refresh_database(): void
    {
        config(['database.connections.sqlite.database' => '/never-open-this-file.sqlite']);

        $this->expectException(LogicException::class);
        $this->assertSafeDatabase($this->app);
    }
}
