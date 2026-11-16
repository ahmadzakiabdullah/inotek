<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * @property int $id
 * @property string|null $event
 * @property string $recipient
 * @property string|null $subject
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $created_at
 */
class EmailLog extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const UPDATED_AT = null;

    protected $fillable = ['event', 'recipient', 'subject', 'status', 'error'];

    /**
     * Write a log row without ever breaking the email flow itself.
     */
    public static function record(?string $event, string $recipient, ?string $subject, string $status, ?string $error = null): void
    {
        try {
            self::create([
                'event' => $event ? Str::limit($event, 50, '') : null,
                'recipient' => Str::limit($recipient, 255, ''),
                'subject' => $subject !== null ? Str::limit($subject, 255, '') : null,
                'status' => $status,
                'error' => $error !== null ? Str::limit($error, 2000) : null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
