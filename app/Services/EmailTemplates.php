<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Mime\Email;

/**
 * Editable subject and message for each notification email.
 *
 * The layout (greeting, button, signature) is fixed; admins edit only the
 * subject and the message paragraphs, using {placeholders}.
 */
final class EmailTemplates
{
    public const SETTING_PREFIX = 'mail_template_';

    public const MAX_SUBJECT = 200;

    public const MAX_BODY = 5000;

    public const SETTING_FOOTER = 'mail_footer';

    public const MAX_FOOTER = 300;

    /** Placeholders: {year}, {system_name}. */
    public const DEFAULT_FOOTER = '© {year} {system_name} · Universiti Teknikal Malaysia Melaka';

    /**
     * Placeholders every template can use.
     *
     * @var array<string, array{description: string, sample: string}>
     */
    public const COMMON_PLACEHOLDERS = [
        'name' => ['description' => 'Recipient\'s name (empty for a supervisor without an account)', 'sample' => 'Aminah binti Ali'],
        'system_name' => ['description' => 'System name from System Settings', 'sample' => 'INOTEK'],
    ];

    /**
     * @var array<string, array{subject: string, body: string, button: string, placeholders: array<string, array{description: string, sample: string}>}>
     */
    public const TEMPLATES = [
        'judge_assigned' => [
            'subject' => 'New Judging Assignment | {system_name}',
            'body' => "You have been assigned {projects} to evaluate in Round {round}.\n\nPlease sign in to complete your evaluations.",
            'button' => 'Judge evaluation list',
            'placeholders' => [
                'projects' => ['description' => 'Number of projects, e.g. "1 project" or "3 projects"', 'sample' => '3 projects'],
                'round' => ['description' => 'Round number (1 or 2)', 'sample' => '1'],
            ],
        ],
        'project_submitted' => [
            'subject' => 'Project Awaiting Your Review | {system_name}',
            'body' => "{participant_name} has submitted the project \"{project_title}\" ({project_code}) and named you as supervisor.\n\nPlease review it in {system_name}.",
            'button' => 'Project Approvals',
            'placeholders' => [
                'participant_name' => ['description' => 'Name of the participant who submitted', 'sample' => 'Muhammad Hafiz'],
                'project_title' => ['description' => 'Project title', 'sample' => 'Smart Farm Monitoring'],
                'project_code' => ['description' => 'Project code', 'sample' => 'ICT-01'],
            ],
        ],
        'project_approved' => [
            'subject' => 'Project Approved | {system_name}',
            'body' => 'Your project "{project_title}" ({project_code}) has been approved.',
            'button' => 'My Project',
            'placeholders' => [
                'project_title' => ['description' => 'Project title', 'sample' => 'Smart Farm Monitoring'],
                'project_code' => ['description' => 'Project code', 'sample' => 'ICT-01'],
            ],
        ],
        'project_returned' => [
            'subject' => 'Project Returned for Editing | {system_name}',
            'body' => "Your project \"{project_title}\" ({project_code}) has been returned for correction.\n\nReviewer's comments: {comments}\n\nPlease update the project and submit it again.",
            'button' => 'My Project',
            'placeholders' => [
                'project_title' => ['description' => 'Project title', 'sample' => 'Smart Farm Monitoring'],
                'project_code' => ['description' => 'Project code (may be empty before the first submission)', 'sample' => 'ICT-01'],
                'comments' => ['description' => 'Reviewer\'s comments', 'sample' => 'Please add the supervisor\'s phone number.'],
            ],
        ],
        'results_published' => [
            'subject' => 'Competition Results Published | {system_name}',
            'body' => "The results of {session_name} have been published.\n\nSign in to see your project's result, certificates and the judges' feedback.",
            'button' => 'My Project',
            'placeholders' => [
                'session_name' => ['description' => 'Competition session name', 'sample' => 'Sesi 2/2026'],
            ],
        ],
        'score_submitted' => [
            'subject' => 'Evaluation Submitted | {system_name}',
            'body' => '{judge_name} has submitted the Round {round} evaluation for "{project_title}" ({project_code}).',
            'button' => 'Judge Assignments',
            'placeholders' => [
                'judge_name' => ['description' => 'Judge\'s name', 'sample' => 'Dr. Lim Wei Ming'],
                'project_title' => ['description' => 'Project title', 'sample' => 'Smart Farm Monitoring'],
                'project_code' => ['description' => 'Project code', 'sample' => 'ICT-01'],
                'round' => ['description' => 'Round number (1 or 2)', 'sample' => '1'],
            ],
        ],
    ];

    public static function exists(string $event): bool
    {
        return array_key_exists($event, self::TEMPLATES);
    }

    /**
     * The saved template, or the default when none is saved.
     *
     * @return array{subject: string, body: string, customized: bool}
     */
    public static function get(string $event): array
    {
        $default = self::TEMPLATES[$event];
        $saved = json_decode((string) Setting::get(self::SETTING_PREFIX.$event, ''), true);

        if (is_array($saved) && is_string($saved['subject'] ?? null) && is_string($saved['body'] ?? null)) {
            return ['subject' => $saved['subject'], 'body' => $saved['body'], 'customized' => true];
        }

        return ['subject' => $default['subject'], 'body' => $default['body'], 'customized' => false];
    }

    public static function save(string $event, string $subject, string $body): void
    {
        Setting::set(self::SETTING_PREFIX.$event, (string) json_encode([
            'subject' => $subject,
            'body' => $body,
        ]), 'json');
    }

    public static function reset(string $event): void
    {
        Setting::where('key', self::SETTING_PREFIX.$event)->delete();
        Cache::forget('settings.'.self::SETTING_PREFIX.$event);
    }

    /**
     * @return array<string, array{description: string, sample: string}>
     */
    public static function placeholders(string $event): array
    {
        return self::COMMON_PLACEHOLDERS + self::TEMPLATES[$event]['placeholders'];
    }

    /**
     * @return array<string, string>
     */
    public static function sampleValues(string $event): array
    {
        return array_map(fn (array $placeholder) => $placeholder['sample'], self::placeholders($event));
    }

    /**
     * Placeholders in the text that this template does not offer.
     *
     * @return array<int, string>
     */
    public static function unknownPlaceholders(string $event, string $text): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $text, $matches);

        return array_values(array_unique(array_diff($matches[1], array_keys(self::placeholders($event)))));
    }

    /**
     * Fill the placeholders. Unknown ones are left as typed.
     *
     * @param  array<string, string|int|null>  $values
     */
    public static function fill(string $text, array $values): string
    {
        $replacements = [];

        foreach ($values as $key => $value) {
            $replacements['{'.$key.'}'] = (string) $value;
        }

        return strtr($text, $replacements);
    }

    public static function systemName(): string
    {
        return (string) Setting::get('system_name', config('app.name', 'INOTEK'));
    }

    /**
     * The saved footer text, with placeholders still in it.
     */
    public static function footer(): string
    {
        $footer = trim((string) Setting::get(self::SETTING_FOOTER, ''));

        return $footer !== '' ? $footer : self::DEFAULT_FOOTER;
    }

    public static function saveFooter(?string $footer): void
    {
        $footer = trim((string) $footer);
        Setting::set(self::SETTING_FOOTER, $footer === self::DEFAULT_FOOTER ? '' : $footer);
    }

    /**
     * The footer line every email ends with, placeholders filled.
     */
    public static function footerText(): string
    {
        return self::fill(self::footer(), ['year' => date('Y'), 'system_name' => self::systemName()]);
    }

    /**
     * The shared email layout: greeting, paragraphs, one button, signature.
     * The event is stamped in a header so the email log can label it.
     *
     * @param  array<int, string>  $paragraphs
     */
    public static function mailMessage(string $subject, array $paragraphs, string $recipientName, string $url, ?string $event = null, bool $isError = false): MailMessage
    {
        $systemName = self::systemName();
        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($recipientName !== '' ? "Hello {$recipientName}," : 'Hello,');

        foreach ($paragraphs as $paragraph) {
            // Keep single line breaks inside a paragraph.
            $mail->line(preg_replace('/\R/', "  \n", $paragraph) ?? $paragraph);
        }

        $mail->action("Open {$systemName}", $url)
            ->salutation("Regards,  \n{$systemName}");

        if ($event !== null) {
            $mail->withSymfonyMessage(function (Email $message) use ($event): void {
                $message->getHeaders()->addTextHeader(EmailLogRecorder::EVENT_HEADER, $event);
            });
        }

        return $isError ? $mail->error() : $mail;
    }

    /**
     * Build the email for an event from its template and values.
     *
     * @param  array<string, string|int|null>  $values
     */
    public static function render(string $event, array $values, string $recipientName, string $url): MailMessage
    {
        $template = self::get($event);
        $values = ['name' => $recipientName, 'system_name' => self::systemName()] + $values;

        return self::mailMessage(
            self::fill($template['subject'], $values),
            self::paragraphs(self::fill($template['body'], $values)),
            $recipientName,
            $url,
            $event,
        );
    }

    /**
     * Split a message into paragraphs on blank lines.
     *
     * @return array<int, string>
     */
    public static function paragraphs(string $body): array
    {
        $paragraphs = preg_split('/\R\s*\R/', trim($body)) ?: [];

        return array_values(array_filter(array_map('trim', $paragraphs), fn (string $p) => $p !== ''));
    }
}
