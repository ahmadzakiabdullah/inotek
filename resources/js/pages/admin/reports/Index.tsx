import { Head, router } from '@inertiajs/react';
import { AlertTriangle, FileDown, FileSpreadsheet, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';

interface SessionOption {
    id: number;
    name: string;
    is_active: boolean;
    r2_locked: boolean;
}

interface ReportType {
    key: string;
    title: string;
    description: string;
}

interface Props {
    sessions: SessionOption[];
    selectedSessionId: number | null;
    resultsFinalized: boolean;
    reports: ReportType[];
}

export default function ReportsIndex({ sessions, selectedSessionId, resultsFinalized, reports }: Props) {
    const changeSession = (id: string) => {
        router.get('/dashboard/reports', { session_id: id }, { preserveScroll: true, preserveState: true });
    };

    const downloadUrl = (type: string, format: 'csv' | 'pdf') =>
        `/dashboard/reports/${type}.${format}?session_id=${selectedSessionId}`;

    return (
        <>
            <Head title="Reports & Export" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                            <FileDown className="h-8 w-8 text-primary" />
                            Reports &amp; Export
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Download competition data as CSV (opens in Excel) or as a printable PDF.
                        </p>
                    </div>

                    {sessions.length > 0 && (
                        <div className="space-y-1.5 sm:w-72">
                            <Label htmlFor="report-session">Competition session</Label>
                            <select
                                id="report-session"
                                value={selectedSessionId ?? ''}
                                onChange={(e) => changeSession(e.target.value)}
                                className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                {sessions.map((session) => (
                                    <option key={session.id} value={session.id}>
                                        {session.name}
                                        {session.is_active ? ' (active)' : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>

                {selectedSessionId === null ? (
                    <p className="rounded-lg border border-border/60 p-6 text-sm text-muted-foreground">
                        There are no competition sessions yet.
                    </p>
                ) : (
                    <>
                        {!resultsFinalized && (
                            <div className="flex items-start gap-2 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-sm text-amber-700 dark:text-amber-400">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                <p>
                                    Round 2 has not been finalized for this session. The results report is marked as a draft, and
                                    its ranks and awards may still change.
                                </p>
                            </div>
                        )}

                        <div className="grid gap-4 md:grid-cols-2">
                            {reports.map((report) => (
                                <Card key={report.key} className="flex flex-col border border-border/50 bg-card/60 shadow-sm">
                                    <CardHeader>
                                        <CardTitle className="text-base font-semibold">{report.title}</CardTitle>
                                        <CardDescription>{report.description}</CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex-1" />
                                    <CardFooter className="gap-2">
                                        {/* Plain links: the browser handles the file download. */}
                                        <Button asChild variant="outline" size="sm" className="gap-1.5">
                                            <a href={downloadUrl(report.key, 'csv')} aria-label={`Download ${report.title} as CSV`}>
                                                <FileSpreadsheet className="h-4 w-4" />
                                                CSV / Excel
                                            </a>
                                        </Button>
                                        <Button asChild variant="outline" size="sm" className="gap-1.5">
                                            <a href={downloadUrl(report.key, 'pdf')} aria-label={`Download ${report.title} as PDF`}>
                                                <FileText className="h-4 w-4" />
                                                PDF
                                            </a>
                                        </Button>
                                    </CardFooter>
                                </Card>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Reports & Export', href: '/dashboard/reports' },
    ],
};
