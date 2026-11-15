import { Head, router } from '@inertiajs/react';
import { BarChart3 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { Label } from '@/components/ui/label';

/*
 * Colors: dataviz reference palette, validated with validate_palette.js in both modes.
 * Two-series charts use categorical slots 1 (blue) and 2 (orange). Judge deviation
 * is diverging: blue for "scores above peers", red for "below peers".
 */
const SERIES_1 = { light: '#2a78d6', dark: '#3987e5' };
const SERIES_2 = { light: '#eb6834', dark: '#d95926' };

interface SessionOption {
    id: number;
    name: string;
    is_active: boolean;
}

interface TrendRow {
    session_id: number;
    name: string;
    registered: number;
    approved: number;
    participants: number;
    judges: number;
    evaluations: number;
}

interface SessionAnalytics {
    summary: {
        registered: number;
        approved: number;
        pending: number;
        judges: number;
        evaluations: number;
        average_score: number | null;
    };
    byCategory: Array<{ category: string; registered: number; approved: number; average_score: number | null }>;
    byInstitution: Array<{ institution: string; registered: number; approved: number }>;
    scoreDistribution: Array<{ band: string; round1: number; round2: number }>;
    judgeConsistency: Array<{
        judge: string;
        evaluations: number;
        average_given: number;
        shared_evaluations: number;
        average_deviation: number | null;
    }>;
}

interface Props {
    sessions: SessionOption[];
    selectedSessionId: number | null;
    trend: TrendRow[];
    session: SessionAnalytics | null;
    minSharedEvaluations: number;
}

const participationConfig = {
    registered: { label: 'Registered', theme: SERIES_1 },
    approved: { label: 'Approved', theme: SERIES_2 },
} satisfies ChartConfig;

const distributionConfig = {
    round1: { label: 'Round 1', theme: SERIES_1 },
    round2: { label: 'Round 2', theme: SERIES_2 },
} satisfies ChartConfig;

const numberFormat = new Intl.NumberFormat('en-MY');

function StatTile({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-border/60 bg-card/60 p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-semibold">{value}</p>
        </div>
    );
}

/** Every chart also offers its numbers as a table (screen readers, exact values, print). */
function DataTable({ caption, headers, rows }: { caption: string; headers: string[]; rows: Array<Array<ReactNode>> }) {
    return (
        <details className="mt-3 text-xs">
            <summary className="cursor-pointer font-medium text-muted-foreground hover:text-foreground">View as table</summary>
            <div className="mt-2 overflow-x-auto">
                <table className="w-full">
                    <caption className="sr-only">{caption}</caption>
                    <thead>
                        <tr className="border-b border-border/60 text-left text-muted-foreground">
                            {headers.map((header, index) => (
                                <th key={header} scope="col" className={`py-1.5 pr-3 font-medium ${index > 0 ? 'text-right' : ''}`}>
                                    {header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, rowIndex) => (
                            <tr key={rowIndex} className="border-b border-border/30 last:border-0">
                                {row.map((cell, cellIndex) => (
                                    <td key={cellIndex} className={`py-1.5 pr-3 ${cellIndex > 0 ? 'text-right tabular-nums' : ''}`}>
                                        {cell}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </details>
    );
}

function GroupedColumns({
    config,
    data,
    categoryKey,
    seriesKeys,
    label,
}: {
    config: ChartConfig;
    data: object[];
    categoryKey: string;
    seriesKeys: [string, string];
    label: string;
}) {
    return (
        <ChartContainer config={config} className="aspect-auto h-64 w-full" role="img" aria-label={label}>
            <BarChart data={data} barGap={2} margin={{ top: 8, right: 8, left: -12, bottom: 0 }}>
                <CartesianGrid vertical={false} strokeWidth={1} />
                <XAxis dataKey={categoryKey} tickLine={false} axisLine={false} tickMargin={8} interval={0} fontSize={11} />
                <YAxis allowDecimals={false} tickLine={false} axisLine={false} width={40} fontSize={11} />
                <ChartTooltip cursor={{ fillOpacity: 0.06 }} content={<ChartTooltipContent />} />
                <ChartLegend
                    content={({ payload, verticalAlign }) => <ChartLegendContent payload={payload} verticalAlign={verticalAlign} />}
                />
                {seriesKeys.map((key) => (
                    <Bar key={key} dataKey={key} fill={`var(--color-${key})`} radius={[4, 4, 0, 0]} maxBarSize={24} />
                ))}
            </BarChart>
        </ChartContainer>
    );
}

/** Diverging bar from a zero line: right = scores above other judges, left = below. */
function DeviationBar({ value, scale }: { value: number; scale: number }) {
    const width = Math.min(50, (Math.abs(value) / scale) * 50);
    const positive = value >= 0;

    return (
        <div className="relative h-3 w-full min-w-[120px]" aria-hidden="true">
            <div className="absolute inset-y-0 left-1/2 w-px bg-border" />
            <div
                className={`absolute inset-y-0 ${positive ? 'rounded-r bg-[#2a78d6] dark:bg-[#3987e5]' : 'rounded-l bg-[#e34948] dark:bg-[#e66767]'}`}
                style={positive ? { left: '50%', width: `${width}%` } : { right: '50%', width: `${width}%` }}
            />
        </div>
    );
}

export default function AnalyticsIndex({ sessions, selectedSessionId, trend, session, minSharedEvaluations }: Props) {
    const changeSession = (id: string) => {
        router.get('/dashboard/analytics', { session_id: id }, { preserveScroll: true, preserveState: true });
    };

    const deviationScale = Math.max(5, ...(session?.judgeConsistency ?? []).map((row) => Math.abs(row.average_deviation ?? 0)));

    return (
        <>
            <Head title="Analytics" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                            <BarChart3 className="h-8 w-8 text-primary" />
                            Analytics
                        </h1>
                        <p className="text-sm text-muted-foreground">Participation trends, category results and judge scoring patterns.</p>
                    </div>

                    {sessions.length > 0 && (
                        <div className="space-y-1.5 sm:w-72">
                            <Label htmlFor="analytics-session">Competition session</Label>
                            <select
                                id="analytics-session"
                                value={selectedSessionId ?? ''}
                                onChange={(e) => changeSession(e.target.value)}
                                className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                {sessions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name}
                                        {option.is_active ? ' (active)' : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>

                {session && (
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                        <StatTile label="Registered projects" value={numberFormat.format(session.summary.registered)} />
                        <StatTile label="Approved" value={numberFormat.format(session.summary.approved)} />
                        <StatTile label="Awaiting review" value={numberFormat.format(session.summary.pending)} />
                        <StatTile label="Judges who scored" value={numberFormat.format(session.summary.judges)} />
                        <StatTile label="Evaluations" value={numberFormat.format(session.summary.evaluations)} />
                        <StatTile label="Average score" value={session.summary.average_score === null ? '–' : `${session.summary.average_score}%`} />
                    </div>
                )}

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card className="border border-border/50 bg-card/60">
                        <CardHeader>
                            <CardTitle className="text-base font-semibold">Participation across sessions</CardTitle>
                            <CardDescription>Projects registered and approved in each competition session.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {trend.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No sessions yet.</p>
                            ) : (
                                <>
                                    <GroupedColumns
                                        config={participationConfig}
                                        data={trend}
                                        categoryKey="name"
                                        seriesKeys={['registered', 'approved']}
                                        label="Column chart of registered and approved projects per session"
                                    />
                                    <DataTable
                                        caption="Participation across sessions"
                                        headers={['Session', 'Registered', 'Approved', 'Participants', 'Judges', 'Evaluations']}
                                        rows={trend.map((row) => [row.name, row.registered, row.approved, row.participants, row.judges, row.evaluations])}
                                    />
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {session && (
                        <Card className="border border-border/50 bg-card/60">
                            <CardHeader>
                                <CardTitle className="text-base font-semibold">Score distribution</CardTitle>
                                <CardDescription>Number of evaluations in each score band, by round.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {session.summary.evaluations === 0 ? (
                                    <p className="text-sm text-muted-foreground">No evaluations in this session yet.</p>
                                ) : (
                                    <>
                                        <GroupedColumns
                                            config={distributionConfig}
                                            data={session.scoreDistribution}
                                            categoryKey="band"
                                            seriesKeys={['round1', 'round2']}
                                            label="Column chart of evaluations per score band for Round 1 and Round 2"
                                        />
                                        <DataTable
                                            caption="Score distribution"
                                            headers={['Score band (%)', 'Round 1', 'Round 2']}
                                            rows={session.scoreDistribution.map((row) => [row.band, row.round1, row.round2])}
                                        />
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>

                {session && (
                    <div className="grid gap-6 xl:grid-cols-3">
                        <Card className="border border-border/50 bg-card/60 xl:col-span-2">
                            <CardHeader>
                                <CardTitle className="text-base font-semibold">Projects by category</CardTitle>
                                <CardDescription>Registrations, approvals and the average final score in each category.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {session.byCategory.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No projects in this session yet.</p>
                                ) : (
                                    <>
                                        <GroupedColumns
                                            config={participationConfig}
                                            data={session.byCategory}
                                            categoryKey="category"
                                            seriesKeys={['registered', 'approved']}
                                            label="Column chart of registered and approved projects per category"
                                        />
                                        <DataTable
                                            caption="Projects by category"
                                            headers={['Category', 'Registered', 'Approved', 'Average final score']}
                                            rows={session.byCategory.map((row) => [
                                                row.category,
                                                row.registered,
                                                row.approved,
                                                row.average_score === null ? '–' : `${row.average_score}%`,
                                            ])}
                                        />
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="border border-border/50 bg-card/60">
                            <CardHeader>
                                <CardTitle className="text-base font-semibold">By institution</CardTitle>
                                <CardDescription>UTeM and other institutions in this session.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <table className="w-full text-sm">
                                    <caption className="sr-only">Projects by institution</caption>
                                    <thead>
                                        <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                            <th scope="col" className="py-1.5 font-medium">Institution</th>
                                            <th scope="col" className="py-1.5 text-right font-medium">Registered</th>
                                            <th scope="col" className="py-1.5 text-right font-medium">Approved</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {session.byInstitution.map((row) => (
                                            <tr key={row.institution} className="border-b border-border/30 last:border-0">
                                                <td className="py-2">{row.institution}</td>
                                                <td className="py-2 text-right tabular-nums">{row.registered}</td>
                                                <td className="py-2 text-right tabular-nums">{row.approved}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {session && (
                    <Card className="border border-border/50 bg-card/60">
                        <CardHeader>
                            <CardTitle className="text-base font-semibold">Judge scoring consistency</CardTitle>
                            <CardDescription>
                                How each judge&rsquo;s scores compare with the other judges of the same project and round. Above peers
                                means more lenient; below means stricter. Shown once a judge shares at least {minSharedEvaluations}{' '}
                                evaluations with other judges.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            {session.judgeConsistency.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No evaluations in this session yet.</p>
                            ) : (
                                <table className="w-full text-sm">
                                    <caption className="sr-only">Judge scoring consistency</caption>
                                    <thead>
                                        <tr className="border-b border-border/60 text-left text-xs text-muted-foreground">
                                            <th scope="col" className="py-1.5 pr-3 font-medium">Judge</th>
                                            <th scope="col" className="py-1.5 pr-3 text-right font-medium">Evaluations</th>
                                            <th scope="col" className="py-1.5 pr-3 text-right font-medium">Average given</th>
                                            <th scope="col" className="py-1.5 pr-3 text-center font-medium">
                                                <span className="flex justify-between gap-2">
                                                    <span>Below peers</span>
                                                    <span>Above peers</span>
                                                </span>
                                            </th>
                                            <th scope="col" className="py-1.5 text-right font-medium">Difference</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {session.judgeConsistency.map((row) => (
                                            <tr key={row.judge} className="border-b border-border/30 last:border-0">
                                                <td className="py-2 pr-3">{row.judge}</td>
                                                <td className="py-2 pr-3 text-right tabular-nums">{row.evaluations}</td>
                                                <td className="py-2 pr-3 text-right tabular-nums">{row.average_given}%</td>
                                                <td className="py-2 pr-3">
                                                    {row.average_deviation !== null && (
                                                        <DeviationBar value={row.average_deviation} scale={deviationScale} />
                                                    )}
                                                </td>
                                                <td className="py-2 text-right tabular-nums">
                                                    {row.average_deviation === null ? (
                                                        <span className="text-xs text-muted-foreground">
                                                            Too few shared ({row.shared_evaluations})
                                                        </span>
                                                    ) : (
                                                        `${row.average_deviation > 0 ? '+' : ''}${row.average_deviation} pts`
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

AnalyticsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Analytics', href: '/dashboard/analytics' },
    ],
};
