<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the audit logs.
     */
    public function index(): Response
    {
        $logs = AuditLog::with('user')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('admin/audit-logs/Index', [
            'logs' => $logs,
        ]);
    }
}
