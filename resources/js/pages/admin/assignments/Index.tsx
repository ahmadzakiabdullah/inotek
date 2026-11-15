import { Head, router, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, ChevronLeft, ChevronRight, Lock, Plus, Search, UserPlus, Users, Wand2, X } from 'lucide-react';
import React, { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface ProjectRow {
    id: number;
    pcode: string | null;
    title: string;
    category: { id: number; name: string } | null;
    shortlisted: boolean;
    /** Judges with a conflict of interest, keyed by judge ID. */
    conflicts: Record<string, 'owner' | 'supervisor'>;
    /** Judges assigned to or scoring this project in Round 1 (not allowed in its Round 2). */
    round_one_judge_ids: number[];
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
    scored: boolean;
}

interface Props {
    projects: ProjectRow[];
    judges: Judge[];
    assignments: Assignment[];
    activeSession: { id: number; name: string; r2_locked: boolean } | null;
    roundOneComplete: boolean;
    roundOneLocked: boolean;
    targetJudges: number;
    autoAssign: { enabled: boolean; judgesPerProject: number; max: number };
}

type Round = 1 | 2;
type Coverage = 'all' | 'none' | 'under' | 'complete';

const PAGE_SIZE = 50;

const selectClass =
    'h-9 rounded-md border border-border bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring';

/** Why a judge cannot take this project in this round, or null when they can. */
function ineligibleReason(project: ProjectRow, judgeId: number, round: Round, assignedJudgeIds: Set<number>): string | null {
    if (assignedJudgeIds.has(judgeId)) {
        return 'Already assigned';
    }

    const conflict = project.conflicts[String(judgeId)];

    if (conflict) {
        return conflict === 'owner' ? 'Project owner' : 'Supervisor';
    }

    if (round === 2 && project.round_one_judge_ids.includes(judgeId)) {
        return 'Judged in Round 1';
    }

    return null;
}

interface PickerOption {
    judge: Judge;
    load: number;
    /** Shown on the right: a reason when disabled, otherwise a hint. */
    note: string;
    disabled: boolean;
}

/** Searchable judge list, least-loaded eligible judges first. */
function JudgePicker({ options, onPick }: { options: PickerOption[]; onPick: (judgeId: number) => void }) {
    const sorted = [...options].sort(
        (a, b) => Number(a.disabled) - Number(b.disabled) || a.load - b.load || a.judge.name.localeCompare(b.judge.name),
    );

    return (
        <Command
            // Match on name and email.
            filter={(value, search) => (value.toLowerCase().includes(search.toLowerCase()) ? 1 : 0)}
        >
            <CommandInput placeholder="Search judges…" />
            <CommandList className="max-h-72">
                <CommandEmpty>No judge found.</CommandEmpty>
                <CommandGroup heading="Fewest assignments first">
                    {sorted.map((option) => (
                        <CommandItem
                            key={option.judge.id}
                            value={`${option.judge.name} ${option.judge.email} #${option.judge.id}`}
                            disabled={option.disabled}
                            onSelect={() => onPick(option.judge.id)}
                            className="flex items-center justify-between gap-3"
                        >
                            <span className="min-w-0">
                                <span className="block truncate">{option.judge.name}</span>
                                <span className="block truncate text-[11px] text-muted-foreground">{option.judge.email}</span>
                            </span>
                            <span className="shrink-0 text-[11px] text-muted-foreground">{option.note}</span>
                        </CommandItem>
                    ))}
                </CommandGroup>
            </CommandList>
        </Command>
    );
}

function AutoAssignDialog({
    open,
    onOpenChange,
    round,
    defaultJudgesPerProject,
    max,
    judgeCount,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    round: Round;
    defaultJudgesPerProject: number;
    max: number;
    judgeCount: number;
}) {
    const autoForm = useForm({ round_no: String(round), judges_per_project: defaultJudgesPerProject });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // The server sends the result (including projects it could not fill) as a toast.
        autoForm.transform((data) => ({ ...data, round_no: String(round) }));
        autoForm.post('/dashboard/assignments/auto', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Wand2 className="h-5 w-5 text-primary" />
                        Auto-assign judges · Round {round}
                    </DialogTitle>
                    <DialogDescription>
                        Gives every {round === 2 ? 'shortlisted' : 'approved'} project the chosen number of judges, spreading
                        the workload evenly across all {judgeCount} judge(s). Existing assignments are kept, and conflicts of
                        interest{round === 2 ? ' and Round 1 judges' : ''} are skipped.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="auto-count">Judges per project</Label>
                        <Input
                            id="auto-count"
                            type="number"
                            min={1}
                            max={max}
                            value={autoForm.data.judges_per_project}
                            onChange={(e) => autoForm.setData('judges_per_project', Number(e.target.value))}
                            className="w-28"
                        />
                        <InputError message={autoForm.errors.judges_per_project} />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={autoForm.processing} className="gap-2">
                            <Wand2 className="h-4 w-4" />
                            {autoForm.processing ? 'Assigning…' : 'Auto-assign'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function AssignmentsIndex({
    projects,
    judges,
    assignments,
    activeSession,
    roundOneComplete,
    roundOneLocked,
    targetJudges,
    autoAssign,
}: Props) {
    const [round, setRound] = useState<Round>(roundOneLocked ? 2 : 1);
    const [search, setSearch] = useState('');
    const [categoryId, setCategoryId] = useState('all');
    const [coverage, setCoverage] = useState<Coverage>('all');
    const [page, setPage] = useState(0);
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [pickerFor, setPickerFor] = useState<number | 'bulk' | null>(null);
    const [autoOpen, setAutoOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    const roundAvailable = round === 1 || roundOneComplete;
    const canEdit = round === 1 ? !roundOneLocked : roundOneComplete && !activeSession?.r2_locked;

    const judgesById = useMemo(() => new Map(judges.map((judge) => [judge.id, judge])), [judges]);

    const roundAssignments = useMemo(() => assignments.filter((a) => a.round_no === round), [assignments, round]);

    const assignmentsByProject = useMemo(() => {
        const map = new Map<number, Assignment[]>();

        for (const assignment of roundAssignments) {
            map.set(assignment.project_id, [...(map.get(assignment.project_id) ?? []), assignment]);
        }

        return map;
    }, [roundAssignments]);

    const loadByJudge = useMemo(() => {
        const map = new Map<number, number>(judges.map((judge) => [judge.id, 0]));

        for (const assignment of roundAssignments) {
            map.set(assignment.judge_id, (map.get(assignment.judge_id) ?? 0) + 1);
        }

        return map;
    }, [judges, roundAssignments]);

    const roundProjects = useMemo(
        () => (round === 2 ? projects.filter((project) => project.shortlisted) : projects),
        [projects, round],
    );

    const categories = useMemo(() => {
        const map = new Map<number, string>();

        for (const project of roundProjects) {
            if (project.category) {
                map.set(project.category.id, project.category.name);
            }
        }

        return [...map.entries()].sort((a, b) => a[1].localeCompare(b[1]));
    }, [roundProjects]);

    const summary = useMemo(() => {
        const counts = roundProjects.map((project) => assignmentsByProject.get(project.id)?.length ?? 0);
        const loads = [...loadByJudge.values()];

        return {
            total: roundProjects.length,
            complete: counts.filter((count) => count >= targetJudges).length,
            none: counts.filter((count) => count === 0).length,
            under: counts.filter((count) => count > 0 && count < targetJudges).length,
            minLoad: loads.length ? Math.min(...loads) : 0,
            maxLoad: loads.length ? Math.max(...loads) : 0,
        };
    }, [roundProjects, assignmentsByProject, loadByJudge, targetJudges]);

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        return roundProjects.filter((project) => {
            const count = assignmentsByProject.get(project.id)?.length ?? 0;
            const matchesSearch =
                !term || project.title.toLowerCase().includes(term) || (project.pcode ?? '').toLowerCase().includes(term);
            const matchesCategory = categoryId === 'all' || String(project.category?.id) === categoryId;
            const matchesCoverage =
                coverage === 'all' ||
                (coverage === 'none' && count === 0) ||
                (coverage === 'under' && count < targetJudges) ||
                (coverage === 'complete' && count >= targetJudges);

            return matchesSearch && matchesCategory && matchesCoverage;
        });
    }, [roundProjects, search, categoryId, coverage, assignmentsByProject, targetJudges]);

    const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    const currentPage = Math.min(page, pageCount - 1);
    const visible = filtered.slice(currentPage * PAGE_SIZE, (currentPage + 1) * PAGE_SIZE);

    const resetView = () => {
        setPage(0);
        setSelected(new Set());
    };

    const assignedSet = (projectId: number) => new Set((assignmentsByProject.get(projectId) ?? []).map((a) => a.judge_id));

    const submitPairs = (pairs: Array<{ project_id: number; judge_id: number }>, onDone?: () => void) => {
        setBusy(true);
        router.post(
            '/dashboard/assignments/bulk',
            { round_no: round, pairs },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => onDone?.(),
                onFinish: () => {
                    setBusy(false);
                    setPickerFor(null);
                },
            },
        );
    };

    const removeAssignment = (assignment: Assignment, project: ProjectRow) => {
        const judgeName = judgesById.get(assignment.judge_id)?.name ?? 'this judge';

        if (!window.confirm(`Remove ${judgeName} from ${project.pcode ?? project.title} (Round ${round})?`)) {
            return;
        }

        setBusy(true);
        router.post(`/dashboard/assignments/${assignment.id}/delete`, {}, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setBusy(false),
        });
    };

    const singleOptions = (project: ProjectRow): PickerOption[] => {
        const assigned = assignedSet(project.id);

        return judges.map((judge) => {
            const reason = ineligibleReason(project, judge.id, round, assigned);
            const load = loadByJudge.get(judge.id) ?? 0;

            return { judge, load, disabled: reason !== null, note: reason ?? `${load} assigned` };
        });
    };

    const selectedProjects = filtered.filter((project) => selected.has(project.id));

    const bulkOptions = (): PickerOption[] =>
        judges.map((judge) => {
            const eligible = selectedProjects.filter((project) => ineligibleReason(project, judge.id, round, assignedSet(project.id)) === null).length;
            const load = loadByJudge.get(judge.id) ?? 0;

            return {
                judge,
                load,
                disabled: eligible === 0,
                note: eligible === selectedProjects.length ? `${load} assigned` : `${eligible}/${selectedProjects.length} eligible`,
            };
        });

    const assignToSelected = (judgeId: number) => {
        // Only send eligible pairs; the server re-checks each one anyway.
        const pairs = selectedProjects
            .filter((project) => ineligibleReason(project, judgeId, round, assignedSet(project.id)) === null)
            .map((project) => ({ project_id: project.id, judge_id: judgeId }));

        submitPairs(pairs, () => setSelected(new Set()));
    };

    const allVisibleSelected = visible.length > 0 && visible.every((project) => selected.has(project.id));
    const someVisibleSelected = visible.some((project) => selected.has(project.id));

    const toggleAllVisible = (checked: boolean) => {
        const next = new Set(selected);

        for (const project of visible) {
            if (checked) {
                next.add(project.id);
            } else {
                next.delete(project.id);
            }
        }

        setSelected(next);
    };

    const toggleOne = (projectId: number, checked: boolean) => {
        const next = new Set(selected);

        if (checked) {
            next.add(projectId);
        } else {
            next.delete(projectId);
        }

        setSelected(next);
    };

    if (!activeSession) {
        return (
            <>
                <Head title="Judge Assignments" />
                <div className="flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/5 p-4 text-sm text-amber-700 dark:text-amber-400">
                    <AlertCircle className="h-5 w-5" />
                    No active competition session found. Configure and activate a session in Sessions Management first.
                </div>
            </>
        );
    }

    const coveragePercent = summary.total ? Math.round((summary.complete / summary.total) * 100) : 0;

    return (
        <>
            <Head title="Judge Assignments" />

            <div className="space-y-4">
                {/* Header and round switch */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                            <Users className="h-8 w-8 text-primary" />
                            Judge Assignments
                        </h1>
                        <p className="text-sm text-muted-foreground">Active session: {activeSession.name}</p>
                    </div>

                    <div className="inline-flex rounded-lg border border-border p-1" role="group" aria-label="Evaluation round">
                        {([1, 2] as Round[]).map((value) => (
                            <Button
                                key={value}
                                type="button"
                                size="sm"
                                variant={round === value ? 'default' : 'ghost'}
                                aria-pressed={round === value}
                                onClick={() => {
                                    setRound(value);
                                    resetView();
                                }}
                            >
                                Round {value}
                                {value === 2 && !roundOneComplete && <Lock className="ml-1 h-3 w-3" />}
                            </Button>
                        ))}
                    </div>
                </div>

                {/* Round status notices */}
                {round === 1 && roundOneLocked && (
                    <p className="flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-sm text-amber-700 dark:text-amber-400">
                        <Lock className="h-4 w-4 shrink-0" />
                        Round 1 is closed because Round 2 judging has started. Assignments are shown for reference only.
                    </p>
                )}
                {round === 2 && !roundOneComplete && (
                    <p className="flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-sm text-amber-700 dark:text-amber-400">
                        <Lock className="h-4 w-4 shrink-0" />
                        Round 2 opens once every Round 1 evaluation is complete. The shortlist below may still change.
                    </p>
                )}
                {round === 2 && activeSession.r2_locked && (
                    <p className="flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-sm text-amber-700 dark:text-amber-400">
                        <Lock className="h-4 w-4 shrink-0" />
                        Round 2 has been finalized, so its assignments can no longer change.
                    </p>
                )}

                {/* Coverage summary */}
                <Card className="border border-border/60 bg-card/60">
                    <CardContent className="flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div className="min-w-0 flex-1 space-y-2">
                            <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                                <span>
                                    <strong className="text-base">{summary.complete}</strong> of {summary.total}{' '}
                                    {round === 2 ? 'shortlisted' : ''} projects have {targetJudges}+ judges
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    Target {targetJudges} per project (System Settings)
                                </span>
                            </div>
                            <Progress value={coveragePercent} aria-label={`${coveragePercent}% of projects have enough judges`} />
                            <div className="flex flex-wrap gap-2 text-xs">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setCoverage('none');
                                        resetView();
                                    }}
                                    className="rounded-full border border-border px-2.5 py-0.5 hover:bg-muted"
                                >
                                    {summary.none} with no judges
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setCoverage('under');
                                        resetView();
                                    }}
                                    className="rounded-full border border-border px-2.5 py-0.5 hover:bg-muted"
                                >
                                    {summary.under} below target
                                </button>
                                <span className="px-1 py-0.5 text-muted-foreground">
                                    Judge workload this round: {summary.minLoad}–{summary.maxLoad} projects
                                </span>
                            </div>
                        </div>

                        {autoAssign.enabled && (
                            <Button type="button" variant="outline" className="gap-2" disabled={!canEdit} onClick={() => setAutoOpen(true)}>
                                <Wand2 className="h-4 w-4" />
                                Auto-assign…
                            </Button>
                        )}
                    </CardContent>
                </Card>

                {/* Filters */}
                <div className="flex flex-col gap-2 md:flex-row md:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                resetView();
                            }}
                            placeholder="Search project title or code…"
                            aria-label="Search projects"
                            className="pl-9"
                        />
                    </div>
                    <select
                        aria-label="Filter by category"
                        value={categoryId}
                        onChange={(e) => {
                            setCategoryId(e.target.value);
                            resetView();
                        }}
                        className={selectClass}
                    >
                        <option value="all">All categories</option>
                        {categories.map(([id, name]) => (
                            <option key={id} value={id}>
                                {name}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Filter by judge coverage"
                        value={coverage}
                        onChange={(e) => {
                            setCoverage(e.target.value as Coverage);
                            resetView();
                        }}
                        className={selectClass}
                    >
                        <option value="all">All projects</option>
                        <option value="none">No judges</option>
                        <option value="under">Below target</option>
                        <option value="complete">Target reached</option>
                    </select>
                </div>

                {/* Bulk action bar */}
                {canEdit && selected.size > 0 && (
                    <div className="sticky top-2 z-10 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-primary/40 bg-background/95 p-3 shadow-md backdrop-blur">
                        <span className="text-sm font-medium">
                            {selectedProjects.length} project{selectedProjects.length === 1 ? '' : 's'} selected
                        </span>
                        <div className="flex gap-2">
                            <Popover open={pickerFor === 'bulk'} onOpenChange={(open) => setPickerFor(open ? 'bulk' : null)}>
                                <PopoverTrigger asChild>
                                    <Button size="sm" className="gap-1.5" disabled={busy || selectedProjects.length === 0}>
                                        <UserPlus className="h-4 w-4" />
                                        Assign a judge to all selected
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-80 p-0" align="end">
                                    <JudgePicker options={bulkOptions()} onPick={assignToSelected} />
                                </PopoverContent>
                            </Popover>
                            <Button size="sm" variant="outline" onClick={() => setSelected(new Set())}>
                                Clear selection
                            </Button>
                        </div>
                    </div>
                )}

                {/* Project list */}
                <Card className="overflow-hidden border border-border/60">
                    <Table>
                        <TableHeader className="bg-muted/30">
                            <TableRow>
                                <TableHead className="w-10 pl-4">
                                    <Checkbox
                                        aria-label="Select all projects on this page"
                                        checked={allVisibleSelected ? true : someVisibleSelected ? 'indeterminate' : false}
                                        onCheckedChange={(checked) => toggleAllVisible(checked === true)}
                                        disabled={!canEdit || visible.length === 0}
                                    />
                                </TableHead>
                                <TableHead className="w-24">Code</TableHead>
                                <TableHead>Project</TableHead>
                                <TableHead>Judges</TableHead>
                                <TableHead className="w-32 pr-4 text-right">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {visible.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="py-10 text-center text-sm text-muted-foreground">
                                        {!roundAvailable && round === 2
                                            ? 'No shortlist yet.'
                                            : roundProjects.length === 0
                                              ? round === 2
                                                  ? 'No shortlisted projects yet.'
                                                  : 'No approved projects in this session yet.'
                                              : 'No projects match these filters.'}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                visible.map((project) => {
                                    const projectAssignments = assignmentsByProject.get(project.id) ?? [];
                                    const count = projectAssignments.length;

                                    return (
                                        <TableRow key={project.id} data-state={selected.has(project.id) ? 'selected' : undefined}>
                                            <TableCell className="pl-4 align-top">
                                                <Checkbox
                                                    aria-label={`Select ${project.pcode ?? project.title}`}
                                                    checked={selected.has(project.id)}
                                                    onCheckedChange={(checked) => toggleOne(project.id, checked === true)}
                                                    disabled={!canEdit}
                                                />
                                            </TableCell>
                                            <TableCell className="align-top font-mono text-xs font-semibold">{project.pcode ?? '—'}</TableCell>
                                            <TableCell className="align-top">
                                                <div className="max-w-xs font-medium leading-snug">{project.title}</div>
                                                <div className="text-xs text-muted-foreground">{project.category?.name ?? '—'}</div>
                                            </TableCell>
                                            <TableCell className="align-top">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    {projectAssignments.map((assignment) => {
                                                        const judgeName = judgesById.get(assignment.judge_id)?.name ?? 'Unknown judge';

                                                        return (
                                                            <Badge
                                                                key={assignment.id}
                                                                variant="secondary"
                                                                className="gap-1 py-0.5 pr-1 font-normal"
                                                                title={assignment.scored ? 'Score submitted' : 'Not scored yet'}
                                                            >
                                                                {assignment.scored && (
                                                                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" aria-label="Score submitted" />
                                                                )}
                                                                {judgeName}
                                                                {canEdit && !assignment.scored && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => removeAssignment(assignment, project)}
                                                                        disabled={busy}
                                                                        className="rounded-sm p-0.5 hover:bg-destructive/15 hover:text-destructive"
                                                                        aria-label={`Remove ${judgeName} from ${project.pcode ?? project.title}`}
                                                                    >
                                                                        <X className="h-3 w-3" />
                                                                    </button>
                                                                )}
                                                            </Badge>
                                                        );
                                                    })}
                                                    <span
                                                        className={`text-xs tabular-nums ${count >= targetJudges ? 'text-muted-foreground' : 'font-semibold text-amber-700 dark:text-amber-400'}`}
                                                    >
                                                        {count}/{targetJudges}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="pr-4 text-right align-top">
                                                {canEdit && (
                                                    <Popover
                                                        open={pickerFor === project.id}
                                                        onOpenChange={(open) => setPickerFor(open ? project.id : null)}
                                                    >
                                                        <PopoverTrigger asChild>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                className="gap-1"
                                                                disabled={busy}
                                                                aria-label={`Add a judge to ${project.pcode ?? project.title}`}
                                                            >
                                                                <Plus className="h-3.5 w-3.5" />
                                                                Judge
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent className="w-80 p-0" align="end">
                                                            <JudgePicker
                                                                options={singleOptions(project)}
                                                                onPick={(judgeId) => submitPairs([{ project_id: project.id, judge_id: judgeId }])}
                                                            />
                                                        </PopoverContent>
                                                    </Popover>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>

                    {filtered.length > PAGE_SIZE && (
                        <div className="flex items-center justify-between border-t border-border/60 px-4 py-2 text-sm">
                            <span className="text-muted-foreground">
                                {currentPage * PAGE_SIZE + 1}–{Math.min((currentPage + 1) * PAGE_SIZE, filtered.length)} of {filtered.length}
                            </span>
                            <div className="flex gap-1">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setPage(currentPage - 1)}
                                    disabled={currentPage === 0}
                                    aria-label="Previous page"
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setPage(currentPage + 1)}
                                    disabled={currentPage >= pageCount - 1}
                                    aria-label="Next page"
                                >
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    )}
                </Card>
            </div>

            {autoAssign.enabled && (
                <AutoAssignDialog
                    open={autoOpen}
                    onOpenChange={setAutoOpen}
                    round={round}
                    defaultJudgesPerProject={autoAssign.judgesPerProject}
                    max={autoAssign.max}
                    judgeCount={judges.length}
                />
            )}
        </>
    );
}

AssignmentsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Judge Assignments', href: '/dashboard/assignments' },
    ],
};
