<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Mark a specific notification as read.
     */
    public function markAsRead($id)
    {
        auth()->user()->unreadNotifications()->where('id', $id)->update(['read_at' => now()]);
        return back();
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        return back();
    }

    /**
     * Display the announcement composer interface.
     */
    public function createAnnouncement()
    {
        return \Inertia\Inertia::render('admin/announcements/Index');
    }

    /**
     * Send broadcasted announcement to target roles.
     */
    public function sendAnnouncement(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'message' => 'required|string|max:500',
            'target_role' => 'required|string|in:all,admin,lecturer,judge,user',
            'type' => 'required|string|in:info,success,warning,error',
        ]);

        $query = \App\Models\User::query();

        if ($validated['target_role'] !== 'all') {
            $roleMap = [
                'admin' => 1,
                'lecturer' => 2,
                'judge' => 3,
                'user' => 4,
            ];
            $query->where('role_id', $roleMap[$validated['target_role']]);
        }

        $users = $query->get();
        $senderName = auth()->user()->name;

        foreach ($users as $user) {
            try {
                $user->notify(new \App\Notifications\SystemNotification(
                    $validated['title'],
                    $validated['message'],
                    '#',
                    $validated['type']
                ));
            } catch (\Exception $e) {
                // Keep resilient
            }
        }

        \App\Services\AuditLogger::log(
            'BROADCAST_ANNOUNCEMENT',
            "Admin '{$senderName}' broadcasted announcement: '{$validated['title']}' to target '{$validated['target_role']}'"
        );

        return back()->with('success', 'Announcement has been broadcasted successfully in real-time!');
    }
}
