import { Head, useForm, Link } from '@inertiajs/react';
import { Sliders, Lock, Unlock, ClipboardList, CheckSquare, Sparkles, Trophy, MessageSquare } from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Project {
    id: number;
    title: string;
    pcode: string | null;
    avg_score: string;
    judges_count: number;
}

interface Round2Score {
    id: number;
    title: string;
    pcode: string | null;
    category_id: number;
    round2_total: number;
    comments: string | null;
    category?: { name: string } | null;
}

interface CompetitionSession {
    id: number;
    name: string;
    is_active: boolean;
    r2_locked: boolean;
}

interface Props {
    shortlist: Record<number, Project[]>;
    round2Scores: Round2Score[];
    activeSession: CompetitionSession | null;
    limit: number;
}

export default function Round2Index({ shortlist, round2Scores, activeSession, limit }: Props) {
    const lockForm = useForm({});
    const [currentLimit, setCurrentLimit] = useState(limit);

    const handleLockToggle = (e: React.FormEvent) => {
        e.preventDefault();

        if (!activeSession?.r2_locked && !window.confirm('Finalize Round 2 judging? Judges will no longer be able to submit or edit scores, and Round 2 assignments cannot be changed.')) {
            return;
        }

        lockForm.post('/dashboard/round2/lock', {
            onSuccess: () => {
                toast.success(
                    activeSession?.r2_locked
                        ? 'Round 2 judging reopened.'
                        : 'Round 2 judging finalized.'
                );
            },
            onError: () => {
                toast.error('Error toggling Round 2 lock status.');
            }
        });
    };

    // Helper to calculate total qualified project count across all categories
    const totalShortlisted = Object.values(shortlist).reduce((acc, list) => acc + list.length, 0);

    return (
        <>
            <Head title="Round 2 Shortlist" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                            <Trophy className="h-8 w-8 text-amber-500" />
                            Round 2 Shortlist & Scoring
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {activeSession 
                                ? `Active Session: ${activeSession.name}` 
                                : 'No active competition session.'}
                        </p>
                    </div>

                    {activeSession && (
                        <div className="flex items-center gap-2">
                            {/* Limit Adjuster Form */}
                            <form method="GET" action="/dashboard/round2" className="flex items-center gap-2 mr-2">
                                <span className="text-xs font-semibold text-muted-foreground whitespace-nowrap">Shortlist size:</span>
                                <select
                                    name="limit"
                                    value={currentLimit}
                                    onChange={(e) => {
                                        setCurrentLimit(Number(e.target.value));
                                        e.target.form?.submit();
                                    }}
                                    className="rounded-md border border-border bg-background px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                >
                                    <option value="3">Top 3 Projects</option>
                                    <option value="5">Top 5 Projects</option>
                                    <option value="10">Top 10 Projects</option>
                                </select>
                            </form>

                            {/* Finalize Toggle Button */}
                            <form onSubmit={handleLockToggle}>
                                <Button
                                    type="submit"
                                    variant={activeSession.r2_locked ? "destructive" : "default"}
                                    disabled={lockForm.processing}
                                    className="gap-1.5 h-8 text-xs font-semibold shadow-md"
                                >
                                    {activeSession.r2_locked ? (
                                        <>
                                            <Lock className="h-3.5 w-3.5" />
                                            Reopen R2
                                        </>
                                    ) : (
                                        <>
                                            <Unlock className="h-3.5 w-3.5" />
                                            Finalize R2 Judging
                                        </>
                                    )}
                                </Button>
                            </form>
                        </div>
                    )}
                </div>

                {!activeSession ? (
                    <div className="rounded-lg border border-warning/20 bg-warning/5 p-4 text-sm text-warning">
                        No active competition session found at this time.
                    </div>
                ) : (
                    <div className="space-y-6">
                        {/* Lock Warning Banner */}
                        {activeSession.r2_locked && (
                            <div className="flex items-center gap-2.5 rounded-lg border border-destructive/20 bg-destructive/5 p-3 text-xs text-destructive">
                                <Lock className="h-4 w-4 shrink-0" />
                                <div>
                                <span className="font-bold">Round 2 Finalized:</span> Scores and assignments are final. Judges cannot add or modify scores.
                                </div>
                            </div>
                        )}

                        {/* Top Shortlist Grid by Category */}
                        <div className="grid gap-6 md:grid-cols-2">
                            {Object.entries(shortlist).map(([categoryId, projects]) => {
                                // Find category name from project lists or fallback
                                const firstProject = projects[0];
                                const categoryName = firstProject ? 'Category ' + categoryId : 'Category ID ' + categoryId;
                                
                                return (
                                    <Card key={categoryId} className="border border-border/50 bg-card/60 backdrop-blur-sm shadow-md overflow-hidden">
                                        <CardHeader className="bg-primary/5 border-b border-border/40 p-4 flex flex-row items-center justify-between">
                                            <div>
                                                <CardTitle className="text-sm font-bold text-foreground">
                                                    {categoryName}
                                                </CardTitle>
                                                <CardDescription className="text-[10px]">
                                                    Showing Top {limit} ordered by average Round 1 score
                                                </CardDescription>
                                            </div>
                                            <Badge className="font-mono text-[10px] bg-primary/10 text-primary hover:bg-primary/20 border-primary/20">
                                                {projects.length} Qualified
                                            </Badge>
                                        </CardHeader>
                                        <CardContent className="p-0">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow className="border-b border-border/30 bg-muted/10 hover:bg-transparent">
                                                        <TableHead className="py-2 pl-4 text-xs font-semibold">Code</TableHead>
                                                        <TableHead className="text-xs font-semibold">Project Title</TableHead>
                                                        <TableHead className="text-center text-xs font-semibold w-[90px]">Avg R1</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {projects.length > 0 ? (
                                                        projects.map((project, idx) => (
                                                            <TableRow 
                                                                key={project.id}
                                                                className="border-b border-border/20 transition-colors hover:bg-muted/5 text-xs"
                                                            >
                                                                <TableCell className="py-2.5 pl-4 font-mono font-bold text-primary">
                                                                    {project.pcode || '-'}
                                                                </TableCell>
                                                                <TableCell className="font-medium max-w-[180px] truncate" title={project.title}>
                                                                    <div className="flex items-center gap-1.5">
                                                                        <span className="font-bold text-muted-foreground">#{idx + 1}</span>
                                                                        {project.title}
                                                                    </div>
                                                                </TableCell>
                                                                <TableCell className="text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                                                    {parseFloat(project.avg_score).toFixed(2)}%
                                                                </TableCell>
                                                            </TableRow>
                                                        ))
                                                    ) : (
                                                        <TableRow>
                                                            <TableCell colSpan={3} className="h-20 text-center text-xs text-muted-foreground italic">
                                                                No projects evaluated in Round 1 for this category.
                                                            </TableCell>
                                                        </TableRow>
                                                    )}
                                                </TableBody>
                                            </Table>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>

                        {/* Round 2 Scores Board Card */}
                        <Card className="border border-border/50 bg-card/40 backdrop-blur-sm shadow-md overflow-hidden">
                            <CardHeader className="border-b border-border/40 p-4">
                                <CardTitle className="text-lg font-bold flex items-center gap-2">
                                    <ClipboardList className="h-5 w-5 text-primary" />
                                    Round 2 Results & Evaluations
                                </CardTitle>
                                <CardDescription>Projects scored by judges in Round 2.</CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="border-b border-border/40 bg-muted/20 hover:bg-transparent">
                                            <TableHead className="py-3 pl-4 font-semibold w-[100px]">Project Code</TableHead>
                                            <TableHead className="font-semibold">Project Title</TableHead>
                                            <TableHead className="font-semibold">Category</TableHead>
                                            <TableHead className="text-center font-semibold w-[120px]">Round 2 Score</TableHead>
                                            <TableHead className="font-semibold">Judge's Comments</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {round2Scores.length > 0 ? (
                                            round2Scores.map((score) => (
                                                <TableRow 
                                                    key={score.id}
                                                    className="border-b border-border/30 transition-colors hover:bg-muted/10"
                                                >
                                                    <TableCell className="py-3 pl-4 font-mono font-bold text-primary">
                                                        {score.pcode || '-'}
                                                    </TableCell>
                                                    <TableCell className="font-medium text-xs max-w-[200px] truncate" title={score.title}>
                                                        {score.title}
                                                    </TableCell>
                                                    <TableCell className="text-xs">
                                                        {score.category?.name ?? 'N/A'}
                                                    </TableCell>
                                                    <TableCell className="text-center font-mono font-bold text-base text-primary">
                                                        {score.round2_total}%
                                                    </TableCell>
                                                    <TableCell className="text-xs text-muted-foreground italic max-w-[250px] truncate" title={score.comments || ''}>
                                                        {score.comments ? (
                                                            <span className="flex items-center gap-1">
                                                                <MessageSquare className="h-3.5 w-3.5 shrink-0" />
                                                                {score.comments}
                                                            </span>
                                                        ) : (
                                                            '-'
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        ) : (
                                            <TableRow>
                                                <TableCell colSpan={5} className="h-24 text-center text-xs text-muted-foreground">
                                                    No evaluation scores submitted for Round 2 yet.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </>
    );
}

Round2Index.layout = {
    breadcrumbs: [
        {
            title: 'Admin Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Round 2 Shortlist',
            href: '/dashboard/round2',
        },
    ],
};
