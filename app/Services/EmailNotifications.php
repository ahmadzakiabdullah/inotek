<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Decides which system notifications are also delivered by email.
 *
 * In-app notifications are always sent. Email is added on top when the
 * master switch is on and the notification's event is enabled.
 */
final class EmailNotifications
{
    public const SETTING_ENABLED = 'mail_enabled';

    public const SETTING_EVENT_PREFIX = 'mail_event_';

    /** Announcements are emailed when the admin ticks "Also send by email". */
    public const ANNOUNCEMENT = 'announcement';

    /** The test email ignores every switch: it exists to check delivery. */
    public const TEST = 'test';

    /**
     * Events that can send email, in the order the settings page lists them.
     *
     * @var array<string, array{label: string, recipient: string, description: string, default: bool}>
     */
    public const EVENTS = [
        'judge_assigned' => [
            'label' => 'Judge assigned',
            'recipient' => 'Judge',
            'description' => 'One email per judge per assignment action, however many projects were assigned.',
            'default' => true,
        ],
        'project_submitted' => [
            'label' => 'Project submitted for review',
            'recipient' => 'Supervisor',
            'description' => 'Sent to the supervisor email entered on the project, even if the supervisor has no account.',
            'default' => true,
        ],
        'project_approved' => [
            'label' => 'Project approved',
            'recipient' => 'Participant',
            'description' => 'Sent to the project owner when a reviewer approves the project.',
            'default' => true,
        ],
        'project_returned' => [
            'label' => 'Project returned for editing',
            'recipient' => 'Participant',
            'description' => 'Sent to the project owner with the reviewer\'s comments.',
            'default' => true,
        ],
        'results_published' => [
            'label' => 'Results published',
            'recipient' => 'Participants',
            'description' => 'Sent once per session to the owners of approved projects when Round 2 is finalized.',
            'default' => true,
        ],
        'score_submitted' => [
            'label' => 'Evaluation submitted',
            'recipient' => 'Administrators',
            'description' => 'One email per evaluation, so this can be many emails during judging.',
            'default' => false,
        ],
    ];

    public static function enabled(): bool
    {
        return filter_var(Setting::get(self::SETTING_ENABLED, '1'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function eventEnabled(string $event): bool
    {
        if (! array_key_exists($event, self::EVENTS)) {
            return false;
        }

        $default = self::EVENTS[$event]['default'] ? '1' : '0';

        return filter_var(Setting::get(self::SETTING_EVENT_PREFIX.$event, $default), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether a notification tagged with this event should also go by email.
     */
    public static function shouldSend(?string $event): bool
    {
        if ($event === null) {
            return false;
        }

        if ($event === self::TEST) {
            return true;
        }

        if (! self::enabled()) {
            return false;
        }

        return $event === self::ANNOUNCEMENT || self::eventEnabled($event);
    }

    /**
     * @return array<int, array{key: string, label: string, recipient: string, description: string, enabled: bool}>
     */
    public static function events(): array
    {
        $events = [];

        foreach (self::EVENTS as $key => $event) {
            $events[] = [
                'key' => $key,
                'label' => $event['label'],
                'recipient' => $event['recipient'],
                'description' => $event['description'],
                'enabled' => self::eventEnabled($key),
            ];
        }

        return $events;
    }

    /**
     * @param  array<string, bool>  $events
     */
    public static function update(bool $enabled, array $events): void
    {
        Setting::set(self::SETTING_ENABLED, $enabled ? '1' : '0', 'boolean');

        foreach (array_keys(self::EVENTS) as $key) {
            if (array_key_exists($key, $events)) {
                Setting::set(self::SETTING_EVENT_PREFIX.$key, $events[$key] ? '1' : '0', 'boolean');
            }
        }
    }
}
