import { Head, useForm } from '@inertiajs/react';
import { Users, Plus, Trash2, Search, AlertCircle, Calendar, Sparkles, Filter } from 'lucide-react';
import React, { useState, useMemo } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Category {
    id: number;
    name: string;
}

interface Project {
    id: number;
    title: string;
    pcode: string | null;
    category: Category | null;
    judge_assignments: Array<{
        id: number;
        judge: {
            id: number;
            name: string;
        };
        round_no: number;
    }>;
}

interface Judge {
    id: number;
    name: string;
    email: string;
}

interface Assignment {
    id: number;
    project_id: number;
    judge_id: number;
    round_no: number;
    project: {
        title: string;
        pcode: string | null;
        category: Category | null;
    };
    judge: {
        name: string;
    };
}

interface CompetitionSession {
    id: number;
    name: string;
    is_active: boolean;
    r2_locked: boolean;
}

interface Props {
    projects: Project[];
    judges: Judge[];
    assignments: Assignment[];
    activeSession: CompetitionSession | null;
    roundOneComplete: boolean;
}

export default function AssignmentsIndex({ projects, judges, assignments, activeSession, roundOneComplete }: Props) {
    const [searchTerm, setSearchTerm] = useState('');
    const [roundFilter, setRoundFilter] = useState<'all' | '1' | '2'>('all');
    const [selectedProject, setSelectedProject] = useState<string>('');
    const [selectedJudge, setSelectedJudge] = useState<string>('');
    const [selectedRound, setSelectedRound] = useState<string>('1');

    const form = useForm({
        project_id: '',
        judge_id: '',
        round_no: '1',
    });

    const deleteForm = useForm({});

    const availableJudges = useMemo(() => {
        if (!selectedProject) {
            return judges;
        }

        const assignedJudgeIds = new Set(
            assignments
                .filter(
                    (assignment) =>
                        assignment.project_id.toString() === selectedProject &&
                        assignment.round_no.toString() === selectedRound,
                )
                .map((assignment) => assignment.judge_id),
        );

        return judges.filter((judge) => !assignedJudgeIds.has(judge.id));
    }, [assignments, judges, selectedProject, selectedRound]);

    const handleAssign = (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedProject || !selectedJudge) {
            toast.error('Please select both Project and Judge.');

            return;
        }

        form.setData({
            project_id: selectedProject,
            judge_id: selectedJudge,
            round_no: selectedRound,
        });

        // Delay submission slightly to allow useForm data state to sync
        setTimeout(() => {
            form.post('/dashboard/assignments', {
                onSuccess: () => {
                    toast.success('Judge assigned successfully!');
                    setSelectedProject('');
                    setSelectedJudge('');
                },
                onError: (err) => {
                    const message = err.error || 'Failed to assign judge. Check conflicts.';
                    toast.error(message);
                }
            });
        }, 100);
    };

    const handleDeleteAssignment = (id: number) => {
        if (!confirm('Are you sure you want to delete this judge assignment?')) {
return;
}

        deleteForm.delete(`/dashboard/assignments/${id}`, {
            onSuccess: () => {
                toast.success('Judge assignment deleted successfully.');
            },
            onError: (err) => {
                toast.error(err.error || 'Failed to delete assignment.');
            }
        });
    };

    // Filtered current assignments list
    const filteredAssignments = useMemo(() => {
        return assignments.filter((assign) => {
            const titleMatch = assign.project.title.toLowerCase().includes(searchTerm.toLowerCase());
            const codeMatch = assign.project.pcode?.toLowerCase().includes(searchTerm.toLowerCase()) || false;
            const judgeMatch = assign.judge.name.toLowerCase().includes(searchTerm.toLowerCase());
            
            const matchesSearch = titleMatch || codeMatch || judgeMatch;
            const matchesRound = roundFilter === 'all' || assign.round_no.toString() === roundFilter;

            return matchesSearch && matchesRound;
        });
    }, [assignments, searchTerm, roundFilter]);

    return (
        <>
            <Head title="Judge Assignments" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-2">
                    <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                        <Users className="h-8 w-8 text-primary" />
                        Judge Assignments Management
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {activeSession 
                            ? `Active Session: ${activeSession.name}` 
                            : 'No active competition session.'}
                    </p>
                </div>

                {!activeSession ? (
                    <div className="rounded-lg border border-warning/20 bg-warning/5 p-4 text-sm text-warning flex items-center gap-2">
                        <AlertCircle className="h-5 w-5" />
                        No active competition session found. Please configure and activate a session in the Sessions Management panel first.
                    </div>
                ) : (
                    <div className="grid gap-6 lg:grid-cols-3">
                        {/* Assignment Form Card */}
                        <div className="space-y-6 lg:col-span-1">
                            <Card className="border border-border/50 bg-card/60 backdrop-blur-sm shadow-md">
                                <CardHeader>
                                    <CardTitle className="text-lg font-bold flex items-center gap-2">
                                        <Plus className="h-5 w-5 text-primary" />
                                        Assign New Judge
                                    </CardTitle>
                                    <CardDescription>Select project, judge, and evaluation round.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={handleAssign} className="space-y-4">
                                        {/* Project selection */}
                                        <div className="space-y-2">
                                            <Label htmlFor="project">Project</Label>
                                            <select
                                                id="project"
                                                value={selectedProject}
                                                onChange={(e) => setSelectedProject(e.target.value)}
                                                className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                                required
                                            >
                                                <option value="">-- Select Project --</option>
                                                {projects.map((project) => (
                                                    <option key={project.id} value={project.id}>
                                                        {project.pcode ? `[${project.pcode}] ` : ''}{project.title} ({project.category?.name})
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={form.errors.project_id} />
                                        </div>

                                        {/* Judge selection */}
                                        <div className="space-y-2">
                                            <Label htmlFor="judge">Judge</Label>
                                            <select
                                                id="judge"
                                                value={selectedJudge}
                                                onChange={(e) => setSelectedJudge(e.target.value)}
                                                className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                                required
                                            >
                                                <option value="">-- Select Judge --</option>
                                                {availableJudges.map((judge) => (
                                                    <option key={judge.id} value={judge.id}>
                                                        {judge.name} ({judge.email})
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={form.errors.judge_id} />
                                        </div>

                                        {/* Round selection */}
                                        <div className="space-y-2">
                                            <Label htmlFor="round">Evaluation Round</Label>
                                            <select
                                                id="round"
                                                value={selectedRound}
                                                onChange={(e) => setSelectedRound(e.target.value)}
                                                className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                            >
                                                <option value="1">Round 1 (Preliminary Round)</option>
                                                <option value="2" disabled={!roundOneComplete}>Round 2 (Final / Qualified Shortlist)</option>
                                            </select>
                                            <InputError message={form.errors.round_no} />
                                        </div>

                                        {/* Warnings and Notes for Round 2 */}
                                        {selectedRound === '2' && (
                                            <div className="rounded-lg border border-amber-500/20 bg-amber-500/5 p-3 text-xs text-amber-600 dark:text-amber-400 space-y-1">
                                                <p className="font-bold flex items-center gap-1">
                                                    <Sparkles className="h-3.5 w-3.5" /> Round 2 Guard Limit:
                                                </p>
                                            <p>{roundOneComplete ? 'The system blocks assignment if the selected judge already evaluated this project in Round 1.' : 'Round 2 is unavailable until every Round 1 evaluation is complete.'}</p>
                                        </div>
                                    )}

                                        <Button
                                            type="submit"
                                            disabled={form.processing || (selectedRound === '2' && !roundOneComplete)}
                                            className="w-full mt-2"
                                        >
                                            {form.processing ? 'Processing...' : 'Assign Judge'}
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Assignments List Card */}
                        <div className="lg:col-span-2 space-y-6">
                            <Card className="border border-border/50 bg-card/40 backdrop-blur-sm shadow-md overflow-hidden">
                                <CardHeader className="border-b border-border/40 p-4">
                                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <CardTitle className="text-lg font-bold">Existing Assignments</CardTitle>
                                            <CardDescription>View and manage judge assignments for the active session.</CardDescription>
                                        </div>
                                        {/* Filters */}
                                        <div className="flex flex-wrap gap-2 items-center">
                                            <select
                                                value={roundFilter}
                                                onChange={(e) => setRoundFilter(e.target.value as any)}
                                                className="rounded-md border border-border bg-background/50 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                            >
                                                <option value="all">All Rounds</option>
                                                <option value="1">Round 1</option>
                                                <option value="2">Round 2</option>
                                            </select>

                                            <div className="relative w-full sm:w-48">
                                                <Search className="absolute top-2 left-2 h-3.5 w-3.5 text-muted-foreground" />
                                                <input
                                                    placeholder="Search judge/project..."
                                                    value={searchTerm}
                                                    onChange={(e) => setSearchTerm(e.target.value)}
                                                    className="w-full rounded-md border border-border/50 bg-background/50 pl-8 pr-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <div className="overflow-x-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow className="border-b border-border/40 bg-muted/20 hover:bg-transparent">
                                                    <TableHead className="py-2.5 pl-4 font-semibold w-[100px]">Code</TableHead>
                                                    <TableHead className="font-semibold">Project</TableHead>
                                                    <TableHead className="font-semibold">Assigned Judge</TableHead>
                                                    <TableHead className="text-center font-semibold w-[90px]">Round</TableHead>
                                                    <TableHead className="pr-4 text-right font-semibold w-[80px]">Delete</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {filteredAssignments.length > 0 ? (
                                                    filteredAssignments.map((assign) => (
                                                        <TableRow 
                                                            key={assign.id}
                                                            className="border-b border-border/30 transition-colors hover:bg-muted/10"
                                                        >
                                                            <TableCell className="py-3 pl-4 font-mono text-xs font-bold text-primary">
                                                                {assign.project.pcode || '-'}
                                                            </TableCell>
                                                            <TableCell className="font-medium text-xs max-w-[200px] truncate" title={assign.project.title}>
                                                                {assign.project.title}
                                                            </TableCell>
                                                            <TableCell className="font-semibold text-xs text-foreground">
                                                                {assign.judge.name}
                                                            </TableCell>
                                                            <TableCell className="text-center">
                                                                <Badge variant="outline" className="text-[10px] px-1.5 py-0.2 font-mono">
                                                                    R{assign.round_no}
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="pr-4 text-right">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() => handleDeleteAssignment(assign.id)}
                                                                    className="h-7 w-7 text-destructive hover:bg-destructive/10"
                                                                >
                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                </Button>
                                                            </TableCell>
                                                        </TableRow>
                                                    ))
                                                ) : (
                                                    <TableRow>
                                                        <TableCell colSpan={5} className="h-24 text-center text-xs text-muted-foreground">
                                                            No judge assignments found.
                                                        </TableCell>
                                                    </TableRow>
                                                )}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

AssignmentsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Admin Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Judge Assignments',
            href: '/dashboard/assignments',
        },
    ],
};
