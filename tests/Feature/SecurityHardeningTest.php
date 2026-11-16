<?php

namespace Tests\Feature;

use App\Casts\SafeLink;
use App\Models\Project;
use App\Models\User;
use App\Notifications\SystemNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_carry_security_headers(): void
    {
        $this->get('/')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_the_firewall_is_trusted_for_the_client_ip_but_others_are_not(): void
    {
        Route::get('/_test/ip', fn (Request $request) => $request->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.9'])
            ->withHeader('X-Forwarded-For', '203.0.113.7')
            ->get('/_test/ip')
            ->assertSeeText('203.0.113.7');

        // Anyone else cannot fake their address.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->withHeader('X-Forwarded-For', '203.0.113.7')
            ->get('/_test/ip')
            ->assertSeeText('198.51.100.20');
    }

    public function test_typed_values_cannot_become_links_in_emails(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['role_id' => 4, 'name' => '[Admin](https://evil.example)']);

        $html = (string) (new SystemNotification('Project Approved', 'Text', '/dashboard/projects', 'success',
            mailEvent: 'project_approved',
            mailData: ['project_title' => '[Sign in here](https://evil.example/login)', 'project_code' => 'ICT-01']))
            ->toMail($user)->render();

        $this->assertStringNotContainsString('href="https://evil.example', $html);
        $this->assertStringContainsString('[Sign in here](https://evil.example/login)', $html);
        $this->assertStringContainsString('Hello [Admin](https://evil.example),', $html);
    }

    public function test_only_web_links_are_passed_on_for_poster_and_video(): void
    {
        $this->assertSame('https://youtu.be/abc', SafeLink::clean('https://youtu.be/abc'));
        $this->assertSame('/storage/posters/a.pdf', SafeLink::clean('/storage/posters/a.pdf'));
        $this->assertNull(SafeLink::clean('javascript:alert(document.cookie)'));
        $this->assertNull(SafeLink::clean(' JavaScript:alert(1)'));
        $this->assertNull(SafeLink::clean('data:text/html,<script>alert(1)</script>'));
        $this->assertNull(SafeLink::clean('//evil.example/x'));
        $this->assertNull(SafeLink::clean(''));

        $project = new Project(['video_url' => 'javascript:alert(1)', 'poster_url' => 'https://example.com/p.png']);
        $this->assertNull($project->video_url);
        $this->assertSame('https://example.com/p.png', $project->toArray()['poster_url']);
    }
}
