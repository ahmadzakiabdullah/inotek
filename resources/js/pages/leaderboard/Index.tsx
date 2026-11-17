import { Head, Link } from '@inertiajs/react';
import {
    Trophy,
    AlertCircle,
    RefreshCw,
    BarChart3,
} from 'lucide-react';
import React, { useState, useEffect } from 'react';
import { AwardBadge } from '@/components/leaderboard/award-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

interface Category {
    id: number;
    code: string;
    name: string;
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
    judges?: { name: string; round: number; score: number; comments: string | null; submitted_at: string }[];
    best_presenter?: { name: string; votes: number } | null;
}

interface Props {
    activeSession: {
        id: number;
        name: string;
    } | null;
    categories: Category[];
    leaderboardData: Record<number, LeaderboardProject[]>;
    resultsPublished: boolean;
}

export default function LeaderboardIndex({
    activeSession,
    categories,
    leaderboardData,
    resultsPublished,
}: Props) {
    // Public leaderboard only; staff use leaderboard/App. Scores appear once Round 2 is locked.
    const showResults = resultsPublished;
    const [localLeaderboardData, setLocalLeaderboardData] =
        useState(leaderboardData);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [lastUpdated, setLastUpdated] = useState<Date | null>(new Date());
    const [activeCategoryTab, setActiveCategoryTab] = useState(
        categories.length > 0 ? categories[0].id.toString() : '',
    );

    useEffect(() => {
        setLocalLeaderboardData(leaderboardData);
    }, [leaderboardData]);

    // Fetch updated score rankings
    const fetchRankings = () => {
        setIsRefreshing(true);
        fetch('/leaderboard?api=true')
            .then((res) => res.json())
            .then((data) => {
                setLocalLeaderboardData(data.leaderboardData);
                setLastUpdated(new Date());
            })
            .catch((err) => console.error('Error fetching rankings:', err))
            .finally(() => setIsRefreshing(false));
    };

    // WebSocket listening & Polling Fallback
    useEffect(() => {
        // 1. Setup Laravel Echo channel if window.Echo exists
        const echo = (window as any).Echo;
        let channel: any = null;

        if (echo) {
            channel = echo.channel('leaderboard');
            channel.listen('.score.updated', (e: any) => {
                fetchRankings();
            });
        }

        // 2. Setup 15-second polling fallback
        const pollInterval = setInterval(() => {
            fetchRankings();
        }, 15000);

        return () => {
            if (channel && echo) {
                channel.stopListening('.score.updated');
            }

            clearInterval(pollInterval);
        };
    }, []);

    if (!activeSession) {
        return (
            <>
                <Head title="Live Leaderboard" />
                <div className="flex min-h-screen flex-col items-center justify-center space-y-4 bg-background p-6 text-center">
                    <AlertCircle className="h-16 w-16 text-muted-foreground/40" />
                    <h1 className="text-2xl font-bold tracking-tight">
                        No Active Session
                    </h1>
                    <p className="max-w-sm text-sm text-muted-foreground">
                        The live scoring leaderboard is currently unavailable
                        because there is no active competition session.
                    </p>
                    <Button asChild size="sm">
                        <Link href="/">Return to Home</Link>
                    </Button>
                </div>
            </>
        );
    }


    return (
        <>
            <Head title="Live Scoring Leaderboard" />

            <div className="min-h-screen bg-background px-6 py-8 text-foreground lg:px-8">
                <div className="mx-auto w-full max-w-7xl space-y-8">
                    {/* Header Banner */}
                    <div className="rounded-xl border border-border/60 bg-card/60 p-6 shadow-sm">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <span className="flex h-2 w-2 animate-pulse rounded-full bg-emerald-500" />
                                <span className="text-xs font-semibold tracking-widest text-emerald-600 uppercase dark:text-emerald-400">
                                    Live Standings
                                </span>
                            </div>
                            <h1 className="flex items-center gap-2 text-3xl font-extrabold tracking-tight lg:text-4xl">
                                <Trophy className="h-8 w-8 text-primary" />
                                INOTEK Leaderboard
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Real-time competition standings for{' '}
                                <span className="font-semibold text-foreground">
                                    {activeSession.name}
                                </span>
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            <Button
                                onClick={fetchRankings}
                                variant="outline"
                                size="sm"
                                disabled={isRefreshing}
                                className="h-9"
                            >
                                <RefreshCw
                                    className={`mr-2 h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`}
                                />
                                Refresh
                            </Button>
                            <Button
                                asChild
                                size="sm"
                                variant="default"
                                className="h-9 bg-primary text-primary-foreground"
                            >
                                <Link href="/dashboard">Dashboard</Link>
                            </Button>
                        </div>
                    </div>
                        <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-border/60 pt-3 text-xs text-muted-foreground">
                            <span>{showResults ? 'Results are final. Categories are ranked by their published score.' : 'Judging is in progress. Project standings will be finalized after judging closes.'}</span>
                            <span aria-live="polite">{lastUpdated ? `Updated ${lastUpdated.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : 'Updating automatically every 15 seconds'}</span>
                        </div>
                    </div>

                    {categories.length > 0 ? (
                        <Tabs
                            value={activeCategoryTab}
                            onValueChange={setActiveCategoryTab}
                            className="w-full space-y-6"
                        >
                            <div className="md:hidden">
                                <Select value={activeCategoryTab} onValueChange={setActiveCategoryTab}>
                                    <SelectTrigger aria-label="Choose a competition category"><SelectValue placeholder="Choose a category" /></SelectTrigger>
                                    <SelectContent>
                                        {categories.map((cat) => <SelectItem key={cat.id} value={cat.id.toString()}>[{cat.code}] {cat.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="hidden overflow-x-auto pb-1 md:block">
                                <TabsList className="flex w-max min-w-full border border-border bg-muted/60 p-1">
                                    {categories.map((cat) => (
                                        <TabsTrigger
                                            key={cat.id}
                                            value={cat.id.toString()}
                                            className="rounded px-4 py-2 text-xs font-semibold md:text-sm"
                                        >
                                            [{cat.code}] {cat.name}
                                        </TabsTrigger>
                                    ))}
                                </TabsList>
                            </div>

                            {categories.map((cat) => {
                                const projects =
                                    localLeaderboardData[cat.id] || [];

                                return (
                                    <TabsContent
                                        key={cat.id}
                                        value={cat.id.toString()}
                                        className="space-y-4"
                                    >
                                        <Card className="overflow-hidden border-border/60 bg-card shadow-sm">
                                            <CardHeader className="border-b border-border/70 bg-muted/20 p-6">
                                                <div className="flex items-center justify-between">
                                                    <div>
                                                        <CardTitle className="flex items-center gap-2 text-xl font-bold">
                                                            <BarChart3 className="h-5 w-5 text-primary" />
                                                            Category Standings
                                                        </CardTitle>
                                                        <CardDescription className="mt-1">
                                                            {showResults
                                                                ? `Final results for ${cat.name}.`
                                                                : `Projects registered under ${cat.name}. Results will appear after judging is finalized.`}
                                                        </CardDescription>
                                                    </div>
                                                    <Badge
                                                        variant="secondary"
                                                        className="font-mono"
                                                    >
                                                        {projects.length}{' '}
                                                        projects
                                                    </Badge>
                                                </div>
                                            </CardHeader>
                                            <div className="border-b border-border/60 bg-muted/10 px-6 py-3 text-xs text-muted-foreground">
                                                {showResults ? 'Rankings use the finalized published score for this category.' : 'Rankings are not final while judging is underway; scores are hidden until results are published.'}
                                            </div>
                                            <CardContent className="p-0">
                                                <div className="overflow-x-auto">
                                                    <Table>
                                                        <TableHeader className="bg-muted/30">
                                                            <TableRow className="border-b border-border/70 hover:bg-transparent">
                                                                <TableHead className="w-[80px] py-4 pl-6 text-center font-bold">
                                                                    Rank
                                                                </TableHead>
                                                                <TableHead className="w-[120px] font-bold">
                                                                    Code
                                                                </TableHead>
                                                                <TableHead className="font-bold">
                                                                    Project
                                                                    Details
                                                                </TableHead>
                                                                <TableHead className="w-[150px] pr-6 text-right font-bold">
                                                                    Award
                                                                </TableHead>
                                                            </TableRow>
                                                        </TableHeader>
                                                        <TableBody>
                                                            {projects.length >
                                                            0 ? (
                                                                projects.map(
                                                                    (
                                                                        proj,
                                                                        idx,
                                                                    ) => {
                                                                        const rank =
                                                                            idx +
                                                                            1;
                                                                        const isTop3 =
                                                                            rank <=
                                                                            3;

                                                                        return (
                                                                            <TableRow
                                                                                key={
                                                                                    proj.id
                                                                                }
                                                                                className={`border-b border-border/60 transition-colors hover:bg-muted/50 ${rank === 1 ? 'bg-amber-500/[0.04]' : ''}`}
                                                                            >
                                                                                <TableCell className="py-4 pl-6 text-center align-middle font-black">
                                                                                    {rank ===
                                                                                    1 ? (
                                                                                        <span
                                                                                            className="mx-auto flex h-7 w-7 items-center justify-center rounded-full bg-yellow-500 text-slate-950 shadow-lg shadow-yellow-500/10"
                                                                                            title="First Place"
                                                                                        >
                                                                                            1
                                                                                        </span>
                                                                                    ) : rank ===
                                                                                      2 ? (
                                                                                        <span
                                                                                            className="mx-auto flex h-7 w-7 items-center justify-center rounded-full bg-slate-300 text-slate-950 shadow-lg shadow-slate-300/10"
                                                                                            title="Second Place"
                                                                                        >
                                                                                            2
                                                                                        </span>
                                                                                    ) : rank ===
                                                                                      3 ? (
                                                                                        <span
                                                                                            className="mx-auto flex h-7 w-7 items-center justify-center rounded-full bg-amber-600 text-white shadow-lg shadow-amber-600/10"
                                                                                            title="Third Place"
                                                                                        >
                                                                                            3
                                                                                        </span>
                                                                                    ) : (
                                                                                        <span className="font-mono text-sm text-muted-foreground">
                                                                                            {
                                                                                                rank
                                                                                            }
                                                                                        </span>
                                                                                    )}
                                                                                </TableCell>
                                                                                <TableCell className="py-4 align-middle font-mono text-xs font-bold">
                                                                                    {proj.pcode || (
                                                                                        <span className="text-muted-foreground italic">
                                                                                            Pending
                                                                                        </span>
                                                                                    )}
                                                                                </TableCell>
                                                                                <TableCell className="py-4 align-middle">
                                                                                    <div className="space-y-0.5">
                                                                                        <div className="text-sm leading-snug font-semibold">
                                                                                            {
                                                                                                proj.title
                                                                                            }
                                                                                        </div>
                                                                                    </div>
                                                                                </TableCell>
                                                                                <TableCell className="py-4 pr-6 text-right align-middle">
                                                                                    <AwardBadge award={proj.award_level} />
                                                                                </TableCell>
                                                                            </TableRow>
                                                                        );
                                                                    },
                                                                )
                                                            ) : (
                                                                <TableRow>
                                                                    <TableCell
                                                                            colSpan={4}
                                                                        className="py-12 text-center text-sm text-muted-foreground italic"
                                                                    >
                                                                        No approved projects are listed in this category yet.
                                                                    </TableCell>
                                                                </TableRow>
                                                            )}
                                                        </TableBody>
                                                    </Table>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </TabsContent>
                                );
                            })}
                        </Tabs>
                    ) : (
                        <div className="rounded-xl border border-dashed border-border bg-muted/20 p-8 text-center text-muted-foreground">
                            No categories have projects in this active session yet.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
