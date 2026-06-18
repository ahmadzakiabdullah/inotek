<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\Rubric;
use App\Models\User;
use App\Models\AuditLog;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateAndAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $student;
    protected User $student2;
    protected CompetitionSession $session;
    protected Category $category;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Create Users
        $this->admin = User::factory()->create(['role_id' => 1]);
        $this->student = User::factory()->create(['role_id' => 4]);
        $this->student2 = User::factory()->create(['role_id' => 4]);

        // Create Active Session and Category
        $this->session = CompetitionSession::create([
            'name' => 'Session 2026',
            'is_active' => true,
            'r2_locked' => false,
        ]);

        $this->category = Category::create([
            'session_id' => $this->session->id,
            'code' => 'C1',
            'name' => 'Tech Category',
        ]);

        // Create Rubric & map to Category so we have active evaluation paths
        $rubric = Rubric::create(['name' => 'Test Rubric']);
        $this->category->rubrics()->attach($rubric);

        // Create a Project for Student 1
        $this->project = Project::create([
            'session_id' => $this->session->id,
            'category_id' => $this->category->id,
            'user_id' => $this->student->id,
            'title' => 'Project Alpha',
            'abstract' => 'An innovation test.',
            'institution_type' => 'utem',
            'status' => Project::STATUS_NEW,
            'supervisor_name' => 'Prof. Dr. Ali',
            'supervisor_email' => 'ali@utem.edu.my',
        ]);
    }

    /**
     * Test certificate access rules.
     */
    public function test_certificate_download_access_controls()
    {
        // 1. Participant cannot download certificate of participation for draft project
        $response = $this->actingAs($this->student)
            ->get(route('certificates.downloadParticipation', $this->project));
        $response->assertSessionHasErrors(['status']);

        // 2. Approve project
        $this->project->update([
            'status' => Project::STATUS_APPROVED,
            'pcode' => 'C1-01',
        ]);

        // 3. Project owner can download participation certificate
        $response = $this->actingAs($this->student)
            ->get(route('certificates.downloadParticipation', $this->project));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // 4. Other student cannot download their certificate
        $response = $this->actingAs($this->student2)
            ->get(route('certificates.downloadParticipation', $this->project));
        $response->assertStatus(403);

        // 5. Admin can download the certificate
        $response = $this->actingAs($this->admin)
            ->get(route('certificates.downloadParticipation', $this->project));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Test certificate of achievement rules.
     */
    public function test_certificate_achievement_validation()
    {
        $this->project->update([
            'status' => Project::STATUS_APPROVED,
            'pcode' => 'C1-01',
        ]);

        // 1. Owner cannot download achievement certificate if no award assigned
        $response = $this->actingAs($this->student)
            ->get(route('certificates.downloadAchievement', $this->project));
        $response->assertSessionHasErrors(['award']);

        // 2. Assign Gold award
        $this->project->update(['award_level' => 'Gold']);

        // 3. Owner can download achievement certificate
        $response = $this->actingAs($this->student)
            ->get(route('certificates.downloadAchievement', $this->project));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Test public certificate verification hash.
     */
    public function test_public_certificate_verification()
    {
        $this->project->update([
            'status' => Project::STATUS_APPROVED,
            'pcode' => 'C1-01',
            'certificate_hash' => 'test-hash-signature',
        ]);

        // 1. Verify valid hash renders correctly
        $response = $this->get(route('certificates.verify', 'test-hash-signature'));
        $response->assertStatus(200);
        $response->assertSee('test-hash-signature');

        // 2. Verify invalid hash fails verification but responds with failure status
        $response = $this->get(route('certificates.verify', 'fake-hash'));
        $response->assertStatus(200);
        $response->assertSee('fake-hash');
    }

    /**
     * Test audit logging triggers and access controls.
     */
    public function test_audit_logging_mechanisms()
    {
        // 1. Submit project first as student so it can be approved
        $this->project->update(['status' => Project::STATUS_SUBMITTED]);

        // 2. Admin approves project, triggering an audit log
        $this->actingAs($this->admin)->post(route('admin.approvals.approve'), [
            'project_ids' => [$this->project->id],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'APPROVE_PROJECT',
        ]);

        // 3. Admin locks Round 2 session, triggering an audit log
        $this->actingAs($this->admin)->post(route('admin.round2.lockToggle'));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'LOCK_ROUND_2',
        ]);

        // 4. Admin accesses audit logs page
        $response = $this->actingAs($this->admin)->get(route('admin.audit-logs.index'));
        $response->assertStatus(200);

        // 5. Student cannot access audit logs page
        $response = $this->actingAs($this->student)->get(route('admin.audit-logs.index'));
        $response->assertStatus(403);
    }
}
