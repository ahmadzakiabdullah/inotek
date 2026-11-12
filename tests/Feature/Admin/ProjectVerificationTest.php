<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_access_approvals_dashboard(): void
    {
        $response = $this->get(route('admin.approvals.index'));
        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_approvals_dashboard(): void
    {
        $user = User::factory()->create(['role_id' => 4]); // user role
        $response = $this->actingAs($user)->get(route('admin.approvals.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_access_approvals_dashboard(): void
    {
        $admin = User::factory()->create(['role_id' => 1]); // admin
        $response = $this->actingAs($admin)->get(route('admin.approvals.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('admin/approvals/index'));
    }

    public function test_admin_can_bulk_approve_projects(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);

        $project1 = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Project 1',
            'abstract' => 'Sample',
            'institution_type' => 'utem',
            'status' => Project::STATUS_SUBMITTED,
            'supervisor_name' => 'Super',
            'supervisor_email' => 's@test.com',
        ]);

        $project2 = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Project 2',
            'abstract' => 'Sample 2',
            'institution_type' => 'utem',
            'status' => Project::STATUS_SUBMITTED,
            'supervisor_name' => 'Super',
            'supervisor_email' => 's@test.com',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.approvals.approve'), [
            'project_ids' => [$project1->id, $project2->id],
        ]);

        $response->assertRedirect(route('admin.approvals.index'));

        $this->assertDatabaseHas('projects', [
            'id' => $project1->id,
            'status' => Project::STATUS_APPROVED,
            'pcode' => 'C1-01',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project2->id,
            'status' => Project::STATUS_APPROVED,
            'pcode' => 'C1-02',
        ]);
    }

    public function test_admin_approval_generates_an_unused_code_when_existing_codes_have_a_gap(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $student = User::factory()->create(['role_id' => 4]);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);
        Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => $student->id,
            'pcode' => 'C1-02',
            'title' => 'Existing project',
            'abstract' => 'Sample',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'supervisor_name' => 'Super',
            'supervisor_email' => 's@test.com',
        ]);
        $draft = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Draft project',
            'abstract' => 'Sample',
            'institution_type' => 'utem',
            'status' => Project::STATUS_SUBMITTED,
            'supervisor_name' => 'Super',
            'supervisor_email' => 's@test.com',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.approvals.approve'), [
            'project_ids' => [$draft->id],
        ]);

        $response->assertRedirect(route('admin.approvals.index'));
        $this->assertDatabaseHas('projects', [
            'id' => $draft->id,
            'status' => Project::STATUS_APPROVED,
            'pcode' => 'C1-01',
        ]);
    }

    public function test_admin_cannot_register_project_in_active_session_using_another_sessions_category(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $activeSession = CompetitionSession::create(['name' => 'Active', 'is_active' => true]);
        $otherSession = CompetitionSession::create(['name' => 'Other', 'is_active' => false]);
        $category = Category::create(['session_id' => $otherSession->id, 'code' => 'C2', 'name' => 'Other Category']);
        $student = User::factory()->create(['role_id' => 4]);

        $response = $this->actingAs($admin)->post(route('admin.approvals.storeProject'), [
            'user_id' => $student->id,
            'category_id' => $category->id,
            'title' => 'Mismatched project',
            'abstract' => 'Project abstract',
            'institution_type' => 'utem',
            'supervisor_name' => 'Dr. Supervisor',
            'supervisor_email' => 'supervisor@utem.edu.my',
        ]);

        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseMissing('projects', ['session_id' => $activeSession->id, 'user_id' => $student->id]);
    }

    public function test_admin_can_reject_project_with_comments(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);

        $project = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Project 1',
            'abstract' => 'Sample',
            'institution_type' => 'utem',
            'status' => Project::STATUS_SUBMITTED,
            'supervisor_name' => 'Super',
            'supervisor_email' => 's@test.com',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.approvals.reject', $project), [
            'admin_comments' => 'Please update your abstract details.',
        ]);

        $response->assertRedirect(route('admin.approvals.index'));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => Project::STATUS_EDIT,
            'admin_comments' => 'Please update your abstract details.',
        ]);
    }

    public function test_admin_can_override_project_code(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);

        $project = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Project 1',
            'abstract' => 'Sample',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'pcode' => 'C1-01',
            'supervisor_name' => 'Super',
            'supervisor_email' => 's@test.com',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.approvals.code', $project), [
            'pcode' => 'C1-OVERRIDE',
        ]);

        $response->assertRedirect(route('admin.approvals.index'));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'pcode' => 'C1-OVERRIDE',
        ]);
    }

    public function test_admin_cannot_override_project_code_with_existing_duplicate_in_session(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);

        Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Project 1',
            'abstract' => 'Sample',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'pcode' => 'C1-01',
            'supervisor_name' => 'Super',
            'supervisor_email' => 's@test.com',
        ]);

        $project2 = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Project 2',
            'abstract' => 'Sample 2',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'pcode' => 'C1-02',
            'supervisor_name' => 'Super',
            'supervisor_email' => 's@test.com',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.approvals.code', $project2), [
            'pcode' => 'C1-01', // duplicate code
        ]);

        $response->assertSessionHasErrors('pcode');
        $this->assertDatabaseHas('projects', [
            'id' => $project2->id,
            'pcode' => 'C1-02', // code remains unchanged
        ]);
    }

    public function test_lecturer_can_access_approvals_dashboard_and_only_see_supervised_projects(): void
    {
        $lecturer = User::factory()->create(['role_id' => 2, 'email' => 'lecturer@utem.edu.my']);
        $otherLecturer = User::factory()->create(['role_id' => 2, 'email' => 'other@utem.edu.my']);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);
        $student = User::factory()->create(['role_id' => 4]);

        // Supervised project (supervisor email differs in case only)
        $project1 = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => $student->id,
            'title' => 'Supervised Project',
            'abstract' => 'Sample',
            'institution_type' => 'utem',
            'status' => Project::STATUS_SUBMITTED,
            'supervisor_name' => 'Lecturer A',
            'supervisor_email' => 'Lecturer@UTeM.edu.my',
        ]);

        // A project the lecturer owns is never shown for their own review.
        Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => $lecturer->id,
            'title' => 'Own Project',
            'abstract' => 'Sample 3',
            'institution_type' => 'utem',
            'status' => Project::STATUS_SUBMITTED,
            'supervisor_name' => 'Lecturer A',
            'supervisor_email' => 'lecturer@utem.edu.my',
        ]);

        // Unsupervised project
        $project2 = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => $otherLecturer->id,
            'title' => 'Other Project',
            'abstract' => 'Sample 2',
            'institution_type' => 'utem',
            'status' => Project::STATUS_SUBMITTED,
            'supervisor_name' => 'Lecturer B',
            'supervisor_email' => 'other@utem.edu.my',
        ]);

        $response = $this->actingAs($lecturer)->get(route('admin.approvals.index'));
        $response->assertStatus(200);

        // Assert Inertia page receives only the supervised project, and no student list
        $response->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.id', $project1->id)
            ->has('students', 0)
        );
    }

    public function test_lecturer_cannot_approve_own_project(): void
    {
        $lecturer = User::factory()->create(['role_id' => 2, 'email' => 'lecturer@utem.edu.my']);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);

        $project = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => $lecturer->id,
            'title' => 'Own Project',
            'abstract' => 'Sample',
            'institution_type' => 'utem',
            'status' => Project::STATUS_SUBMITTED,
            'supervisor_name' => 'Lecturer A',
            'supervisor_email' => 'lecturer@utem.edu.my',
        ]);

        $this->actingAs($lecturer)->post(route('admin.approvals.approve'), [
            'project_ids' => [$project->id],
        ]);

        $this->assertSame(Project::STATUS_SUBMITTED, $project->fresh()->status);
    }

    public function test_admin_cannot_register_project_for_non_participant(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $lecturer = User::factory()->create(['role_id' => 2]);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);

        $response = $this->actingAs($admin)->post(route('admin.approvals.storeProject'), [
            'user_id' => $lecturer->id,
            'category_id' => $category->id,
            'title' => 'Lecturer project',
            'abstract' => 'Project abstract',
            'institution_type' => 'utem',
            'supervisor_name' => 'Dr. Supervisor',
            'supervisor_email' => 'supervisor@utem.edu.my',
        ]);

        $response->assertSessionHasErrors('user_id');
        $this->assertDatabaseMissing('projects', ['user_id' => $lecturer->id]);
    }

    public function test_lecturer_cannot_reject_unsupervised_project(): void
    {
        $lecturer = User::factory()->create(['role_id' => 2, 'email' => 'lecturer@utem.edu.my']);
        $session = CompetitionSession::create(['name' => 'Session 1', 'is_active' => true]);
        $category = Category::create(['session_id' => $session->id, 'code' => 'C1', 'name' => 'Tech']);

        $project = Project::create([
            'session_id' => $session->id,
            'category_id' => $category->id,
            'user_id' => $lecturer->id,
            'title' => 'Other Project',
            'abstract' => 'Sample 2',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'pcode' => 'C1-01',
            'supervisor_name' => 'Lecturer B',
            'supervisor_email' => 'other@utem.edu.my', // not supervised by $lecturer
        ]);

        $response = $this->actingAs($lecturer)->post(route('admin.approvals.reject', $project), [
            'admin_comments' => 'Send back',
        ]);

        $response->assertStatus(403);
    }
}
