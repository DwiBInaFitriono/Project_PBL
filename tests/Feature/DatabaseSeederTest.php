<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeder_does_not_create_a_dummy_account(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
    }
}
