<?php

namespace App\Concerns;

use App\Models\Category;
use App\Models\Project;
use App\Models\TeamMember;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait ProjectValidationRules
{
    /**
     * Validation rules shared by participant and admin project forms.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function projectRules(bool $withCategory = true): array
    {
        return [
            ...($withCategory ? ['category_id' => ['required', 'integer', Rule::exists('categories', 'id')]] : []),
            'title' => ['required', 'string', 'max:255'],
            'abstract' => ['required', 'string', 'max:10000'],
            'institution_type' => ['required', 'string', 'in:utem,ipt'],
            'supervisor_name' => ['required', 'string', 'max:255'],
            'supervisor_email' => ['required', 'email', 'max:255'],
            'supervisor_phone' => ['nullable', 'string', 'max:100'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'poster_url' => ['nullable', 'url', 'max:255'],
            'team_members' => ['nullable', 'array'],
            'team_members.*.name' => ['required', 'string', 'max:255'],
            'team_members.*.email' => ['nullable', 'email', 'max:255'],
            'team_members.*.phone' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Map validated input to the project's editable attributes.
     *
     * Supervisor emails are stored in lowercase so lecturer matching is
     * case-insensitive everywhere.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function projectAttributes(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'abstract' => $validated['abstract'],
            'institution_type' => $validated['institution_type'],
            'supervisor_name' => $validated['supervisor_name'],
            'supervisor_email' => Str::lower(trim($validated['supervisor_email'])),
            'supervisor_phone' => $validated['supervisor_phone'] ?? null,
            'video_url' => $validated['video_url'] ?? null,
            'poster_url' => $validated['poster_url'] ?? null,
        ];
    }

    /**
     * Replace the project's team members with the validated list.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function syncTeamMembers(Project $project, array $validated): void
    {
        $project->teamMembers()->delete();

        foreach ($validated['team_members'] ?? [] as $member) {
            TeamMember::create([
                'project_id' => $project->id,
                'name' => $member['name'],
                'email' => $member['email'] ?? null,
                'phone' => $member['phone'] ?? null,
            ]);
        }
    }

    /**
     * Reject team members for categories that do not allow teams.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function ensureTeamAllowed(?Category $category, array $validated): void
    {
        if (! $category?->allow_team && ! empty($validated['team_members'])) {
            throw ValidationException::withMessages([
                'team_members' => __('Team members are not allowed for this category.'),
            ]);
        }
    }
}
