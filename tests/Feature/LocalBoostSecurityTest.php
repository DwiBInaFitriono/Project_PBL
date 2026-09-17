<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class LocalBoostSecurityTest extends TestCase
{
    public function test_local_boot_disables_browser_log_ingestion_without_disabling_boost_cli(): void
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/local-boot.php')], base_path());
        $process->mustRun();
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('local', $result['environment']);
        $this->assertFalse($result['debug']);
        $this->assertTrue($result['boost_active']);
        $this->assertTrue($result['boost_cli']);
        $this->assertSame(404, $result['browser_logs_status']);
        $this->assertFalse($result['browser_logs_route']);
        $this->assertFalse($result['browser_injection']);
    }
}
