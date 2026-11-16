<?php

namespace App\Services;

use App\Models\CompetitionSession;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Notifications that are not tied to a single recipient account.
 */
class CompetitionNotifier
{
    public const SETTING_RESULTS_NOTIFIED_PREFIX = 'results_notified_session_';

    /**
     * Tell the supervisor named on a project that it awaits their review.
     *
     * A supervisor with an account gets an in-app notification (and email when
     * enabled); one without an account gets the email only.
     */
    public function projectSubmitted(Project $project): void
    {
        $email = strtolower(trim((string) $project->supervisor_email));
        $owner = $project->user;

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // A participant who names themselves as supervisor gets nothing extra.
        if ($owner && strtolower((string) $owner->email) === $email) {
            return;
        }

        $studentName = $owner?->name ?: 'A participant';
        $notification = new SystemNotification(
            'Project Awaiting Your Review',
            "{$studentName} has submitted the project '{$project->title}' ({$project->pcode}) and named you as supervisor. Please review it in INOTEK.",
            '/dashboard/approvals',
            'info',
            mailEvent: 'project_submitted',
            mailData: [
                'participant_name' => $studentName,
                'project_title' => $project->title,
                'project_code' => $project->pcode,
            ],
        );

        $supervisor = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($supervisor) {
            $supervisor->notify($notification);
        } else {
            Notification::route('mail', $email)->notify($notification);
        }
    }

    /**
     * Tell project owners that results are out. Runs once per session, so
     * reopening and finalizing Round 2 again does not repeat it.
     *
     * @return int Number of participants notified.
     */
    public function resultsPublished(CompetitionSession $session): int
    {
        $flag = self::SETTING_RESULTS_NOTIFIED_PREFIX.$session->id;

        if (filter_var(Setting::get($flag, '0'), FILTER_VALIDATE_BOOLEAN)) {
            return 0;
        }

        Setting::set($flag, '1', 'boolean');

        $owners = Project::query()
            ->where('session_id', $session->id)
            ->where('status', Project::STATUS_APPROVED)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');

        foreach ($owners as $owner) {
            $owner->notify(new SystemNotification(
                'Competition Results Published',
                "The results of {$session->name} have been published. Log in to see your project's result, certificates and the judges' feedback.",
                '/dashboard/projects',
                'success',
                mailEvent: 'results_published',
                mailData: ['session_name' => $session->name],
            ));
        }

        return $owners->count();
    }
}
