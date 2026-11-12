<?php

namespace Tests\Feature\Admin;

use App\Models\CompetitionSession;
use App\Models\User;
use App\Models\Category;
use App\Models\Project;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitionSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_access_sessions_management(): void
    {
        $response = $this->get(route('admin.sessions.index'));
        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_sessions_management(): void
    {
        $user = User::factory()->create(['role_id' => 4]); // standard user
        $response = $this->actingAs($user)->get(route('admin.sessions.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_access_sessions_management(): void
    {
        $admin = User::factory()->create(['role_id' => 1]); // admin
        $response = $this->actingAs($admin)->get(route('admin.sessions.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('admin/sessions/index'));
    }

    public function test_admin_can_create_session(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);

        $response = $this->actingAs($admin)->post(route('admin.sessions.store'), [
            'name' => 'Semester 1 2026/2027',
            'is_active' => true,
            'registration_mode' => 'open',
            'session_starts_at' => '2026-01-01',
            'session_ends_at' => '2026-12-31',
        ]);

        $response->assertRedirect(route('admin.sessions.index'));
        $this->assertDatabaseHas('competition_sessions', [
            'name' => 'Semester 1 2026/2027',
            'is_active' => true,
        ]);
    }

    public function test_creating_active_session_deactivates_others(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);

        // Create initial active session
        $session1 = CompetitionSession::create([
            'name' => 'Session 1',
            'is_active' => true,
            'r2_locked' => false,
        ]);

        // Create second active session
        $response = $this->actingAs($admin)->post(route('admin.sessions.store'), [
            'name' => 'Session 2',
            'is_active' => true,
            'registration_mode' => 'open',
            'session_starts_at' => '2026-01-01',
            'session_ends_at' => '2026-12-31',
        ]);

        $response->assertRedirect(route('admin.sessions.index'));

        $this->assertDatabaseHas('competition_sessions', [
            'id' => $session1->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_delete_active_session(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);

        $session = CompetitionSession::create([
            'name' => 'Session 1',
            'is_active' => true,
            'r2_locked' => false,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.sessions.destroy', $session));

        $response->assertRedirect(route('admin.sessions.index'));
        $this->assertDatabaseHas('competition_sessions', [
            'id' => $session->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_deactivate_the_last_active_session(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session = CompetitionSession::create([
            'name' => 'Session 1',
            'is_active' => true,
            'r2_locked' => false,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.sessions.update', $session), [
            'name' => $session->name,
            'is_active' => false,
            'registration_mode' => 'open',
            'session_starts_at' => '2026-01-01',
            'session_ends_at' => '2026-12-31',
        ]);

        $response->assertRedirect(route('admin.sessions.index'));
        $response->assertSessionMissing('error');
        $this->assertDatabaseHas('competition_sessions', [
            'id' => $session->id,
            'is_active' => false,
        ]);
    }

    public function test_activating_a_session_keeps_only_that_session_active(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session1 = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $session2 = CompetitionSession::create(['name' => 'Session 2', 'is_active' => false]);

        $response = $this->actingAs($admin)->put(route('admin.sessions.update', $session2), [
            'name' => $session2->name,
            'is_active' => true,
            'registration_mode' => 'open',
            'session_starts_at' => '2026-01-01',
            'session_ends_at' => '2026-12-31',
        ]);

        $response->assertRedirect(route('admin.sessions.index'));
        $this->assertDatabaseHas('competition_sessions', ['id' => $session1->id, 'is_active' => false]);
        $this->assertDatabaseHas('competition_sessions', ['id' => $session2->id, 'is_active' => true]);
        $this->assertSame(1, CompetitionSession::where('is_active', true)->count());
    }

    public function test_creating_inactive_session_preserves_existing_active_session(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $activeSession = CompetitionSession::create(['name' => 'Active Session', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.sessions.store'), [
            'name' => 'Inactive Session',
            'is_active' => false,
            'registration_mode' => 'open',
            'session_starts_at' => '2026-01-01',
            'session_ends_at' => '2026-12-31',
        ]);

        $response->assertRedirect(route('admin.sessions.index'));
        $this->assertDatabaseHas('competition_sessions', ['id' => $activeSession->id, 'is_active' => true]);
        $this->assertDatabaseHas('competition_sessions', ['name' => 'Inactive Session', 'is_active' => false]);
    }

    public function test_admin_can_delete_inactive_session(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);

        $session = CompetitionSession::create([
            'name' => 'Session 1',
            'is_active' => false,
            'r2_locked' => false,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.sessions.destroy', $session));

        $response->assertRedirect(route('admin.sessions.index'));
        $this->assertDatabaseMissing('competition_sessions', [
            'id' => $session->id,
        ]);
    }

    public function test_admin_cannot_delete_session_that_has_projects(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session = CompetitionSession::create(['name' => 'Past Session', 'is_active' => false]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);
        Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'History Project',
            'abstract' => 'Abstract',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'supervisor_name' => 'Supervisor',
            'supervisor_email' => 'supervisor@example.com',
        ]);

        $this->actingAs($admin)->delete(route('admin.sessions.destroy', $session))
            ->assertRedirect(route('admin.sessions.index'));

        $this->assertDatabaseHas('competition_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('projects', ['session_id' => $session->id]);
    }

    public function test_session_form_cannot_change_round_two_lock(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true, 'r2_locked' => false]);

        $this->actingAs($admin)->put(route('admin.sessions.update', $session), [
            'name' => 'Session 1',
            'is_active' => true,
            'registration_mode' => 'open',
            'session_starts_at' => '2026-01-01',
            'session_ends_at' => '2026-12-31',
            'r2_locked' => true,
        ]);

        $this->assertFalse($session->fresh()->r2_locked);
    }
}
