<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\EmailNotifications;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected CompetitionSession $session;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['role_id' => 1, 'email' => 'admin@example.com']);
        $this->session = CompetitionSession::create([
            'name' => 'Session 2026',
            'is_active' => true,
            'r2_locked' => false,
            'registration_mode' => 'open',
        ]);
        $this->category = Category::create(['code' => 'ICT', 'name' => 'ICT']);
    }

    private function project(array $attributes = []): Project
    {
        return Project::create(array_merge([
            'session_id' => $this->session->id,
            'category_id' => $this->category->id,
            'user_id' => User::factory()->create(['role_id' => 4])->id,
            'title' => 'Smart Farm',
            'abstract' => 'Abstract',
            'institution_type' => 'utem',
            'status' => Project::STATUS_APPROVED,
            'supervisor_name' => 'Supervisor',
            'supervisor_email' => 'supervisor@example.com',
        ], $attributes));
    }

    public function test_settings_page_shows_status_and_events_to_admins_only(): void
    {
        $this->actingAs($this->admin)->get(route('admin.email.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/email/Index')
                ->where('settings.enabled', true)
                ->has('settings.events', count(EmailNotifications::EVENTS))
                ->where('settings.events.0.key', 'judge_assigned')
                ->where('status.mailer', 'array'));

        $lecturer = User::factory()->create(['role_id' => 2]);
        $this->actingAs($lecturer)->get(route('admin.email.index'))->assertForbidden();
    }

    public function test_admin_can_save_email_settings(): void
    {
        $this->actingAs($this->admin)->post(route('admin.email.update'), [
            'enabled' => false,
            'events' => ['judge_assigned' => false, 'score_submitted' => true],
        ])->assertSessionHasNoErrors();

        $this->assertFalse(EmailNotifications::enabled());
        $this->assertFalse(EmailNotifications::eventEnabled('judge_assigned'));
        $this->assertTrue(EmailNotifications::eventEnabled('score_submitted'));
        // Events that were not sent keep their default.
        $this->assertTrue(EmailNotifications::eventEnabled('project_approved'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'UPDATE_EMAIL_SETTINGS']);
    }

    public function test_unknown_events_are_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.email.update'), [
            'enabled' => true,
            'events' => ['not_an_event' => true],
        ])->assertSessionHasErrors('events');
    }

    public function test_notification_adds_mail_only_when_switch_and_event_are_on(): void
    {
        $user = User::factory()->create(['role_id' => 4]);
        $approved = new SystemNotification('Title', 'Message', mailEvent: 'project_approved');
        $scored = new SystemNotification('Title', 'Message', mailEvent: 'score_submitted');
        $inAppOnly = new SystemNotification('Title', 'Message');

        $this->assertContains('mail', $approved->via($user));
        $this->assertNotContains('mail', $scored->via($user), 'score_submitted is off by default');
        $this->assertSame(['database', 'broadcast'], $inAppOnly->via($user));

        EmailNotifications::update(false, []);
        $this->assertNotContains('mail', $approved->via($user));
        $this->assertContains('mail', (new SystemNotification('T', 'M', mailEvent: EmailNotifications::TEST))->via($user));
    }

    public function test_mail_message_uses_the_shared_layout(): void
    {
        $user = User::factory()->create(['role_id' => 4, 'name' => 'Aminah']);
        $mail = (new SystemNotification('Project Approved', "Your project 'Smart Farm' has been approved.", '/dashboard/projects', 'success',
            mailEvent: 'project_approved', mailData: ['project_title' => 'Smart Farm', 'project_code' => 'ICT-01']))
            ->toMail($user);

        $this->assertStringStartsWith('Project Approved | ', (string) $mail->subject);
        $this->assertSame('Hello Aminah,', $mail->greeting);
        $this->assertSame(url('/dashboard/projects'), $mail->actionUrl);
        $this->assertStringContainsString('Smart Farm', (string) $mail->render());
    }

    public function test_judge_assignment_is_emailed(): void
    {
        Notification::fake();
        $judge = User::factory()->create(['role_id' => 3]);
        $project = $this->project();

        $this->actingAs($this->admin)->post(route('admin.assignments.bulk'), [
            'round_no' => 1,
            'pairs' => [['project_id' => $project->id, 'judge_id' => $judge->id]],
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($judge, SystemNotification::class, fn ($n, array $channels) => in_array('mail', $channels, true));
    }

    public function test_submitting_a_project_emails_a_supervisor_without_an_account(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role_id' => 4]);
        $project = $this->project(['user_id' => $owner->id, 'status' => Project::STATUS_NEW, 'supervisor_email' => 'Dr.Ali@Example.com']);

        $this->actingAs($owner)->post(route('projects.submit', $project))->assertSessionHasNoErrors();

        Notification::assertSentOnDemand(
            SystemNotification::class,
            fn ($n, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'dr.ali@example.com'
                && $channels === ['mail']
        );
    }

    public function test_submitting_a_project_notifies_a_supervisor_account_in_app_and_by_email(): void
    {
        Notification::fake();
        $lecturer = User::factory()->create(['role_id' => 2, 'email' => 'supervisor@example.com']);
        $owner = User::factory()->create(['role_id' => 4]);
        $project = $this->project(['user_id' => $owner->id, 'status' => Project::STATUS_NEW]);

        $this->actingAs($owner)->post(route('projects.submit', $project))->assertSessionHasNoErrors();

        Notification::assertSentTo($lecturer, SystemNotification::class, fn ($n, array $channels) => $channels === ['database', 'broadcast', 'mail']);
    }

    public function test_results_published_is_sent_once_per_session(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role_id' => 4]);
        $this->project(['user_id' => $owner->id]);
        $this->project(['user_id' => $owner->id, 'title' => 'Second']);
        $this->project(['status' => Project::STATUS_SUBMITTED, 'title' => 'Not approved']);

        $this->actingAs($this->admin)->post(route('admin.round2.lockToggle'), ['locked' => true]);
        $this->actingAs($this->admin)->post(route('admin.round2.lockToggle'), ['locked' => false]);
        $this->actingAs($this->admin)->post(route('admin.round2.lockToggle'), ['locked' => true]);

        // One owner with two projects, notified once, despite finalizing twice.
        Notification::assertSentToTimes($owner, SystemNotification::class, 1);
        Notification::assertCount(1);
    }

    public function test_announcement_can_also_be_emailed(): void
    {
        Notification::fake();
        $judge = User::factory()->create(['role_id' => 3]);

        $this->actingAs($this->admin)->post(route('admin.announcements.send'), [
            'title' => 'Briefing',
            'message' => 'Judges briefing at 9am.',
            'target_role' => 'judge',
            'type' => 'info',
            'send_email' => true,
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($judge, SystemNotification::class, fn ($n, array $channels) => in_array('mail', $channels, true));

        Notification::fake();
        $this->actingAs($this->admin)->post(route('admin.announcements.send'), [
            'title' => 'Briefing',
            'message' => 'In-app only.',
            'target_role' => 'judge',
            'type' => 'info',
        ]);

        Notification::assertSentTo($judge, SystemNotification::class, fn ($n, array $channels) => ! in_array('mail', $channels, true));
    }

    public function test_test_email_is_sent_to_the_admin_even_when_email_is_off(): void
    {
        Notification::fake();
        EmailNotifications::update(false, []);

        $this->actingAs($this->admin)->post(route('admin.email.test'))->assertSessionHasNoErrors();

        Notification::assertSentTo($this->admin, SystemNotification::class, fn ($n, array $channels) => $channels === ['mail']);
    }

    public function test_nudge_is_refused_when_email_is_off(): void
    {
        EmailNotifications::update(false, []);

        $this->actingAs($this->admin)->post(route('admin.judges.nudge'))
            ->assertSessionHasErrors('error');
    }
}
