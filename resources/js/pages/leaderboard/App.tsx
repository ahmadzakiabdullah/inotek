import { Head } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    ChevronDown,
    Info,
    Lock,
    Mic,
    RefreshCw,
    Search,
    Trophy,
} from 'lucide-react';
import { Fragment, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { AwardBadge } from '@/components/leaderboard/award-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

// Staff leaderboard (admin, lecturer, judge). The server decides what each role
// may see; this page only adapts its layout to the data it receives.

interface Category {
    id: number;
    code: string;
    name: string;
}

interface JudgeScore {
    /** Full name (falls back to the account name). */
    name: string;
    username?: string | null;
    round: number;
    score: number;
    comments: string | null;
    /** Nominee's name when this judge nominated someone for Best Presenter. */
    best_presenter?: string | null;
    submitted_at: string;
}

interface LeaderboardProject {
    id: number;
    title: string;
    pcode: string | null;
    category_id: number;
    category_name: string;
    username?: string | null;
    award_level: string | null;
    avg_r1?: number;
    judges_r1?: number;
    avg_r2?: number;
    judges_r2?: number;
    final_score?: number;
    judges?: JudgeScore[];
    best_presenter?: { name: string; votes: number } | null;
}

interface Props {
    activeSession: { id: number; name: string } | null;
    categories: Category[];
    leaderboardData: Record<number, LeaderboardProject[]>;
    resultsPublished: boolean;
    resultsFinal?: boolean;
}

type StatusFilter = 'all' | 'evaluated' | 'round2' | 'pending';

/**
 * What the viewer may see, derived from the data the server sent:
 * scores = averages and ranks (admins, or everyone once results are final),
 * own    = a judge's own scores only (before results are final),
 * list   = project list without scores (lecturers before results are final).
 */
type ViewMode = 'scores' | 'own' | 'list';

type View = 'projects' | 'presenters';

interface Nomination {
    nominee: string;
    row: Row;
    votes: number;
    rounds: number[];
    judges: string[];
}

interface Row {
    project: LeaderboardProject;
    category: Category | undefined;
    rank: number | null;
    evaluated: boolean;
    inRound2: boolean;
}

const fmt = (value: number | undefined) => (value ?? 0).toFixed(2);

export default function StaffLeaderboard({
    activeSession,
    categories,
    leaderboardData,
    resultsPublished,
    resultsFinal = false,
}: Props) {
    const [data, setData] = useState(leaderboardData);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [lastUpdated, setLastUpdated] = useState(new Date());
    const [categoryId, setCategoryId] = useState('all');
    const [status, setStatus] = useState<StatusFilter>('all');
    const [search, setSearch] = useState('');
    const [expanded, setExpanded] = useState<Set<number>>(new Set());
    const [view, setView] = useState<View>('projects');

    useEffect(() => setData(leaderboardData), [leaderboardData]);

    const refresh = () => {
        setIsRefreshing(true);
        fetch('/dashboard/leaderboard?api=true', {
            headers: { Accept: 'application/json' },
        })
            .then((res) => res.json())
            .then((json) => {
                setData(json.leaderboardData ?? {});
                setLastUpdated(new Date());
            })
            .catch((err) => console.error('Error fetching rankings:', err))
            .finally(() => setIsRefreshing(false));
    };

    useEffect(() => {
        const echo = (window as any).Echo;
        const channel = echo ? echo.channel('leaderboard') : null;
        channel?.listen('.score.updated', refresh);
        const poll = setInterval(refresh, 15000);

        return () => {
            channel?.stopListening('.score.updated');
            clearInterval(poll);
        };
    }, []);

    const allProjects = useMemo(() => Object.values(data ?? {}).flat(), [data]);

    const mode: ViewMode = resultsPublished
        ? 'scores'
        : allProjects.some((p) => p.judges !== undefined)
          ? 'own'
          : 'list';

    // Ranks are per category and follow the server's ordering, so they stay the
    // same whatever filter or search is applied.
    const rows: Row[] = useMemo(() => {
        return categories.flatMap((category) => {
            let rank = 0;

            return (data?.[category.id] ?? []).map((project) => {
                const inRound2 = (project.judges_r2 ?? 0) > 0;
                const evaluated =
                    mode === 'own'
                        ? (project.judges ?? []).length > 0
                        : inRound2 || (project.judges_r1 ?? 0) > 0;

                return {
                    project,
                    category,
                    rank: mode === 'scores' && evaluated ? ++rank : null,
                    evaluated,
                    inRound2,
                };
            });
        });
    }, [categories, data, mode]);

    const inCategory = rows.filter(
        (row) =>
            categoryId === 'all' ||
            String(row.project.category_id) === categoryId,
    );

    const counts: Record<StatusFilter, number> = {
        all: inCategory.length,
        evaluated: inCategory.filter((r) => r.evaluated).length,
        round2: inCategory.filter((r) => r.inRound2).length,
        pending: inCategory.filter((r) => !r.evaluated).length,
    };

    const term = search.trim().toLowerCase();
    const visible = inCategory.filter((row) => {
        if (status === 'evaluated' && !row.evaluated) {
            return false;
        }

        if (status === 'round2' && !row.inRound2) {
            return false;
        }

        if (status === 'pending' && row.evaluated) {
            return false;
        }

        if (!term) {
            return true;
        }

        const { title, pcode, username } = row.project;

        return [title, pcode, username].some((v) =>
            v?.toLowerCase().includes(term),
        );
    });

    const nominations = collectNominations(
        inCategory.filter(
            (row) =>
                !term ||
                [row.project.title, row.project.pcode, row.project.username]
                    .concat(
                        (row.project.judges ?? []).map(
                            (j) => j.best_presenter ?? '',
                        ),
                    )
                    .some((v) => v?.toLowerCase().includes(term)),
        ),
    );
    const canSeeNominations = mode !== 'list';

    const toggle = (id: number) =>
        setExpanded((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });

    if (!activeSession) {
        return (
            <>
                <Head title="Leaderboard" />
                <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-12 text-center">
                    <AlertCircle className="h-10 w-10 text-muted-foreground/50" />
                    <h1 className="text-lg font-semibold">No active session</h1>
                    <p className="max-w-sm text-sm text-muted-foreground">
                        The leaderboard shows the active competition session.
                        Activate a session to see its projects and scores.
                    </p>
                </div>
            </>
        );
    }

    const showCategory = categoryId === 'all';
    const columnCount = mode === 'scores' ? 7 : mode === 'own' ? 5 : 3;

    const statCards: {
        key: StatusFilter;
        label: string;
        hint: string;
        tone: string;
    }[] = [
        {
            key: 'all',
            label: 'All projects',
            hint: 'Approved in this session',
            tone: 'text-foreground',
        },
        {
            key: 'evaluated',
            label: mode === 'own' ? 'Scored by you' : 'Evaluated',
            hint:
                mode === 'own'
                    ? 'You submitted a score'
                    : 'At least one score (R1 or R2)',
            tone: 'text-emerald-600 dark:text-emerald-400',
        },
        ...(mode === 'scores'
            ? [
                  {
                      key: 'round2' as const,
                      label: 'Round 2 finalists',
                      hint: 'Have a Round 2 score',
                      tone: 'text-primary',
                  },
              ]
            : []),
        {
            key: 'pending',
            label: mode === 'own' ? 'Not scored by you' : 'Not evaluated yet',
            hint:
                mode === 'own'
                    ? 'You have not scored these'
                    : 'No scores submitted',
            tone: 'text-amber-600 dark:text-amber-400',
        },
    ];

    // Lecturers cannot tell evaluated from pending projects, so skip the cards.
    const showStatCards = mode !== 'list';

    return (
        <>
            <Head title="Leaderboard" />

            <div className="space-y-5">
                {/* Header */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Trophy className="h-6 w-6 text-primary" />
                            Leaderboard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {activeSession.name} · Updated{' '}
                            {lastUpdated.toLocaleTimeString([], {
                                hour: '2-digit',
                                minute: '2-digit',
                            })}{' '}
                            · refreshes every 15 seconds
                        </p>
                    </div>
                    <Button
                        onClick={refresh}
                        variant="outline"
                        size="sm"
                        disabled={isRefreshing}
                    >
                        <RefreshCw
                            className={`mr-2 h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`}
                        />
                        Refresh
                    </Button>
                </div>

                <StatusBanner mode={mode} resultsFinal={resultsFinal} />

                {/* Summary cards double as status filters */}
                {showStatCards && (
                    <div
                        className={`grid grid-cols-2 gap-3 ${statCards.length === 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3'}`}
                    >
                        {statCards.map((card) => (
                            <button
                                key={card.key}
                                type="button"
                                onClick={() => setStatus(card.key)}
                                aria-pressed={status === card.key}
                                className={`rounded-xl border bg-card p-4 text-left shadow-xs transition-colors hover:bg-muted/50 ${
                                    status === card.key
                                        ? 'border-primary ring-1 ring-primary'
                                        : 'border-border'
                                }`}
                            >
                                <div className="text-xs font-medium text-muted-foreground">
                                    {card.label}
                                </div>
                                <div
                                    className={`mt-1 text-2xl font-bold tabular-nums ${card.tone}`}
                                >
                                    {counts[card.key]}
                                </div>
                                <div className="mt-0.5 text-[11px] text-muted-foreground">
                                    {card.hint}
                                </div>
                            </button>
                        ))}
                    </div>
                )}

                {/* Toolbar */}
                <div className="flex flex-col gap-3 md:flex-row md:items-center">
                    <Select value={categoryId} onValueChange={setCategoryId}>
                        <SelectTrigger
                            className="md:w-80"
                            aria-label="Category"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All categories</SelectItem>
                            {categories.map((cat) => (
                                <SelectItem key={cat.id} value={String(cat.id)}>
                                    [{cat.code}] {cat.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by project title, code or participant"
                            className="pl-9"
                            aria-label="Search projects"
                        />
                    </div>
                </div>

                {canSeeNominations && (
                    <div
                        role="tablist"
                        aria-label="Leaderboard view"
                        className="inline-flex rounded-lg border bg-muted/40 p-1"
                    >
                        {(
                            [
                                ['projects', 'Project ranking', Trophy, null],
                                [
                                    'presenters',
                                    mode === 'own'
                                        ? 'Your Best Presenter nominations'
                                        : 'Best Presenter',
                                    Mic,
                                    nominations.length,
                                ],
                            ] as const
                        ).map(([key, label, Icon, count]) => (
                            <button
                                key={key}
                                type="button"
                                role="tab"
                                aria-selected={view === key}
                                onClick={() => setView(key)}
                                className={`flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                    view === key
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <Icon className="h-4 w-4" />
                                {label}
                                {count !== null && (
                                    <Badge
                                        variant="secondary"
                                        className="px-1.5 py-0 text-[10px] tabular-nums"
                                    >
                                        {count}
                                    </Badge>
                                )}
                            </button>
                        ))}
                    </div>
                )}

                {view === 'presenters' && canSeeNominations ? (
                    <BestPresenterPanel
                        nominations={nominations}
                        mode={mode}
                        showCategory={showCategory}
                    />
                ) : (
                    <>
                        {mode === 'scores' && <ScoringRules />}

                        {/* Results table */}
                        <Card className="gap-0 overflow-hidden p-0">
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader className="bg-muted/40">
                                        <TableRow className="hover:bg-transparent">
                                            {mode === 'scores' && (
                                                <TableHead className="w-16 pl-4 text-center">
                                                    Rank
                                                </TableHead>
                                            )}
                                            <TableHead
                                                className={
                                                    mode === 'scores'
                                                        ? ''
                                                        : 'pl-4'
                                                }
                                            >
                                                Project
                                            </TableHead>
                                            {mode === 'scores' && (
                                                <>
                                                    <TableHead className="hidden w-32 text-center md:table-cell">
                                                        Round 1 avg
                                                    </TableHead>
                                                    <TableHead className="hidden w-32 text-center md:table-cell">
                                                        Round 2 avg
                                                    </TableHead>
                                                    <TableHead className="w-32 text-center">
                                                        Final score
                                                    </TableHead>
                                                    <TableHead className="w-28 text-center">
                                                        Award
                                                    </TableHead>
                                                </>
                                            )}
                                            {mode === 'own' && (
                                                <>
                                                    <TableHead className="w-32 text-center">
                                                        Your R1 score
                                                    </TableHead>
                                                    <TableHead className="w-32 text-center">
                                                        Your R2 score
                                                    </TableHead>
                                                    <TableHead className="w-24 text-center">
                                                        Status
                                                    </TableHead>
                                                </>
                                            )}
                                            {mode === 'list' && (
                                                <TableHead className="w-28 text-center">
                                                    Award
                                                </TableHead>
                                            )}
                                            <TableHead className="w-12 pr-4">
                                                <span className="sr-only">
                                                    Details
                                                </span>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {visible.length === 0 ? (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={columnCount}
                                                    className="py-12 text-center text-sm text-muted-foreground"
                                                >
                                                    {inCategory.length === 0
                                                        ? 'No approved projects in this category yet.'
                                                        : 'No projects match the current filter or search.'}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            visible.map((row) => (
                                                <ProjectRow
                                                    key={row.project.id}
                                                    row={row}
                                                    mode={mode}
                                                    showCategory={showCategory}
                                                    columnCount={columnCount}
                                                    isOpen={expanded.has(
                                                        row.project.id,
                                                    )}
                                                    onToggle={() =>
                                                        toggle(row.project.id)
                                                    }
                                                />
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                            {visible.length > 0 && (
                                <div className="border-t px-4 py-2.5 text-xs text-muted-foreground">
                                    Showing {visible.length} of{' '}
                                    {inCategory.length} projects.
                                    {mode !== 'list' &&
                                        " Click a row to see each judge's score and comments."}
                                </div>
                            )}
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

function StatusBanner({
    mode,
    resultsFinal,
}: {
    mode: ViewMode;
    resultsFinal: boolean;
}) {
    if (resultsFinal) {
        return (
            <Banner
                tone="success"
                icon={<CheckCircle2 className="h-4 w-4" />}
                title="Results are final"
            >
                Round 2 is locked. The scores, ranks and awards below are the
                official results.
            </Banner>
        );
    }

    if (mode === 'scores') {
        return (
            <Banner
                tone="warning"
                icon={<AlertCircle className="h-4 w-4" />}
                title="Draft: judging is still in progress"
            >
                Scores and ranks change as judges submit evaluations. Only
                admins can see these numbers until Round 2 is locked.
            </Banner>
        );
    }

    if (mode === 'own') {
        return (
            <Banner
                tone="info"
                icon={<Lock className="h-4 w-4" />}
                title="You can only see your own scores"
            >
                Other judges' scores, averages and ranks stay hidden until Round
                2 is locked, so they cannot influence your evaluation.
            </Banner>
        );
    }

    return (
        <Banner
            tone="info"
            icon={<Lock className="h-4 w-4" />}
            title="Scores are hidden until results are final"
        >
            Projects are listed by code. Scores, ranks and awards appear here
            once Round 2 is locked.
        </Banner>
    );
}

const BANNER_TONES = {
    success:
        'border-emerald-500/30 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300',
    warning:
        'border-amber-500/40 bg-amber-500/5 text-amber-700 dark:text-amber-300',
    info: 'border-sky-500/30 bg-sky-500/5 text-sky-700 dark:text-sky-300',
};

function Banner({
    tone,
    icon,
    title,
    children,
}: {
    tone: keyof typeof BANNER_TONES;
    icon: ReactNode;
    title: string;
    children: ReactNode;
}) {
    return (
        <div
            role="status"
            className={`flex gap-3 rounded-lg border px-4 py-3 text-sm ${BANNER_TONES[tone]}`}
        >
            <span className="mt-0.5 shrink-0">{icon}</span>
            <div>
                <div className="font-semibold">{title}</div>
                <div className="text-foreground/80">{children}</div>
            </div>
        </div>
    );
}

function ScoringRules() {
    return (
        <details className="group rounded-lg border bg-muted/20 px-4 py-2.5 text-sm">
            <summary className="flex cursor-pointer list-none items-center gap-2 font-medium text-muted-foreground">
                <Info className="h-4 w-4" />
                How the final score and rank are worked out
                <ChevronDown className="ml-auto h-4 w-4 transition-transform group-open:rotate-180" />
            </summary>
            <ul className="mt-2 list-disc space-y-1 pl-6 text-muted-foreground">
                <li>
                    Each round's score is the average of every judge who
                    evaluated the project in that round.
                </li>
                <li>
                    <strong className="text-foreground">Final score</strong> is
                    the Round 2 average if the project has any Round 2 score;
                    otherwise it is the Round 1 average.
                </li>
                <li>
                    Projects are ranked within their own category. Round 2
                    finalists always rank above projects judged only in Round 1.
                </li>
                <li>Projects with no scores yet are not ranked.</li>
            </ul>
        </details>
    );
}

const PODIUM: Record<number, string> = {
    1: 'bg-yellow-500 text-slate-950',
    2: 'bg-slate-300 text-slate-950',
    3: 'bg-amber-600 text-white',
};

function RankBadge({ rank }: { rank: number | null }) {
    if (rank === null) {
        return <span className="text-muted-foreground">–</span>;
    }

    if (PODIUM[rank]) {
        return (
            <span
                className={`mx-auto flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold ${PODIUM[rank]}`}
            >
                {rank}
            </span>
        );
    }

    return (
        <span className="font-mono text-sm text-muted-foreground">{rank}</span>
    );
}

function RoundAverage({ avg, judges }: { avg?: number; judges?: number }) {
    if (!judges) {
        return <span className="text-muted-foreground">–</span>;
    }

    return (
        <div>
            <div className="font-mono text-sm">{fmt(avg)}</div>
            <div className="text-[11px] text-muted-foreground">
                {judges} judge{judges === 1 ? '' : 's'}
            </div>
        </div>
    );
}

function Dash() {
    return <span className="text-muted-foreground">–</span>;
}

function ProjectRow({
    row,
    mode,
    showCategory,
    columnCount,
    isOpen,
    onToggle,
}: {
    row: Row;
    mode: ViewMode;
    showCategory: boolean;
    columnCount: number;
    isOpen: boolean;
    onToggle: () => void;
}) {
    const { project, category, rank, evaluated, inRound2 } = row;
    const judges = project.judges ?? [];
    const canExpand = mode !== 'list';
    const ownR1 = judges.find((j) => j.round === 1)?.score;
    const ownR2 = judges.find((j) => j.round === 2)?.score;
    const presenterNominee =
        mode === 'scores'
            ? project.best_presenter?.name
            : judges.find((j) => j.best_presenter)?.best_presenter;

    return (
        <Fragment>
            <TableRow
                onClick={canExpand ? onToggle : undefined}
                className={`${canExpand ? 'cursor-pointer' : ''} ${isOpen ? 'border-b-0 bg-muted/30' : ''}`}
            >
                {mode === 'scores' && (
                    <TableCell className="pl-4 text-center">
                        <RankBadge rank={rank} />
                    </TableCell>
                )}
                <TableCell
                    className={`py-3 whitespace-normal ${mode === 'scores' ? '' : 'pl-4'}`}
                >
                    <div className="leading-snug font-medium">
                        {project.title}
                    </div>
                    <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                        <span className="font-mono font-semibold">
                            {project.pcode ?? 'No code'}
                        </span>
                        {project.username && <span>· {project.username}</span>}
                        {showCategory && category && (
                            <Badge
                                variant="secondary"
                                className="px-1.5 py-0 text-[10px]"
                                title={category.name}
                            >
                                {category.code}
                            </Badge>
                        )}
                        {mode === 'scores' && inRound2 && (
                            <Badge
                                variant="outline"
                                className="border-primary/40 px-1.5 py-0 text-[10px] text-primary"
                            >
                                Round 2
                            </Badge>
                        )}
                    </div>
                    {presenterNominee && (
                        <div className="mt-1 flex items-center gap-1 text-xs text-amber-700 dark:text-amber-400">
                            <Mic className="h-3 w-3 shrink-0" />
                            <span>
                                Best Presenter nominee:{' '}
                                <strong>{presenterNominee}</strong>
                                {mode === 'scores' &&
                                    project.best_presenter &&
                                    ` (${votes(project.best_presenter.votes)})`}
                            </span>
                        </div>
                    )}
                </TableCell>

                {mode === 'scores' && (
                    <>
                        <TableCell className="hidden text-center md:table-cell">
                            <RoundAverage
                                avg={project.avg_r1}
                                judges={project.judges_r1}
                            />
                        </TableCell>
                        <TableCell className="hidden text-center md:table-cell">
                            <RoundAverage
                                avg={project.avg_r2}
                                judges={project.judges_r2}
                            />
                        </TableCell>
                        <TableCell className="text-center">
                            {evaluated ? (
                                <div>
                                    <div className="font-mono text-base font-bold text-primary">
                                        {fmt(project.final_score)}
                                    </div>
                                    <div className="text-[11px] text-muted-foreground">
                                        from Round {inRound2 ? 2 : 1}
                                    </div>
                                </div>
                            ) : (
                                <Badge
                                    variant="outline"
                                    className="font-normal text-amber-600 dark:text-amber-400"
                                >
                                    Not evaluated
                                </Badge>
                            )}
                        </TableCell>
                        <TableCell className="text-center">
                            {project.award_level ? (
                                <AwardBadge award={project.award_level} />
                            ) : (
                                <Dash />
                            )}
                        </TableCell>
                    </>
                )}

                {mode === 'own' && (
                    <>
                        <TableCell className="text-center font-mono">
                            {ownR1 !== undefined ? fmt(ownR1) : <Dash />}
                        </TableCell>
                        <TableCell className="text-center font-mono">
                            {ownR2 !== undefined ? fmt(ownR2) : <Dash />}
                        </TableCell>
                        <TableCell className="text-center">
                            {evaluated ? (
                                <Badge
                                    variant="outline"
                                    className="font-normal text-emerald-600 dark:text-emerald-400"
                                >
                                    Scored
                                </Badge>
                            ) : (
                                <Badge
                                    variant="outline"
                                    className="font-normal text-amber-600 dark:text-amber-400"
                                >
                                    Not yet
                                </Badge>
                            )}
                        </TableCell>
                    </>
                )}

                {mode === 'list' && (
                    <TableCell className="text-center">
                        {project.award_level ? (
                            <AwardBadge award={project.award_level} />
                        ) : (
                            <Dash />
                        )}
                    </TableCell>
                )}

                <TableCell className="pr-4 text-right">
                    {canExpand && (
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                onToggle();
                            }}
                            aria-expanded={isOpen}
                            aria-label={
                                isOpen
                                    ? 'Hide evaluation details'
                                    : 'Show evaluation details'
                            }
                            className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            <ChevronDown
                                className={`h-4 w-4 transition-transform ${isOpen ? 'rotate-180' : ''}`}
                            />
                        </button>
                    )}
                </TableCell>
            </TableRow>

            {isOpen && (
                <TableRow className="bg-muted/30 hover:bg-muted/30">
                    <TableCell
                        colSpan={columnCount}
                        className="px-4 pt-0 pb-4 whitespace-normal"
                    >
                        <EvaluationDetails project={project} mode={mode} />
                    </TableCell>
                </TableRow>
            )}
        </Fragment>
    );
}

function EvaluationDetails({
    project,
    mode,
}: {
    project: LeaderboardProject;
    mode: ViewMode;
}) {
    const judges = project.judges ?? [];
    const rounds = [1, 2].filter((round) =>
        judges.some((j) => j.round === round),
    );

    if (judges.length === 0) {
        return (
            <p className="rounded-md border border-dashed bg-background px-4 py-3 text-sm text-muted-foreground">
                {mode === 'own'
                    ? 'You have not submitted a score for this project yet.'
                    : 'No judge has submitted a score for this project yet.'}
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {/* Round averages are hidden in the table on small screens, so repeat them here. */}
            {mode === 'scores' && (
                <div className="flex flex-wrap gap-x-6 gap-y-1 text-xs text-muted-foreground md:hidden">
                    <span>
                        Round 1 avg:{' '}
                        <strong className="text-foreground">
                            {project.judges_r1 ? fmt(project.avg_r1) : '–'}
                        </strong>
                    </span>
                    <span>
                        Round 2 avg:{' '}
                        <strong className="text-foreground">
                            {project.judges_r2 ? fmt(project.avg_r2) : '–'}
                        </strong>
                    </span>
                </div>
            )}

            <div
                className={`grid gap-3 ${rounds.length > 1 ? 'lg:grid-cols-2' : ''}`}
            >
                {rounds.map((round) => (
                    <div
                        key={round}
                        className="rounded-md border bg-background"
                    >
                        <div className="border-b px-3 py-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Round {round}
                        </div>
                        <ul className="divide-y">
                            {judges
                                .filter((j) => j.round === round)
                                .map((judge, i) => (
                                    <li
                                        key={`${judge.name}-${i}`}
                                        className="px-3 py-2.5 text-sm"
                                    >
                                        <div className="flex items-baseline justify-between gap-3">
                                            <JudgeName judge={judge} />
                                            <span className="font-mono font-semibold">
                                                {fmt(judge.score)}
                                            </span>
                                        </div>
                                        {judge.best_presenter && (
                                            <div className="mt-1 flex items-center gap-1 text-xs text-amber-700 dark:text-amber-400">
                                                <Mic className="h-3 w-3 shrink-0" />
                                                Nominated{' '}
                                                <strong>
                                                    {judge.best_presenter}
                                                </strong>{' '}
                                                for Best Presenter
                                            </div>
                                        )}
                                        <p
                                            className={`mt-1 text-xs ${judge.comments ? 'text-muted-foreground' : 'text-muted-foreground/60 italic'}`}
                                        >
                                            {judge.comments || 'No comments.'}
                                        </p>
                                    </li>
                                ))}
                        </ul>
                    </div>
                ))}
            </div>
        </div>
    );
}

const votes = (count: number) => `${count} vote${count === 1 ? '' : 's'}`;

function JudgeName({ judge }: { judge: JudgeScore }) {
    const showUsername =
        judge.username &&
        judge.username.toLowerCase() !== judge.name.toLowerCase();

    return (
        <span>
            <span className="font-medium">{judge.name}</span>
            {showUsername && (
                <span className="ml-1.5 font-mono text-xs text-muted-foreground">
                    ({judge.username})
                </span>
            )}
        </span>
    );
}

/**
 * Every Best Presenter nomination in the given rows, one entry per project and
 * nominee, most votes first. Round 1 and Round 2 nominations count together.
 */
function collectNominations(rows: Row[]): Nomination[] {
    const byKey = new Map<string, Nomination>();

    rows.forEach((row) => {
        (row.project.judges ?? []).forEach((judge) => {
            if (!judge.best_presenter) {
                return;
            }

            const key = `${row.project.id}|${judge.best_presenter}`;
            const entry = byKey.get(key) ?? {
                nominee: judge.best_presenter,
                row,
                votes: 0,
                rounds: [],
                judges: [],
            };

            entry.votes++;

            if (!entry.rounds.includes(judge.round)) {
                entry.rounds.push(judge.round);
            }

            entry.judges.push(`${judge.name} (R${judge.round})`);
            byKey.set(key, entry);
        });
    });

    return [...byKey.values()].sort(
        (a, b) =>
            b.votes - a.votes ||
            (b.row.project.final_score ?? 0) - (a.row.project.final_score ?? 0),
    );
}

function BestPresenterPanel({
    nominations,
    mode,
    showCategory,
}: {
    nominations: Nomination[];
    mode: ViewMode;
    showCategory: boolean;
}) {
    return (
        <Card className="gap-0 overflow-hidden p-0">
            <div className="border-b bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                {mode === 'own'
                    ? 'Presenters you nominated for Best Presenter, in Round 1 or Round 2.'
                    : "Judges nominate a presenter while scoring a project, in Round 1 or Round 2. Presenters are listed by the number of judges who nominated them; ties are ordered by the project's final score."}
            </div>
            {nominations.length === 0 ? (
                <div className="px-4 py-12 text-center text-sm text-muted-foreground">
                    {mode === 'own'
                        ? 'You have not nominated anyone yet.'
                        : 'No judge has nominated a Best Presenter for these projects yet.'}
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <Table>
                        <TableHeader className="bg-muted/40">
                            <TableRow className="hover:bg-transparent">
                                {mode === 'scores' && (
                                    <TableHead className="w-16 pl-4 text-center">
                                        #
                                    </TableHead>
                                )}
                                <TableHead
                                    className={mode === 'scores' ? '' : 'pl-4'}
                                >
                                    Nominee
                                </TableHead>
                                <TableHead>Project</TableHead>
                                {mode === 'scores' && (
                                    <TableHead className="w-24 text-center">
                                        Votes
                                    </TableHead>
                                )}
                                <TableHead className="pr-4">
                                    Nominated by
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {nominations.map((entry, index) => {
                                const { project, category } = entry.row;
                                const rank =
                                    index > 0 &&
                                    nominations[index - 1].votes === entry.votes
                                        ? null
                                        : index + 1;

                                return (
                                    <TableRow
                                        key={`${project.id}-${entry.nominee}`}
                                    >
                                        {mode === 'scores' && (
                                            <TableCell className="pl-4 text-center">
                                                {rank !== null && (
                                                    <RankBadge rank={rank} />
                                                )}
                                            </TableCell>
                                        )}
                                        <TableCell
                                            className={`font-medium whitespace-normal ${mode === 'scores' ? '' : 'pl-4'}`}
                                        >
                                            {entry.nominee}
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            <div className="leading-snug">
                                                {project.title}
                                            </div>
                                            <div className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                                                <span className="font-mono font-semibold">
                                                    {project.pcode ?? 'No code'}
                                                </span>
                                                {showCategory && category && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="px-1.5 py-0 text-[10px]"
                                                        title={category.name}
                                                    >
                                                        {category.code}
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        {mode === 'scores' && (
                                            <TableCell className="text-center">
                                                <div className="font-mono text-base font-bold text-amber-700 dark:text-amber-400">
                                                    {entry.votes}
                                                </div>
                                                <div className="text-[11px] text-muted-foreground">
                                                    {entry.rounds
                                                        .sort()
                                                        .map((r) => `R${r}`)
                                                        .join(' + ')}
                                                </div>
                                            </TableCell>
                                        )}
                                        <TableCell className="pr-4 text-xs whitespace-normal text-muted-foreground">
                                            {entry.judges.join(', ')}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>
            )}
        </Card>
    );
}
