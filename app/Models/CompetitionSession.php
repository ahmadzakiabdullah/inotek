<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CompetitionSession extends Model
{
    protected $table = 'competition_sessions';

    protected $fillable = [
        'name',
        'is_active',
        'registration_mode',
        'session_starts_at',
        'session_ends_at',
        'registration_opens_at',
        'registration_closes_at',
        'r2_locked',
        'round2_shortlist_limit',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'session_starts_at' => 'date',
        'session_ends_at' => 'date',
        'registration_opens_at' => 'date',
        'registration_closes_at' => 'date',
        'r2_locked' => 'boolean',
        'round2_shortlist_limit' => 'integer',
    ];

    protected $appends = ['registration_is_open'];

    /**
     * The currently active competition session, resolved once per request.
     */
    public static function active(): ?self
    {
        $request = app('request');
        $cached = $request->attributes->get('inotek.active_session', false);

        if ($cached === false) {
            $cached = static::where('is_active', true)->first();
            $request->attributes->set('inotek.active_session', $cached);
        }

        return $cached;
    }

    /**
     * Forget the per-request active session after it changes.
     */
    public static function forgetActive(): void
    {
        app('request')->attributes->remove('inotek.active_session');
    }

    public function isRegistrationOpen(): bool
    {
        $today = Carbon::today(config('app.timezone'));

        if ($this->session_starts_at && $today->lt($this->session_starts_at)) {
            return false;
        }

        if ($this->session_ends_at && $today->gt($this->session_ends_at)) {
            return false;
        }

        if ($this->registration_mode === 'open') {
            return true;
        }

        if ($this->registration_mode !== 'scheduled'
            || ! $this->registration_opens_at
            || ! $this->registration_closes_at) {
            return false;
        }

        return ! $today->lt($this->registration_opens_at)
            && ! $today->gt($this->registration_closes_at);
    }

    public function getRegistrationIsOpenAttribute(): bool
    {
        return $this->isRegistrationOpen();
    }

}
