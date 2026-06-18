<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryRubricMapping;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\User;
use App\Models\JudgeAssignment;
use App\Models\Score;
use App\Models\Rubric;
use App\Models\RubricItem;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $judge;
    protected User $student;
    protected CompetitionSession $session;
    protected Category $category;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Create Users
        $this->admin = User::factory()->create(['role_id' => 1]); // admin
        $this->judge = User::factory()->create(['role_id' => 3]); // judge
        $this->student = User::factory()->create(['role_id' => 4]); // student

        // Create Active Session
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
            'status' => Project::STATUS_NEW,
            'supervisor_name' => 'Prof. Dr. Ali',
            'supervisor_email' => 'ali@utem.edu.my',
        ]);
    }

    /**
     * Test notification insertion and retrieval.
     */
    public function test_can_notify_user_manually_and_retrieve()
    {
        $this->student->notify(new \App\Notifications\SystemNotification(
            'Test Title',
            'Test Message',
            '/test-action',
            'info'
        ));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->student->id,
            'notifiable_type' => User::class,
        ]);

        $notification = $this->student->notifications()->first();
        $this->assertEquals('Test Title', $notification->data['title']);
        $this->assertEquals('Test Message', $notification->data['message']);
        $this->assertEquals('/test-action', $notification->data['action_url']);
        $this->assertEquals('info', $notification->data['type']);
    }

    /**
     * Test marking notification as read.
     */
    public function test_can_mark_notification_as_read()
    {
        $this->student->notify(new \App\Notifications\SystemNotification(
            'Test Title',
            'Test Message',
            '/test-action',
            'info'
        ));

        $notification = $this->student->unreadNotifications()->first();
        $this->assertNotNull($notification);

        $response = $this->actingAs($this->student)
            ->post("/notifications/{$notification->id}/read");

        $response->assertRedirect();
        $this->assertEquals(0, $this->student->unreadNotifications()->count());
    }

    /**
     * Test marking all notifications as read.
     */
    public function test_can_mark_all_notifications_as_read()
    {
        $this->student->notify(new \App\Notifications\SystemNotification('T1', 'M1'));
        $this->student->notify(new \App\Notifications\SystemNotification('T2', 'M2'));

        $this->assertEquals(2, $this->student->unreadNotifications()->count());

        $response = $this->actingAs($this->student)
            ->post("/notifications/read-all");

        $response->assertRedirect();
        $this->assertEquals(0, $this->student->unreadNotifications()->count());
    }

    /**
     * Test project submission triggers notification to admins.
     */
    public function test_project_submission_notifies_admins()
    {
        $response = $this->actingAs($this->student)
            ->post(route('projects.submit', $this->project));

        $response->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->admin->id,
            'notifiable_type' => User::class,
        ]);

        $notification = $this->admin->notifications()->first();
        $this->assertStringContainsString('New Project Approval Request', $notification->data['title']);
    }

    /**
     * Test project approval triggers notification to student.
     */
    public function test_project_approval_notifies_student()
    {
        // Change project status to submitted
        $this->project->update(['status' => Project::STATUS_SUBMITTED]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.approvals.approve'), [
                'project_ids' => [$this->project->id]
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->student->id,
            'notifiable_type' => User::class,
        ]);

        $notification = $this->student->notifications()->first();
        $this->assertStringContainsString('Project Approved', $notification->data['title']);
    }

    /**
     * Test project rejection triggers notification to student.
     */
    public function test_project_rejection_notifies_student()
    {
        // Change project status to submitted
        $this->project->update(['status' => Project::STATUS_SUBMITTED]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.approvals.reject', $this->project), [
                'admin_comments' => 'Please correct the abstract.'
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->student->id,
            'notifiable_type' => User::class,
        ]);

        $notification = $this->student->notifications()->first();
        $this->assertStringContainsString('Project Returned for Editing', $notification->data['title']);
    }

    /**
     * Test judge assignment triggers notification to judge.
     */
    public function test_judge_assignment_notifies_judge()
    {
        // Make sure project is approved first
        $this->project->update(['status' => Project::STATUS_APPROVED]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.assignments.store'), [
                'project_id' => $this->project->id,
                'judge_id' => $this->judge->id,
                'round_no' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->judge->id,
            'notifiable_type' => User::class,
        ]);

        $notification = $this->judge->notifications()->first();
        $this->assertStringContainsString('New Judging Assignment', $notification->data['title']);
    }

    /**
     * Test evaluation completion triggers notification to admins.
     */
    public function test_evaluation_completion_notifies_admins()
    {
        // Approve project and assign judge
        $this->project->update(['status' => Project::STATUS_APPROVED]);
        JudgeAssignment::create([
            'project_id' => $this->project->id,
            'judge_id' => $this->judge->id,
            'session_id' => $this->session->id,
            'round_no' => 1,
        ]);

        // Create Rubric
        $rubric = Rubric::create([
            'name' => 'Standard Rubric',
            'description' => 'Test Rubric',
        ]);
        $item1 = RubricItem::create([
            'rubric_id' => $rubric->id,
            'criteria_name' => 'Criteria 1',
            'weight' => 0.50,
            'max_points' => 5,
        ]);
        $item2 = RubricItem::create([
            'rubric_id' => $rubric->id,
            'criteria_name' => 'Criteria 2',
            'weight' => 0.50,
            'max_points' => 5,
        ]);
        CategoryRubricMapping::create([
            'category_id' => $this->category->id,
            'rubric_id' => $rubric->id,
        ]);

        $response = $this->actingAs($this->judge)
            ->post(route('judge.evaluations.store', $this->project), [
                'round_no' => 1,
                'scores' => [
                    $item1->id => 5,
                    $item2->id => 4,
                ],
                'comments' => 'Excellent work',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->admin->id,
            'notifiable_type' => User::class,
        ]);

        $notification = $this->admin->notifications()->first();
        $this->assertStringContainsString('Project Evaluation Submitted', $notification->data['title']);
    }

    /**
     * Test admin can access announcement index page.
     */
    public function test_admin_can_access_announcement_page()
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/announcements');

        $response->assertStatus(200);
    }

    /**
     * Test non-admin is blocked from announcement page.
     */
    public function test_non_admin_cannot_access_announcement_page()
    {
        $response = $this->actingAs($this->student)
            ->get('/admin/announcements');

        $response->assertStatus(403);
    }

    /**
     * Test broadcasting announcement to judges only.
     */
    public function test_admin_can_broadcast_announcement_to_judges()
    {
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $this->judge->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/announcements', [
                'title' => 'Broadcast Test Title',
                'message' => 'Broadcast Test Message',
                'target_role' => 'judge',
                'type' => 'warning',
            ]);

        $response->assertRedirect();
        
        // Judge should have notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->judge->id,
            'notifiable_type' => User::class,
        ]);
        
        $notification = $this->judge->notifications()->first();
        $this->assertEquals('Broadcast Test Title', $notification->data['title']);
        $this->assertEquals('Broadcast Test Message', $notification->data['message']);
        $this->assertEquals('warning', $notification->data['type']);

        // Student should NOT have notification
        $this->assertEquals(0, $this->student->notifications()->count());
    }
}
