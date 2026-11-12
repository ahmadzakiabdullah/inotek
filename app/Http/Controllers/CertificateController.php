<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CertificateController extends Controller
{
    /**
     * Download the certificate of participation for an approved project.
     */
    public function downloadParticipation(Project $project): SymfonyResponse
    {
        $this->authorizeDownload($project);

        if ($project->status !== Project::STATUS_APPROVED) {
            return back()->withErrors(['status' => 'Certificates are only available for approved projects.']);
        }

        return $this->renderCertificate($project, 'pdf.participation', 'Certificate_Participation');
    }

    /**
     * Download the certificate of achievement for an approved project.
     */
    public function downloadAchievement(Project $project): SymfonyResponse
    {
        $user = $this->authorizeDownload($project);

        if ($project->status !== Project::STATUS_APPROVED) {
            return back()->withErrors(['status' => 'Certificates are only available for approved projects.']);
        }

        if (! $project->award_level) {
            return back()->withErrors(['award' => 'This project has not been assigned an achievement award level.']);
        }

        // Awards stay confidential until the results are finalized.
        if (! $user->hasRole('admin') && ! $project->session?->r2_locked) {
            abort(403, 'Achievement certificates are available after the results are announced.');
        }

        return $this->renderCertificate($project, 'pdf.achievement', 'Certificate_Achievement');
    }

    /**
     * Publicly verify a certificate via its unique hash.
     *
     * Only the fields shown on the verification page are exposed, so no
     * contact details or internal comments reach the public HTML payload.
     */
    public function verify(string $hash): Response
    {
        $project = Project::where('certificate_hash', $hash)
            ->where('status', Project::STATUS_APPROVED)
            ->with(['user:id,name', 'category:id,name,code', 'session:id,name,r2_locked', 'teamMembers:id,project_id,name'])
            ->first();

        return Inertia::render('certificate/Verify', [
            'verified' => ! is_null($project),
            'hash' => $hash,
            'project' => $project ? [
                'id' => $project->id,
                'title' => $project->title,
                'pcode' => $project->pcode,
                'status' => $project->status,
                'certificate_hash' => $project->certificate_hash,
                'award_level' => $project->session?->r2_locked ? $project->award_level : null,
                'user' => ['name' => $project->user?->name],
                'category' => ['name' => $project->category?->name, 'code' => $project->category?->code],
                'session' => ['name' => $project->session?->name],
                'team_members' => $project->teamMembers->map(fn ($member) => ['name' => $member->name])->values()->all(),
            ] : null,
        ]);
    }

    /**
     * Only the project owner or an admin may download certificates.
     */
    private function authorizeDownload(Project $project): User
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->hasRole('admin') && $project->user_id !== $user->id) {
            abort(403, 'Unauthorized access.');
        }

        return $user;
    }

    private function renderCertificate(Project $project, string $view, string $filePrefix): SymfonyResponse
    {
        if (! $project->certificate_hash) {
            $project->certificate_hash = bin2hex(random_bytes(16));
            $project->save();
        }

        $project->load(['user', 'category', 'session', 'teamMembers']);

        $verifyUrl = route('certificates.verify', ['hash' => $project->certificate_hash]);

        $pdf = Pdf::loadView($view, [
            'project' => $project,
            'verifyUrl' => $verifyUrl,
            'qrCodeData' => $this->qrCodePng($verifyUrl),
            'date' => now()->format('d F Y'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("{$filePrefix}_{$project->pcode}.pdf");
    }

    /**
     * Generate the QR code locally so the verification URL is never sent to a
     * third-party service and downloads cannot hang on an external request.
     */
    private function qrCodePng(string $data): ?string
    {
        try {
            return base64_encode((new Writer(new GDLibRenderer(240, 1)))->writeString($data));
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
