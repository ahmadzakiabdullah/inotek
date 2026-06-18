<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeletesTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected CompetitionSession $session;
    protected Category $category;
    protected Project $project;
    protected TeamMember $teamMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Create a User
        $this->student = User::factory()->create(['role_id' => 4]);

        // Create Session
        $this->session = CompetitionSession::create([
            'name' => 'Session 2026',
            'is_active' => true,
            'r2_locked' => false,
        ]);

        // Create Category
        $this->category = Category::create([
            'session_id' => $this->session->id,
            'code' => 'C1',
            'name' => 'Tech Category',
        ]);

        // Create Project
        $this->project = Project::create([
            'session_id' => $this->session->id,
            'category_id' => $this->category->id,
            'user_id' => $this->student->id,
            'title' => 'Project Alpha',
            'abstract' => 'An innovation test.',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'pcode' => 'C1-01',
            'supervisor_name' => 'Prof. Dr. Ali',
            'supervisor_email' => 'ali@utem.edu.my',
        ]);

        // Create Team Member
        $this->teamMember = TeamMember::create([
            'project_id' => $this->project->id,
            'name' => 'John Doe',
            'email' => 'john@student.com',
            'phone' => '0123456789',
        ]);
    }

    /**
     * Test that deleting a user soft deletes them.
     */
    public function test_user_soft_deletes()
    {
        $this->assertNull($this->student->deleted_at);

        // Delete user
        $this->student->delete();

        // Check soft deleted status
        $this->assertTrue($this->student->trashed());
        $this->assertNotNull($this->student->deleted_at);

        // Database still has record
        $this->assertDatabaseHas('users', [
            'id' => $this->student->id,
        ]);

        // Restore user
        $this->student->restore();
        $this->assertFalse($this->student->trashed());
        $this->assertNull($this->student->deleted_at);
    }

    /**
     * Test that deleting a project soft deletes it.
     */
    public function test_project_soft_deletes()
    {
        $this->assertNull($this->project->deleted_at);

        // Delete project
        $this->project->delete();

        // Check soft deleted status
        $this->assertTrue($this->project->trashed());
        $this->assertNotNull($this->project->deleted_at);

        // Database still has record
        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
        ]);

        // Restore project
        $this->project->restore();
        $this->assertFalse($this->project->trashed());
        $this->assertNull($this->project->deleted_at);
    }

    /**
     * Test that deleting a team member soft deletes them.
     */
    public function test_team_member_soft_deletes()
    {
        $this->assertNull($this->teamMember->deleted_at);

        // Delete team member
        $this->teamMember->delete();

        // Check soft deleted status
        $this->assertTrue($this->teamMember->trashed());
        $this->assertNotNull($this->teamMember->deleted_at);

        // Database still has record
        $this->assertDatabaseHas('team_members', [
            'id' => $this->teamMember->id,
        ]);

        // Restore team member
        $this->teamMember->restore();
        $this->assertFalse($this->teamMember->trashed());
        $this->assertNull($this->teamMember->deleted_at);
    }
}
