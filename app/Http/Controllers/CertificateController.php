<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class CertificateController extends Controller
{
    /**
     * Download the certificate of participation for an approved project.
     */
    public function downloadParticipation(Project $project)
    {
        // Enforce access control: only project user or admin can download
        $user = Auth::user();
        if ($user->role?->name !== 'admin' && $project->user_id !== $user->id) {
            abort(403, 'Unauthorized access.');
        }

        // Verify project is approved
        if ($project->status !== Project::STATUS_APPROVED) {
            return back()->withErrors(['status' => 'Certificates are only available for approved projects.']);
        }

        // Generate certificate hash if empty
        if (!$project->certificate_hash) {
            $project->certificate_hash = bin2hex(random_bytes(16));
            $project->save();
        }

        $project->load(['user', 'category', 'session', 'teamMembers']);

        $verifyUrl = route('certificates.verify', ['hash' => $project->certificate_hash]);
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($verifyUrl);

        $qrCodeData = null;
        try {
            // Fetch QR Code data and convert to base64 for reliable DomPDF embedding
            $opts = [
                "http" => [
                    "method" => "GET",
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
                ]
            ];
            $context = stream_context_create($opts);
            $img = file_get_contents($qrCodeUrl, false, $context);
            if ($img !== false) {
                $qrCodeData = base64_encode($img);
            }
        } catch (\Exception $e) {
            // Log warning or skip
        }

        $pdf = Pdf::loadView('pdf.participation', [
            'project' => $project,
            'verifyUrl' => $verifyUrl,
            'qrCodeData' => $qrCodeData,
            'date' => now()->format('d F Y'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("Certificate_Participation_{$project->pcode}.pdf");
    }

    /**
     * Download the certificate of achievement for an approved project.
     */
    public function downloadAchievement(Project $project)
    {
        // Enforce access: project owner or admin
        $user = Auth::user();
        if ($user->role?->name !== 'admin' && $project->user_id !== $user->id) {
            abort(403, 'Unauthorized access.');
        }

        // Verify project is approved and has an award level
        if ($project->status !== Project::STATUS_APPROVED) {
            return back()->withErrors(['status' => 'Certificates are only available for approved projects.']);
        }

        if (!$project->award_level) {
            return back()->withErrors(['award' => 'This project has not been assigned an achievement award level.']);
        }

        // Generate certificate hash if empty
        if (!$project->certificate_hash) {
            $project->certificate_hash = bin2hex(random_bytes(16));
            $project->save();
        }

        $project->load(['user', 'category', 'session', 'teamMembers']);

        $verifyUrl = route('certificates.verify', ['hash' => $project->certificate_hash]);
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($verifyUrl);

        $qrCodeData = null;
        try {
            $opts = [
                "http" => [
                    "method" => "GET",
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
                ]
            ];
            $context = stream_context_create($opts);
            $img = file_get_contents($qrCodeUrl, false, $context);
            if ($img !== false) {
                $qrCodeData = base64_encode($img);
            }
        } catch (\Exception $e) {
            // Log warning
        }

        $pdf = Pdf::loadView('pdf.achievement', [
            'project' => $project,
            'verifyUrl' => $verifyUrl,
            'qrCodeData' => $qrCodeData,
            'date' => now()->format('d F Y'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("Certificate_Achievement_{$project->pcode}.pdf");
    }

    /**
     * Publicly verify a certificate via its unique hash.
     */
    public function verify(string $hash): Response
    {
        $project = Project::where('certificate_hash', $hash)
            ->where('status', Project::STATUS_APPROVED)
            ->with(['user', 'category', 'session', 'teamMembers'])
            ->first();

        return Inertia::render('certificate/Verify', [
            'project' => $project,
            'verified' => !is_null($project),
            'hash' => $hash,
        ]);
    }
}
