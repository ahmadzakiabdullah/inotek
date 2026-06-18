<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemSettingsController extends Controller
{
    /**
     * Display the settings management panel.
     */
    public function index()
    {
        return Inertia::render('admin/settings/Index', [
            'currentSettings' => [
                'system_name' => Setting::get('system_name', config('app.name', 'Laravel')),
                'global_font' => Setting::get('global_font', 'Inter'),
                'primary_color' => Setting::get('primary_color', '#4f46e5'),
                'date_format' => Setting::get('date_format', 'd/m/Y'),
                'timezone' => Setting::get('timezone', 'Asia/Kuala_Lumpur'),
            ]
        ]);
    }

    /**
     * Update the global system settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'system_name' => 'required|string|max:100',
            'global_font' => 'required|string|in:Inter,Roboto,Poppins,Outfit,Plus Jakarta Sans,Instrument Sans,Lora,Playfair Display',
            'primary_color' => 'required|string|regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/', // Validates hex color code
            'date_format' => 'required|string|in:d/m/Y,Y-m-d,d M Y,m/d/Y',
            'timezone' => 'required|string|timezone',
        ]);

        Setting::set('system_name', $validated['system_name']);
        Setting::set('global_font', $validated['global_font']);
        Setting::set('primary_color', $validated['primary_color']);
        Setting::set('date_format', $validated['date_format']);
        Setting::set('timezone', $validated['timezone']);

        // Log the change in system logs
        $adminName = auth()->user()->name;
        \App\Services\AuditLogger::log(
            'UPDATE_SETTINGS',
            "Admin '{$adminName}' updated system settings: Name='{$validated['system_name']}', Font='{$validated['global_font']}', Color='{$validated['primary_color']}', Timezone='{$validated['timezone']}'"
        );

        return back()->with('success', 'System configurations updated successfully in real-time!');
    }
}
