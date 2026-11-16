<?php

namespace Tests\Feature\Admin;

use App\Models\EmailLog;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\EmailLogRecorder;
use App\Services\EmailTemplates;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobFailed;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EmailTemplatesAndLogsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create(['role_id' => 1, 'email' => 'admin@example.com']);
    }

    public function test_template_pages_are_admin_only(): void
    {
        $this->actingAs($this->admin)->get(route('admin.email.templates.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/email/Templates')
                ->has('templates', count(EmailTemplates::TEMPLATES))
                ->where('templates.0.slug', 'judge-assigned')
                ->where('templates.0.customized', false));

        $this->actingAs($this->admin)->get(route('admin.email.templates.edit', 'project-approved'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/email/TemplateEdit')
                ->where('event.key', 'project_approved')
                ->where('template.subject', EmailTemplates::TEMPLATES['project_approved']['subject'])
                ->where('placeholders.0.key', 'name'));

        $this->actingAs($this->admin)->get(route('admin.email.templates.edit', 'no-such-email'))->assertNotFound();

        $judge = User::factory()->create(['role_id' => 3]);
        $this->actingAs($judge)->get(route('admin.email.templates.index'))->assertForbidden();
        $this->actingAs($judge)->get(route('admin.email.logs'))->assertForbidden();
    }

    public function test_default_template_fills_the_values(): void
    {
        $judge = User::factory()->create(['role_id' => 3, 'name' => 'Dr. Lim']);
        $mail = (new SystemNotification('New Judging Assignments', 'In-app text', '/dashboard/judge/evaluations', 'info',
            mailEvent: 'judge_assigned', mailData: ['projects' => '3 projects', 'round' => 2]))->toMail($judge);

        $this->assertStringStartsWith('New Judging Assignment | ', (string) $mail->subject);
        $this->assertSame('Hello Dr. Lim,', $mail->greeting);
        $this->assertSame('You have been assigned 3 projects to evaluate in Round 2.', $mail->introLines[0]);
    }

    public function test_admin_can_edit_and_reset_a_template(): void
    {
        $this->actingAs($this->admin)->post(route('admin.email.templates.update', 'project-approved'), [
            'subject' => 'Tahniah! {project_code} diluluskan',
            'body' => "Projek \"{project_title}\" telah diluluskan.\n\nJumpa di hari pertandingan.",
        ])->assertSessionHasNoErrors();

        $owner = User::factory()->create(['role_id' => 4, 'name' => 'Aminah']);
        $mail = (new SystemNotification('Project Approved', 'In-app text', '/dashboard/projects', 'success',
            mailEvent: 'project_approved', mailData: ['project_title' => 'Smart Farm', 'project_code' => 'ICT-01']))->toMail($owner);

        $this->assertSame('Tahniah! ICT-01 diluluskan', $mail->subject);
        $this->assertSame(['Projek "Smart Farm" telah diluluskan.', 'Jumpa di hari pertandingan.'], $mail->introLines);
        $this->assertDatabaseHas('audit_logs', ['action' => 'UPDATE_EMAIL_TEMPLATE']);

        $this->actingAs($this->admin)->post(route('admin.email.templates.reset', 'project-approved'))->assertSessionHasNoErrors();
        $this->assertFalse(EmailTemplates::get('project_approved')['customized']);
    }

    public function test_unknown_placeholders_are_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.email.templates.update', 'project-approved'), [
            'subject' => 'Approved {project_code}',
            'body' => 'Dear {student_name}, see {comments}.',
        ])->assertSessionHasErrors(['body']);

        $this->assertFalse(EmailTemplates::get('project_approved')['customized']);
    }

    public function test_preview_renders_unsaved_text_with_sample_values(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.email.templates.preview', 'project-returned'), [
                'subject' => 'Returned: {project_code}',
                'body' => 'Comments: {comments}',
            ])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Hello Aminah binti Ali,', false)
            ->assertSee('Please add the supervisor', false);
    }

    public function test_emails_use_the_utem_logo_and_footer_not_laravel_branding(): void
    {
        $user = User::factory()->create(['role_id' => 4]);
        $html = (string) (new SystemNotification('Project Approved', 'Text', '/dashboard/projects', 'success',
            mailEvent: 'project_approved', mailData: ['project_title' => 'Smart Farm', 'project_code' => 'ICT-01']))
            ->toMail($user)->render();

        $this->assertStringContainsString(asset('images/email-logo.png'), $html);
        $this->assertStringContainsString('© '.date('Y').' '.EmailTemplates::systemName().' · Universiti Teknikal Malaysia Melaka', $html);
        $this->assertStringNotContainsString('laravel.com/img', $html);
        $this->assertStringNotContainsString('Laravel. All rights reserved', $html);
    }

    public function test_admin_can_change_the_footer(): void
    {
        $this->actingAs($this->admin)->post(route('admin.email.update'), [
            'enabled' => true,
            'footer' => 'Sekretariat {system_name}, FTKEK UTeM {year}',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Sekretariat '.EmailTemplates::systemName().', FTKEK UTeM '.date('Y'), EmailTemplates::footerText());

        $this->actingAs($this->admin)->post(route('admin.email.update'), [
            'enabled' => true,
            'footer' => 'Hak cipta {tahun}',
        ])->assertSessionHasErrors('footer');

        // An empty footer goes back to the default.
        $this->actingAs($this->admin)->post(route('admin.email.update'), ['enabled' => true, 'footer' => '']);
        $this->assertSame(EmailTemplates::DEFAULT_FOOTER, EmailTemplates::footer());
    }

    public function test_sent_emails_are_logged_with_their_type(): void
    {
        $user = User::factory()->create(['role_id' => 4, 'email' => 'student@example.com']);

        $user->notifyNow(new SystemNotification('Project Approved', 'Text', '/dashboard/projects', 'success',
            mailEvent: 'project_approved', mailData: ['project_title' => 'Smart Farm', 'project_code' => 'ICT-01']), ['mail']);

        $this->assertDatabaseHas('email_logs', [
            'event' => 'project_approved',
            'recipient' => 'student@example.com',
            'status' => EmailLog::STATUS_SENT,
        ]);
    }

    public function test_queued_emails_that_fail_for_good_are_logged(): void
    {
        $user = User::factory()->create(['role_id' => 4, 'email' => 'student@example.com']);
        $notification = new SystemNotification('Project Approved', 'Text', '/dashboard/projects', 'success',
            mailEvent: 'project_approved', mailData: ['project_title' => 'Smart Farm', 'project_code' => 'ICT-01']);
        $command = new SendQueuedNotifications(collect([$user]), $notification, ['mail']);

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn(['data' => ['command' => serialize($command)]]);

        app(EmailLogRecorder::class)->jobFailed(new JobFailed('database', $job, new RuntimeException('550 Relay denied')));

        $this->assertDatabaseHas('email_logs', [
            'event' => 'project_approved',
            'recipient' => 'student@example.com',
            'status' => EmailLog::STATUS_FAILED,
            'error' => '550 Relay denied',
        ]);
    }

    public function test_log_page_lists_and_filters(): void
    {
        EmailLog::record('project_approved', 'a@example.com', 'Approved', EmailLog::STATUS_SENT);
        EmailLog::record('judge_assigned', 'b@example.com', 'Assigned', EmailLog::STATUS_FAILED, 'Timeout');

        $this->actingAs($this->admin)->get(route('admin.email.logs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/email/Logs')
                ->has('logs.data', 2)
                ->where('summary.sent', 1)
                ->where('summary.failed', 1));

        $this->actingAs($this->admin)->get(route('admin.email.logs', ['status' => 'failed']))
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.recipient', 'b@example.com'));

        $this->actingAs($this->admin)->get(route('admin.email.logs', ['search' => 'a@example']))
            ->assertInertia(fn ($page) => $page->has('logs.data', 1)->where('logs.data.0.event', 'project_approved'));
    }
}
