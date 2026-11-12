<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Listen for successful login events to log to audit logs
        Event::listen(Login::class, function (Login $event) {
            if (! $event->user instanceof User) {
                return;
            }

            $userName = trim((string) ($event->user->name ?? ''));
            if ($userName === '') {
                $userName = trim((string) ($event->user->getAttribute('nama') ?? ''));
            }
            if ($userName === '') {
                $userName = $event->user->username;
            }

            AuditLogger::log(
                'LOGIN',
                "User '{$userName}' (Email: {$event->user->email}) successfully logged in.",
                $event->user->id
            );
        });

        // Listen for logout events to log to audit logs
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                AuditLogger::log(
                    'LOGOUT',
                    "User '{$event->user->name}' (Email: {$event->user->email}) logged out.",
                    $event->user->id
                );
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
