<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\JudgeAutoAssigner;
use App\Services\Round2Wildcards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    /**
     * Display the settings management panel.
     */
    public function index(): Response
    {
        return Inertia::render('admin/settings/Index', [
            'currentSettings' => [
                'system_name' => Setting::get('system_name', config('app.name', 'Laravel')),
                'global_font' => Setting::get('global_font', 'Inter'),
                'primary_color' => Setting::get('primary_color', '#4f46e5'),
                'date_format' => Setting::get('date_format', 'd/m/Y'),
                'timezone' => Setting::get('timezone', 'Asia/Kuala_Lumpur'),
                'auto_assign_enabled' => JudgeAutoAssigner::isEnabled(),
                'auto_assign_judges_per_project' => JudgeAutoAssigner::judgesPerProject(),
                'round2_wildcard_limit' => Round2Wildcards::limit(),
            ],
            'maxJudgesPerProject' => JudgeAutoAssigner::MAX_JUDGES_PER_PROJECT,
            'maxRound2Wildcards' => Round2Wildcards::MAX_LIMIT,
        ]);
    }

    /**
     * Update the global system settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'system_name' => 'required|string|max:100',
            'global_font' => 'required|string|in:Inter,Roboto,Poppins,Outfit,Plus Jakarta Sans,Instrument Sans,Lora,Playfair Display',
            'primary_color' => 'required|string|regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/', // Validates hex color code
            'date_format' => 'required|string|in:d/m/Y,Y-m-d,d M Y,m/d/Y',
            'timezone' => 'required|string|timezone',
            'auto_assign_enabled' => 'sometimes|boolean',
            'auto_assign_judges_per_project' => 'sometimes|integer|min:1|max:'.JudgeAutoAssigner::MAX_JUDGES_PER_PROJECT,
            'round2_wildcard_limit' => 'sometimes|integer|min:0|max:'.Round2Wildcards::MAX_LIMIT,
        ]);

        Setting::set('system_name', $validated['system_name']);
        Setting::set('global_font', $validated['global_font']);
        Setting::set('primary_color', $validated['primary_color']);
        Setting::set('date_format', $validated['date_format']);
        Setting::set('timezone', $validated['timezone']);

        if (array_key_exists('auto_assign_enabled', $validated)) {
            Setting::set(JudgeAutoAssigner::SETTING_ENABLED, $validated['auto_assign_enabled'] ? '1' : '0', 'boolean');
        }

        if (array_key_exists('auto_assign_judges_per_project', $validated)) {
            Setting::set(JudgeAutoAssigner::SETTING_JUDGES_PER_PROJECT, (string) $validated['auto_assign_judges_per_project'], 'integer');
        }

        if (array_key_exists('round2_wildcard_limit', $validated)) {
            Setting::set(Round2Wildcards::SETTING_LIMIT, (string) $validated['round2_wildcard_limit'], 'integer');
        }

        // Log the change in system logs
        $adminName = auth()->user()->name;
        $autoAssign = JudgeAutoAssigner::isEnabled() ? 'on ('.JudgeAutoAssigner::judgesPerProject().' per project)' : 'off';
        AuditLogger::log(
            'UPDATE_SETTINGS',
            "Admin '{$adminName}' updated system settings: Name='{$validated['system_name']}', Font='{$validated['global_font']}', Color='{$validated['primary_color']}', Timezone='{$validated['timezone']}', Auto-assign={$autoAssign}, Round 2 wildcards per category=".Round2Wildcards::limit()
        );

        return $this->backWithSuccess('System configurations updated successfully in real-time!');
    }
}
