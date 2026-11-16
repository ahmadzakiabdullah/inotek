<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\EmailNotifications;
use App\Services\EmailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $title;

    protected string $message;

    protected ?string $actionUrl;

    protected ?string $avatar;

    protected string $type;

    /**
     * The email event (see EmailNotifications::EVENTS) that decides whether
     * this notification is also emailed. Null means in-app only.
     */
    protected ?string $mailEvent = null;

    /**
     * Values for the email template placeholders (see EmailTemplates).
     *
     * @var array<string, string|int|null>
     */
    protected array $mailData = [];

    /**
     * Create a new notification instance.
     *
     * @param  array<string, string|int|null>  $mailData
     */
    public function __construct(string $title, string $message, ?string $actionUrl = null, string $type = 'info', ?string $avatar = null, ?string $mailEvent = null, array $mailData = [])
    {
        $this->title = $title;
        $this->message = $message;
        $this->actionUrl = $actionUrl;
        $this->type = $type;
        $this->avatar = $avatar;
        $this->mailEvent = $mailEvent;
        $this->mailData = $mailData;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // On-demand recipients (for example a supervisor without an account)
        // have no inbox in the app, only an email address.
        $channels = $notifiable instanceof AnonymousNotifiable ? [] : ['database', 'broadcast'];

        if (EmailNotifications::shouldSend($this->mailEvent) && $this->hasMailAddress($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'type' => $this->type,
            'avatar' => $this->avatar,
        ];
    }

    /**
     * Every email shares the same layout. Events with a template use the
     * admin-editable subject and message; others (announcements, the test
     * email) use the notification's own title and message.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->actionUrl && $this->actionUrl !== '#' ? url($this->actionUrl) : url('/dashboard');
        $name = $notifiable instanceof User ? trim((string) $notifiable->name) : '';

        if ($this->mailEvent !== null && EmailTemplates::exists($this->mailEvent)) {
            return EmailTemplates::render($this->mailEvent, $this->mailData, $name, $url);
        }

        return EmailTemplates::mailMessage(
            "{$this->title} | ".EmailTemplates::systemName(),
            EmailTemplates::paragraphs($this->message) ?: [$this->message],
            $name,
            $url,
            $this->mailEvent,
            $this->type === 'error',
        );
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => static::class,
            'data' => $this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function mailEvent(): ?string
    {
        return $this->mailEvent;
    }

    private function hasMailAddress(object $notifiable): bool
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return false;
        }

        $address = $notifiable->routeNotificationFor('mail', $this);

        return is_string($address) ? trim($address) !== '' : ! empty($address);
    }
}
