import React, { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Trophy, Award, Sparkles, AlertCircle, RefreshCw, BarChart3, Medal } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';

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

export default function LeaderboardIndex({ activeSession, categories, leaderboardData }: Props) {
    const [localLeaderboardData, setLocalLeaderboardData] = useState(leaderboardData);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [activeCategoryTab, setActiveCategoryTab] = useState(
        categories.length > 0 ? categories[0].id.toString() : ''
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
                <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-background to-muted/20 p-6 text-center space-y-4">
                    <AlertCircle className="h-16 w-16 text-muted-foreground/40" />
                    <h1 className="text-2xl font-bold tracking-tight">No Active Session</h1>
                    <p className="max-w-sm text-sm text-muted-foreground">
                        The live scoring leaderboard is currently unavailable because there is no active competition session.
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
        if (!award) return null;
        const normalized = award.toLowerCase();
        if (normalized === 'gold') {
            return (
                <Badge className="bg-yellow-500 hover:bg-yellow-600 text-slate-900 border-none font-bold uppercase tracking-wider text-[10px] gap-1 px-2 py-0.5">
                    <Medal className="h-3 w-3" /> Gold
                </Badge>
            );
        }
        if (normalized === 'silver') {
            return (
                <Badge className="bg-slate-300 hover:bg-slate-400 text-slate-900 border-none font-bold uppercase tracking-wider text-[10px] gap-1 px-2 py-0.5">
                    <Medal className="h-3 w-3" /> Silver
                </Badge>
            );
        }
        if (normalized === 'bronze') {
            return (
                <Badge className="bg-amber-600 hover:bg-amber-700 text-white border-none font-bold uppercase tracking-wider text-[10px] gap-1 px-2 py-0.5">
                    <Medal className="h-3 w-3" /> Bronze
                </Badge>
            );
        }
        return (
            <Badge variant="outline" className="border-primary/30 text-primary font-bold uppercase tracking-wider text-[10px]">
                {award}
            </Badge>
        );
    };

    return (
        <>
            <Head title="Live Scoring Leaderboard" />

            <div className="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-slate-100 p-6 md:p-12">
                <div className="mx-auto max-w-6xl space-y-8">
                    {/* Header Banner */}
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-800 pb-6">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <span className="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                                <span className="text-xs font-semibold text-emerald-500 uppercase tracking-widest">
                                    Live Standings
                                </span>
                            </div>
                            <h1 className="flex items-center gap-2 text-3xl font-extrabold tracking-tight lg:text-4xl text-white">
                                <Trophy className="h-8 w-8 text-yellow-500" />
                                INOTEK Leaderboard
                            </h1>
                            <p className="text-sm text-slate-400">
                                Real-time competition standings for{' '}
                                <span className="font-semibold text-slate-200">{activeSession.name}</span>
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            <Button
                                onClick={fetchRankings}
                                variant="outline"
                                size="sm"
                                disabled={isRefreshing}
                                className="h-9 border-slate-800 bg-slate-900/50 text-slate-200 hover:bg-slate-800 hover:text-white"
                            >
                                <RefreshCw className={`mr-2 h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                                Refresh
                            </Button>
                            <Button asChild size="sm" variant="default" className="h-9 bg-primary text-primary-foreground">
                                <Link href="/dashboard">Portal Login</Link>
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
                                <TabsList className="bg-slate-900/60 border border-slate-800 p-1 flex w-max min-w-full">
                                    {categories.map((cat) => (
                                        <TabsTrigger
                                            key={cat.id}
                                            value={cat.id.toString()}
                                            className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground text-xs md:text-sm font-semibold rounded px-4 py-2 text-slate-400"
                                        >
                                            [{cat.code}] {cat.name}
                                        </TabsTrigger>
                                    ))}
                                </TabsList>
                            </div>

                            {categories.map((cat) => {
                                const projects = localLeaderboardData[cat.id] || [];

                                return (
                                    <TabsContent key={cat.id} value={cat.id.toString()} className="space-y-4">
                                        <Card className="border border-slate-800 bg-slate-900/40 backdrop-blur-md overflow-hidden shadow-2xl">
                                            <CardHeader className="border-b border-slate-800/80 bg-slate-950/20 p-6">
                                                <div className="flex items-center justify-between">
                                                    <div>
                                                        <CardTitle className="text-xl font-bold text-white flex items-center gap-2">
                                                            <BarChart3 className="h-5 w-5 text-primary" />
                                                            Category Standings
                                                        </CardTitle>
                                                        <CardDescription className="text-slate-400 mt-1">
                                                            Projects registered under {cat.name} sorted by evaluation average.
                                                        </CardDescription>
                                                    </div>
                                                    <Badge variant="secondary" className="bg-slate-800 text-slate-300 border-none font-mono">
                                                        {projects.length} projects
                                                    </Badge>
                                                </div>
                                            </CardHeader>
                                            <CardContent className="p-0">
                                                <div className="overflow-x-auto">
                                                    <Table>
                                                        <TableHeader className="bg-slate-950/40">
                                                            <TableRow className="border-b border-slate-800 hover:bg-transparent">
                                                                <TableHead className="w-[80px] py-4 pl-6 text-center font-bold text-slate-300">Rank</TableHead>
                                                                <TableHead className="w-[120px] font-bold text-slate-300">Code</TableHead>
                                                                <TableHead className="font-bold text-slate-300">Project Details</TableHead>
                                                                <TableHead className="w-[120px] text-center font-bold text-slate-300">R1 Avg</TableHead>
                                                                <TableHead className="w-[120px] text-center font-bold text-slate-300">R2 Avg</TableHead>
                                                                <TableHead className="w-[150px] text-center font-bold text-slate-300">Final Score</TableHead>
                                                                <TableHead className="w-[150px] pr-6 text-right font-bold text-slate-300">Award</TableHead>
                                                            </TableRow>
                                                        </TableHeader>
                                                        <TableBody>
                                                            {projects.length > 0 ? (
                                                                projects.map((proj, idx) => {
                                                                    const rank = idx + 1;
                                                                    const isTop3 = rank <= 3;

                                                                    return (
                                                                        <TableRow
                                                                            key={proj.id}
                                                                            className={`border-b border-slate-800/60 transition-colors hover:bg-slate-800/10 ${rank === 1 ? 'bg-yellow-500/[0.02]' : ''}`}
                                                                        >
                                                                            <TableCell className="py-4 pl-6 text-center align-middle font-black">
                                                                                {rank === 1 ? (
                                                                                    <span className="flex items-center justify-center mx-auto h-7 w-7 rounded-full bg-yellow-500 text-slate-950 shadow-lg shadow-yellow-500/10" title="First Place">
                                                                                        1
                                                                                    </span>
                                                                                ) : rank === 2 ? (
                                                                                    <span className="flex items-center justify-center mx-auto h-7 w-7 rounded-full bg-slate-300 text-slate-950 shadow-lg shadow-slate-300/10" title="Second Place">
                                                                                        2
                                                                                    </span>
                                                                                ) : rank === 3 ? (
                                                                                    <span className="flex items-center justify-center mx-auto h-7 w-7 rounded-full bg-amber-600 text-white shadow-lg shadow-amber-600/10" title="Third Place">
                                                                                        3
                                                                                    </span>
                                                                                ) : (
                                                                                    <span className="text-slate-400 font-mono text-sm">{rank}</span>
                                                                                )}
                                                                            </TableCell>
                                                                            <TableCell className="py-4 align-middle font-mono text-xs font-bold text-slate-300">
                                                                                {proj.pcode || (
                                                                                    <span className="text-slate-600 italic">Pending</span>
                                                                                )}
                                                                            </TableCell>
                                                                            <TableCell className="py-4 align-middle">
                                                                                <div className="space-y-0.5">
                                                                                    <div className="text-sm font-semibold text-white leading-snug">
                                                                                        {proj.title}
                                                                                    </div>
                                                                                    <div className="text-[10px] text-slate-500 flex items-center gap-1.5">
                                                                                        <span>Registered by: {proj.username}</span>
                                                                                    </div>
                                                                                </div>
                                                                            </TableCell>
                                                                            <TableCell className="py-4 text-center align-middle font-mono text-xs text-slate-400">
                                                                                {proj.avg_r1 > 0 ? (
                                                                                    <span>{proj.avg_r1}% <span className="text-[9px] opacity-60">({proj.judges_r1} J)</span></span>
                                                                                ) : (
                                                                                    <span className="text-slate-700 italic">-</span>
                                                                                )}
                                                                            </TableCell>
                                                                            <TableCell className="py-4 text-center align-middle font-mono text-xs text-slate-400">
                                                                                {proj.avg_r2 > 0 ? (
                                                                                    <span>{proj.avg_r2}% <span className="text-[9px] opacity-60">({proj.judges_r2} J)</span></span>
                                                                                ) : (
                                                                                    <span className="text-slate-700 italic">-</span>
                                                                                )}
                                                                            </TableCell>
                                                                            <TableCell className="py-4 text-center align-middle">
                                                                                <Badge
                                                                                    variant="outline"
                                                                                    className={`font-mono font-bold text-xs py-0.5 px-2.5 rounded border border-slate-700 bg-slate-900/80 ${proj.final_score > 0 ? 'text-primary' : 'text-slate-500'}`}
                                                                                >
                                                                                    {proj.final_score > 0 ? `${proj.final_score}%` : 'No scores'}
                                                                                </Badge>
                                                                            </TableCell>
                                                                            <TableCell className="py-4 pr-6 text-right align-middle">
                                                                                {getAwardBadge(proj.award_level)}
                                                                            </TableCell>
                                                                        </TableRow>
                                                                    );
                                                                })
                                                            ) : (
                                                                <TableRow>
                                                                    <TableCell colSpan={7} className="py-12 text-center text-sm text-slate-500 italic">
                                                                        No projects have been approved or scored in this category yet.
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
                        <div className="rounded-xl border border-slate-800 bg-slate-900/20 p-8 text-center text-slate-400">
                            No competition categories defined for this session.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
