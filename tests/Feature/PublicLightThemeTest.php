<?php

namespace Tests\Feature;

use App\Models\CompetitionSession;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLightThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        CompetitionSession::create(['name' => 'Sesi 2/2026', 'is_active' => true]);
    }

    public function test_public_pages_are_light_even_when_the_visitor_chose_dark(): void
    {
        foreach (['/', '/leaderboard', '/contact'] as $path) {
            $html = $this->withUnencryptedCookie('appearance', 'dark')->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString('class="dark"', $html, $path);
            // The system-preference script is not emitted, so the device setting cannot darken it either.
            $this->assertStringContainsString("const appearance = 'light'", $html, $path);
        }
    }

    public function test_the_dashboard_keeps_the_users_own_choice(): void
    {
        $user = User::factory()->create(['role_id' => 4]);

        $this->actingAs($user)->withUnencryptedCookie('appearance', 'dark')->get('/dashboard')
            ->assertOk()
            ->assertSee('class="dark"', false);

        $this->actingAs($user)->withUnencryptedCookie('appearance', 'system')->get('/dashboard')
            ->assertSee("const appearance = 'system'", false);
    }
}
