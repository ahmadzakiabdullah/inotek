<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthAuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->user = User::factory()->create([
            'role_id' => 4, // standard user / participant
            'password' => bcrypt('password123'),
        ]);
    }

    /**
     * Test that successful login triggers audit logging.
     */
    public function test_login_records_audit_log()
    {
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'LOGIN',
        ]);

        // Perform login request
        $response = $this->post(route('login'), [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Auth::check());

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LOGIN',
            'user_id' => $this->user->id,
            'description' => "User '{$this->user->name}' (Email: {$this->user->email}) successfully logged in.",
        ]);
    }

    /**
     * Test that logout triggers audit logging.
     */
    public function test_logout_records_audit_log()
    {
        // Authenticate user first
        $this->actingAs($this->user);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'LOGOUT',
        ]);

        // Perform logout request
        $response = $this->post(route('logout'));

        $response->assertRedirect();
        $this->assertFalse(Auth::check());

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LOGOUT',
            'user_id' => $this->user->id,
            'description' => "User '{$this->user->name}' (Email: {$this->user->email}) logged out.",
        ]);
    }
}
