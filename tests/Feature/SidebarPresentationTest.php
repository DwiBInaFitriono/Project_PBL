<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_has_no_empty_settings_section_but_keeps_account_link(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertDontSeeText('PENGATURAN')
            ->assertDontSee('aria-label="Setting ESP"', false)
            ->assertSee('data-sidebar-account', false)
            ->assertSee('aria-label="Setting Akun"', false);
        $this->getJson('/settings/esp')->assertForbidden();
    }

    public function test_operator_keeps_settings_section_and_esp_link(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->operator()->create())->get('/dashboard')
            ->assertOk()
            ->assertSeeText('PENGATURAN')
            ->assertSee('aria-label="Setting ESP"', false)
            ->assertSee('aria-label="Setting Akun"', false);
    }
}
