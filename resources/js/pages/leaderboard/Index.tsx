import { Head, Link, usePage } from '@inertiajs/react';
import {
    Trophy,
    Award,
    Sparkles,
    AlertCircle,
    RefreshCw,
    BarChart3,
    Medal,
} from 'lucide-react';
import React, { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    username: string;
    award_level: string | null;
    avg_r1: number;
    judges_r1: number;
    avg_r2: number;
    judges_r2: number;
    final_score: number;
}

interface Props {
    activeSession: {
        id: number;
        name: string;
    } | null;
    categories: Category[];
    leaderboardData: Record<number, LeaderboardProject[]>;
}

export default function LeaderboardIndex({
    activeSession,
    categories,
    leaderboardData,
}: Props) {
    const { component } = usePage();
    const isAppLayout = component === 'leaderboard/App';
    const [localLeaderboardData, setLocalLeaderboardData] =
        useState(leaderboardData);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [activeCategoryTab, setActiveCategoryTab] = useState(
        categories.length > 0 ? categories[0].id.toString() : '',
    );

    // Fetch updated score rankings
    const fetchRankings = () => {
        setIsRefreshing(true);
        fetch('/leaderboard?api=true')
            .then((res) => res.json())
            .then((data) => {
                setLocalLeaderboardData(data.leaderboardData);
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

    // Helper to get award badge variant/class
    const getAwardBadge = (award: string | null) => {
        if (!award) {
return null;
}

        const normalized = award.toLowerCase();

        if (normalized === 'gold') {
            return (
                <Badge className="gap-1 border-none bg-yellow-500 px-2 py-0.5 text-[10px] font-bold tracking-wider text-slate-900 uppercase hover:bg-yellow-600">
                    <Medal className="h-3 w-3" /> Gold
                </Badge>
            );
        }

        if (normalized === 'silver') {
            return (
                <Badge className="gap-1 border-none bg-slate-300 px-2 py-0.5 text-[10px] font-bold tracking-wider text-slate-900 uppercase hover:bg-slate-400">
                    <Medal className="h-3 w-3" /> Silver
                </Badge>
            );
        }

        if (normalized === 'bronze') {
            return (
                <Badge className="gap-1 border-none bg-amber-600 px-2 py-0.5 text-[10px] font-bold tracking-wider text-white uppercase hover:bg-amber-700">
                    <Medal className="h-3 w-3" /> Bronze
                </Badge>
            );
        }

        return (
            <Badge
                variant="outline"
                className="border-primary/30 text-[10px] font-bold tracking-wider text-primary uppercase"
            >
                {award}
            </Badge>
        );
    };

    return (
        <>
            <Head title="Live Scoring Leaderboard" />

            <div
                className={
                    isAppLayout
                        ? 'space-y-6'
                        : 'min-h-screen bg-background p-6 text-foreground md:p-12'
                }
            >
                <div
                    className={
                    isAppLayout
                        ? 'w-full space-y-6'
                            : 'mx-auto max-w-6xl space-y-8'
                    }
                >
                    {/* Header Banner */}
                    <div className="flex flex-col gap-4 rounded-xl border border-border/60 bg-card/60 p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
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

                    {categories.length > 0 ? (
                        <Tabs
                            value={activeCategoryTab}
                            onValueChange={setActiveCategoryTab}
                            className="w-full space-y-6"
                        >
                            {/* Scrollable Tabs row */}
                            <div className="overflow-x-auto pb-1">
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
                                                            Projects registered
                                                            under {cat.name}{' '}
                                                            sorted by evaluation
                                                            average.
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
                                                                <TableHead className="w-[120px] text-center font-bold">
                                                                    R1 Avg
                                                                </TableHead>
                                                                <TableHead className="w-[120px] text-center font-bold">
                                                                    R2 Avg
                                                                </TableHead>
                                                                <TableHead className="w-[150px] text-center font-bold">
                                                                    Final Score
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
                                                                                        <div className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                                                                            <span>
                                                                                                Registered
                                                                                                by:{' '}
                                                                                                {
                                                                                                    proj.username
                                                                                                }
                                                                                            </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </TableCell>
                                                                                <TableCell className="py-4 text-center align-middle font-mono text-xs text-muted-foreground">
                                                                                    {proj.avg_r1 >
                                                                                    0 ? (
                                                                                        <span>
                                                                                            {
                                                                                                proj.avg_r1
                                                                                            }
                                                                                            %{' '}
                                                                                            <span className="text-[9px] opacity-60">
                                                                                                (
                                                                                                {
                                                                                                    proj.judges_r1
                                                                                                }{' '}
                                                                                                J)
                                                                                            </span>
                                                                                        </span>
                                                                                    ) : (
                                                                                        <span className="text-slate-700 italic">
                                                                                            -
                                                                                        </span>
                                                                                    )}
                                                                                </TableCell>
                                                                                <TableCell className="py-4 text-center align-middle font-mono text-xs text-muted-foreground">
                                                                                    {proj.avg_r2 >
                                                                                    0 ? (
                                                                                        <span>
                                                                                            {
                                                                                                proj.avg_r2
                                                                                            }
                                                                                            %{' '}
                                                                                            <span className="text-[9px] opacity-60">
                                                                                                (
                                                                                                {
                                                                                                    proj.judges_r2
                                                                                                }{' '}
                                                                                                J)
                                                                                            </span>
                                                                                        </span>
                                                                                    ) : (
                                                                                        <span className="text-slate-700 italic">
                                                                                            -
                                                                                        </span>
                                                                                    )}
                                                                                </TableCell>
                                                                                <TableCell className="py-4 text-center align-middle">
                                                                                    <Badge
                                                                                        variant="outline"
                                                                                        className={`rounded border-border bg-muted/60 px-2.5 py-0.5 font-mono text-xs font-bold ${proj.final_score > 0 ? 'text-primary' : 'text-muted-foreground'}`}
                                                                                    >
                                                                                        {proj.final_score >
                                                                                        0
                                                                                            ? `${proj.final_score}%`
                                                                                            : 'No scores'}
                                                                                    </Badge>
                                                                                </TableCell>
                                                                                <TableCell className="py-4 pr-6 text-right align-middle">
                                                                                    {getAwardBadge(
                                                                                        proj.award_level,
                                                                                    )}
                                                                                </TableCell>
                                                                            </TableRow>
                                                                        );
                                                                    },
                                                                )
                                                            ) : (
                                                                <TableRow>
                                                                    <TableCell
                                                                        colSpan={
                                                                            7
                                                                        }
                                                                        className="py-12 text-center text-sm text-muted-foreground italic"
                                                                    >
                                                                        No
                                                                        projects
                                                                        have
                                                                        been
                                                                        approved
                                                                        or
                                                                        scored
                                                                        in this
                                                                        category
                                                                        yet.
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
                            No competition categories defined for this session.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
