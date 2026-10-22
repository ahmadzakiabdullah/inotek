<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(string $id): RedirectResponse
    {
        auth()->user()->unreadNotifications()->where('id', $id)->update(['read_at' => now()]);

        return back();
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /**
     * Display the announcement composer interface.
     */
    public function createAnnouncement(): Response
    {
        return Inertia::render('admin/announcements/Index');
    }

    /**
     * Send broadcasted announcement to target roles.
     */
    public function sendAnnouncement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'message' => 'required|string|max:500',
            'target_role' => 'required|string|in:all,admin,lecturer,judge,user',
            'type' => 'required|string|in:info,success,warning,error',
        ]);

        $query = User::query();

        if ($validated['target_role'] !== 'all') {
            $query->withRole($validated['target_role']);
        }

        $users = $query->get();
        $senderName = auth()->user()->name;

        foreach ($users as $user) {
            try {
                $user->notify(new SystemNotification(
                    $validated['title'],
                    $validated['message'],
                    '#',
                    $validated['type']
                ));
            } catch (\Exception $e) {
                // Keep resilient
            }
        }

        AuditLogger::log(
            'BROADCAST_ANNOUNCEMENT',
            "Admin '{$senderName}' broadcasted announcement: '{$validated['title']}' to target '{$validated['target_role']}'"
        );

        return back()->with('success', 'Announcement has been broadcasted successfully in real-time!');
    }
}
