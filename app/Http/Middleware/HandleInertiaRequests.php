<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => \App\Models\Setting::get('system_name', config('app.name', 'Laravel')),
            'version' => config('app.version'),
            'settings' => [
                'system_name' => \App\Models\Setting::get('system_name', config('app.name', 'Laravel')),
                'global_font' => \App\Models\Setting::get('global_font', 'Inter'),
                'primary_color' => \App\Models\Setting::get('primary_color', '#4f46e5'),
                'date_format' => \App\Models\Setting::get('date_format', 'd/m/Y'),
                'timezone' => \App\Models\Setting::get('timezone', 'Asia/Kuala_Lumpur'),
            ],
            'auth' => [
                'user' => $request->user() ? array_merge($request->user()->toArray(), [
                    'role' => $request->user()->role?->name,
                ]) : null,
                'notifications' => $request->user() 
                    ? $request->user()->unreadNotifications()->orderBy('created_at', 'desc')->get()->map(function ($n) {
                        return [
                            'id' => $n->id,
                            'title' => $n->data['title'] ?? 'Notification',
                            'desc' => $n->data['message'] ?? '',
                            'action_url' => $n->data['action_url'] ?? '#',
                            'type' => $n->data['type'] ?? 'info',
                            'avatar' => $n->data['avatar'] ?? null,
                            'date' => $n->created_at->diffForHumans(),
                            'unread_message' => $n->read_at === null,
                        ];
                    })
                    : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
