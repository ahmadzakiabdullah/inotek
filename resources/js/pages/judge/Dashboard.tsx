import { Head, Link } from '@inertiajs/react';
import { ClipboardCheck, Play, Award, CheckCircle, FileSpreadsheet, Hourglass, Search, Sparkles } from 'lucide-react';
import React, { useMemo, useState } from 'react';
import DashboardNotifications from '@/components/dashboard-notifications';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Assignment {
    id: number;
    title: string;
    pcode: string | null;
    category_name: string;
    team_count: number;
    round_no: number;
    is_evaluated: boolean;
    total_score: number | null;
    assignment_id: number;
}

interface CompetitionSession {
    id: number;
    name: string;
    is_active: boolean;
    r2_locked: boolean;
}

interface Props {
    assignments: Assignment[];
    activeSession: CompetitionSession | null;
    error?: string;
}

export default function JudgeDashboard({ assignments, activeSession, error }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');

    const stats = useMemo(() => {
        const total = assignments.length;
        const evaluated = assignments.filter((a) => a.is_evaluated).length;
        const pending = total - evaluated;

        return { total, evaluated, pending };
    }, [assignments]);

    const progressPercentage = stats.total > 0 ? Math.round((stats.evaluated / stats.total) * 100) : 100;

    const filteredAssignments = useMemo(() => {
        return assignments.filter((assignment) => {
            const matchesSearch = 
                assignment.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (assignment.pcode && assignment.pcode.toLowerCase().includes(searchQuery.toLowerCase())) ||
                assignment.category_name.toLowerCase().includes(searchQuery.toLowerCase());
            
            const matchesStatus = 
                statusFilter === 'all' ||
                (statusFilter === 'pending' && !assignment.is_evaluated) ||
                (statusFilter === 'completed' && assignment.is_evaluated);
                
            return matchesSearch && matchesStatus;
        });
    }, [assignments, searchQuery, statusFilter]);

    return (
        <>
            <Head title="Judge Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-2">
                    <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                        <ClipboardCheck className="h-8 w-8 text-primary" />
                        Judge Portal
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {activeSession 
                            ? `Active Session: ${activeSession.name}` 
                            : 'No active competition session.'}
                    </p>
                </div>

                <DashboardNotifications />

                {error && (
                    <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-sm text-destructive">
                        {error}
                    </div>
                )}

                {stats.total > 0 && (
                    <Card className="relative overflow-hidden border border-border/50 bg-gradient-to-r from-card to-primary/5 p-6 backdrop-blur-sm shadow-md transition-all duration-300">
                        {/* Visual gradient backdrop */}
                        <div className="absolute -top-24 -right-24 h-48 w-48 rounded-full bg-primary/10 blur-3xl pointer-events-none" />
                        <CardContent className="p-0 flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div className="space-y-2 flex-1">
                                <h3 className="text-lg font-bold flex items-center gap-2 text-foreground">
                                    {progressPercentage === 100 ? (
                                        <span className="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                                            <Sparkles className="h-5 w-5 text-emerald-500 animate-pulse" />
                                            All Appraisals Completed!
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-2">
                                            <Award className="h-5 w-5 text-primary" />
                                            Your Evaluation Progress
                                        </span>
                                    )}
                                </h3>
                                <p className="text-sm text-muted-foreground max-w-xl leading-relaxed">
                                    {progressPercentage === 100
                                        ? "Outstanding! You have evaluated all assigned projects. Thank you for helping us finalize the results!"
                                        : progressPercentage >= 50
                                        ? "Excellent progress! You've evaluated more than half of your assigned projects. Keep going!"
                                        : "Welcome to your evaluation workspace. Please complete the remaining projects at your earliest convenience."}
                                </p>
                            </div>
                            <div className="w-full md:w-[320px] space-y-2 shrink-0">
                                <div className="flex justify-between text-sm font-semibold">
                                    <span>{progressPercentage}% Complete</span>
                                    <span className="text-muted-foreground">{stats.evaluated} / {stats.total} Projects</span>
                                </div>
                                <Progress 
                                    value={progressPercentage} 
                                    className="h-3"
                                    indicatorColor={
                                        progressPercentage === 100 
                                            ? "bg-emerald-500" 
                                            : progressPercentage >= 50 
                                                ? "bg-primary" 
                                                : "bg-amber-500"
                                    }
                                />
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Stats cards */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Assigned Projects
                            </CardTitle>
                            <FileSpreadsheet className="h-4 w-4 text-primary" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total}</div>
                            <p className="mt-1 text-xs text-muted-foreground">Projects for evaluation</p>
                        </CardContent>
                    </Card>

                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Evaluated
                            </CardTitle>
                            <CheckCircle className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                                {stats.evaluated}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">Scores submitted successfully</p>
                        </CardContent>
                    </Card>

                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Pending Evaluation
                            </CardTitle>
                            <Hourglass className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-amber-600 dark:text-amber-400 font-mono">
                                {stats.pending}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">Awaiting evaluation</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Assignments table */}
                <Card className="overflow-hidden border border-border/50 bg-card/40 backdrop-blur-sm">
                    <CardHeader className="border-b border-border/40 p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div className="space-y-1">
                            <CardTitle className="text-lg font-semibold">Evaluation Tasks</CardTitle>
                            <CardDescription>Click the evaluation button next to a project to start scoring.</CardDescription>
                        </div>
                        <div className="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                            <div className="relative w-full sm:w-[220px]">
                                <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search title, code..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="h-9 pl-9 w-full bg-background"
                                />
                            </div>
                            <div className="w-full sm:w-[150px]">
                                <Select
                                    value={statusFilter}
                                    onValueChange={setStatusFilter}
                                >
                                    <SelectTrigger className="h-9 bg-background">
                                        <SelectValue placeholder="All Statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Projects</SelectItem>
                                        <SelectItem value="pending">Pending</SelectItem>
                                        <SelectItem value="completed">Completed</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="border-b border-border/40 bg-muted/20 hover:bg-transparent">
                                        <TableHead className="py-3 pl-6 font-semibold w-[120px]">Project Code</TableHead>
                                        <TableHead className="font-semibold">Project Title</TableHead>
                                        <TableHead className="font-semibold">Category</TableHead>
                                        <TableHead className="text-center font-semibold w-[100px]">Round</TableHead>
                                        <TableHead className="text-center font-semibold w-[120px]">Status</TableHead>
                                        <TableHead className="text-center font-semibold w-[120px]">Score</TableHead>
                                        <TableHead className="pr-6 text-right font-semibold w-[140px]">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredAssignments.length > 0 ? (
                                        filteredAssignments.map((assignment) => (
                                            <TableRow 
                                                key={`${assignment.id}-${assignment.round_no}`}
                                                className="border-b border-border/30 transition-colors hover:bg-muted/10"
                                            >
                                                <TableCell className="py-4 pl-6 font-mono font-semibold text-primary">
                                                    {assignment.pcode || 'PENDING_CODE'}
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {assignment.title}
                                                </TableCell>
                                                <TableCell>
                                                    {assignment.category_name}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <Badge variant="outline" className="font-semibold px-2 py-0.5">
                                                        R{assignment.round_no}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {assignment.is_evaluated ? (
                                                        <Badge variant="success" className="gap-1">
                                                            Completed
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="warning" className="gap-1">
                                                            Pending
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-center font-mono font-bold">
                                                    {assignment.is_evaluated ? `${assignment.total_score}%` : '-'}
                                                </TableCell>
                                                <TableCell className="pr-6 text-right">
                                                    <Link 
                                                        href={`/dashboard/judge/evaluations/${assignment.id}?round=${assignment.round_no}`}
                                                    >
                                                        <Button 
                                                            variant={assignment.is_evaluated ? "outline" : "default"}
                                                            size="sm"
                                                            className="h-8 w-28 gap-1"
                                                        >
                                                            {assignment.is_evaluated ? (
                                                                <>
                                                                    <Award className="h-3.5 w-3.5" />
                                                                    Edit Score
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Play className="h-3.5 w-3.5 fill-current" />
                                                                    Evaluate
                                                                </>
                                                            )}
                                                        </Button>
                                                    </Link>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    ) : (
                                        <TableRow>
                                            <TableCell colSpan={7} className="h-32 text-center text-muted-foreground">
                                                {assignments.length === 0 
                                                    ? 'No assigned projects found for this session.' 
                                                    : 'No projects match your search or filter criteria.'}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

JudgeDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Judge Dashboard',
                                    href: '/dashboard/judge/evaluations',
        },
    ],
};
