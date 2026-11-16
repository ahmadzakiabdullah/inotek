<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Notifications\SystemNotification;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobFailed;
use Symfony\Component\Mime\Address;
use Throwable;

/**
 * Writes the email log: one row per recipient of every email the mail
 * server accepted, and one row per recipient of a queued notification email
 * that failed for good.
 */
class EmailLogRecorder
{
    /** Header that tells the log which notification an email belongs to. */
    public const EVENT_HEADER = 'X-INOTEK-Event';

    public const EVENT_OTHER = 'other';

    public function messageSent(MessageSent $event): void
    {
        $message = $event->message;
        $header = $message->getHeaders()->get(self::EVENT_HEADER);
        $eventKey = $header ? $header->getBodyAsString() : self::EVENT_OTHER;
        $subject = $message->getSubject();

        $addresses = array_merge($message->getTo(), $message->getCc(), $message->getBcc());

        foreach ($addresses as $address) {
            /** @var Address $address */
            EmailLog::record($eventKey, $address->getAddress(), $subject, EmailLog::STATUS_SENT);
        }
    }

    /**
     * A queued notification email that ran out of retries.
     */
    public function jobFailed(JobFailed $event): void
    {
        try {
            $payload = $event->job->payload();
            $serialized = $payload['data']['command'] ?? null;

            if (! is_string($serialized) || ! str_contains($serialized, SendQueuedNotifications::class)) {
                return;
            }

            $command = unserialize($serialized);

            if (! $command instanceof SendQueuedNotifications || ! in_array('mail', (array) $command->channels, true)) {
                return;
            }

            $notification = $command->notification;
            $eventKey = $notification instanceof SystemNotification ? ($notification->mailEvent() ?? self::EVENT_OTHER) : self::EVENT_OTHER;

            foreach ($command->notifiables as $notifiable) {
                if (! is_object($notifiable)) {
                    continue;
                }

                $recipient = method_exists($notifiable, 'routeNotificationFor')
                    ? $notifiable->routeNotificationFor('mail', $notification)
                    : null;

                $subject = null;
                if (method_exists($notification, 'toMail')) {
                    try {
                        $subject = $notification->toMail($notifiable)->subject;
                    } catch (Throwable) {
                        // The subject is only informative.
                    }
                }

                EmailLog::record(
                    $eventKey,
                    is_string($recipient) ? $recipient : 'unknown',
                    $subject,
                    EmailLog::STATUS_FAILED,
                    $event->exception->getMessage(),
                );
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
