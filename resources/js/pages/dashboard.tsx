import { Head, Link, router } from '@inertiajs/react';
import {
    FolderHeart,
    Users,
    CheckCircle2,
    AlertTriangle,
    Clock,
    Edit,
    Eye,
    Building2,
    Mail,
    Phone,
    User as UserIcon,
    ArrowRight,
    Sparkles,
    AlertCircle,
    Check,
    Calendar,
    ChevronRight,
    Loader2,
    BellRing,
} from 'lucide-react';
import React from 'react';
import DashboardNotifications from '@/components/dashboard-notifications';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Timeline,
    TimelineContent,
    TimelineDate,
    TimelineHeader,
    TimelineIndicator,
    TimelineItem,
    TimelineSeparator,
    TimelineTitle,
} from '@/components/ui/timeline';
import { cn } from '@/lib/utils';

// Definitions
interface CompetitionSession {
    id: number;
    name: string;
    is_active: boolean;
}

interface Category {
    id: number;
    code: string;
    name: string;
    allow_team: boolean;
}

interface TeamMember {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface Project {
    id: number;
    category_id: number;
    session_id: number;
    pcode: string | null;
    title: string;
    abstract: string;
    poster_url: string | null;
    video_url: string | null;
    institution_type: string;
    status: number;
    supervisor_name: string;
    supervisor_email: string;
    supervisor_phone: string | null;
    admin_comments: string | null;
    created_at: string;
    user?: User;
    category?: Category;
    team_members?: TeamMember[];
}

interface CategoryProgress {
    id: number;
    code: string;
    name: string;
    total_assignments: number;
    completed_scores: number;
    progress_percentage: number;
}

interface PendingProject {
    pcode: string | null;
    title: string;
    category_name: string;
    round_no: number;
}

interface PendingJudge {
    id: number;
    name: string;
    email: string;
    pending_projects: PendingProject[];
}

interface Props {
    role: string | null;
    activeSession: CompetitionSession | null;
    stats?: {
        total_projects: number;
        pending_reviews: number;
        approved_projects: number;
        rejected_projects: number;
    };
    recent_projects?: Project[];
    my_projects?: Project[];
    project?: Project | null;
    categoryProgress?: CategoryProgress[];
    pendingJudges?: PendingJudge[];
}

const getStatusDetails = (status: number) => {
    switch (status) {
        case 1:
            return { label: 'Draft', variant: 'secondary' as const };
        case 2:
            return { label: 'Needs Correction', variant: 'warning' as const };
        case 3:
            return { label: 'Awaiting Review', variant: 'info' as const };
        case 4:
            return { label: 'Approved', variant: 'success' as const };
        case 6:
            return { label: 'Cancelled', variant: 'destructive' as const };
        default:
            return { label: 'Unknown', variant: 'outline' as const };
    }
};

const StatsGrid = ({ stats }: { stats: Props['stats'] }) => {
    if (!stats) {
return null;
}

    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card className="group relative overflow-hidden border border-border/50 bg-card/60 transition-all duration-200 hover:border-primary/20 hover:shadow-md">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium text-muted-foreground">
                        Total Projects
                    </CardTitle>
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors group-hover:bg-primary/20">
                        <FolderHeart className="h-5 w-5" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">
                        {stats.total_projects}
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Registered in system
                    </p>
                </CardContent>
            </Card>

            <Card className="group relative overflow-hidden border border-border/50 bg-card/60 transition-all duration-200 hover:border-amber-500/20 hover:shadow-md">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium text-muted-foreground">
                        Awaiting Review
                    </CardTitle>
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500 transition-colors group-hover:bg-amber-500/20">
                        <Clock className="h-5 w-5 animate-pulse" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-amber-600 dark:text-amber-500">
                        {stats.pending_reviews}
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Requires action by supervisor/admin
                    </p>
                </CardContent>
            </Card>

            <Card className="group relative overflow-hidden border border-border/50 bg-card/60 transition-all duration-200 hover:border-emerald-500/20 hover:shadow-md">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium text-muted-foreground">
                        Approved Projects
                    </CardTitle>
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-500 transition-colors group-hover:bg-emerald-500/20">
                        <CheckCircle2 className="h-5 w-5" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-500">
                        {stats.approved_projects}
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Official project code assigned
                    </p>
                </CardContent>
            </Card>

            <Card className="group relative overflow-hidden border border-border/50 bg-card/60 transition-all duration-200 hover:border-destructive/20 hover:shadow-md">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium text-muted-foreground">
                        Needs Correction
                    </CardTitle>
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-destructive/10 text-destructive transition-colors group-hover:bg-destructive/20">
                        <AlertTriangle className="h-5 w-5" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-destructive">
                        {stats.rejected_projects}
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Drafts returned for modification
                    </p>
                </CardContent>
            </Card>
        </div>
    );
};

const AdminDashboard = ({
    stats,
    recent_projects,
    activeSession,
    categoryProgress = [],
    pendingJudges = [],
}: {
    stats: Props['stats'];
    recent_projects: Project[];
    activeSession: CompetitionSession | null;
    categoryProgress?: CategoryProgress[];
    pendingJudges?: PendingJudge[];
}) => {
    const [isNudging, setIsNudging] = React.useState(false);

    const handleNudgeJudges = () => {
        if (pendingJudges.length === 0) {
return;
}

        if (
            confirm(
                'Are you sure you want to send reminder emails to all judges with pending evaluations?',
            )
        ) {
            router.post(
                '/dashboard/judges/nudge',
                {},
                {
                    onStart: () => setIsNudging(true),
                    onFinish: () => setIsNudging(false),
                },
            );
        }
    };

    return (
        <div className="space-y-6">
            {activeSession && (
                <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/30 p-4 text-sm text-muted-foreground">
                    <Calendar className="h-4 w-4 text-primary" />
                    <span>
                        Active Competition Session:{' '}
                        <strong className="text-foreground">
                            {activeSession.name}
                        </strong>
                    </span>
                </div>
            )}

            <StatsGrid stats={stats} />

            {/* Judging Real-Time Monitor */}
            {activeSession &&
                (categoryProgress.length > 0 || pendingJudges.length > 0) && (
                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Category Progress Card */}
                        <Card className="border border-border/50 bg-card/40 backdrop-blur-sm">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                                    <Sparkles className="h-5 w-5 text-primary" />
                                    Judging Progress by Category
                                </CardTitle>
                                <CardDescription>
                                    Real-time completion rate of assigned
                                    project evaluations.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {categoryProgress.length > 0 ? (
                                    categoryProgress.map((cat) => (
                                        <div key={cat.id} className="space-y-2">
                                            <div className="flex items-center justify-between text-sm">
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        variant="outline"
                                                        className="bg-background font-semibold"
                                                    >
                                                        {cat.code}
                                                    </Badge>
                                                    <span
                                                        className="max-w-[180px] truncate font-medium text-foreground"
                                                        title={cat.name}
                                                    >
                                                        {cat.name}
                                                    </span>
                                                </div>
                                                <span className="text-xs font-medium text-muted-foreground">
                                                    {cat.completed_scores} /{' '}
                                                    {cat.total_assignments}{' '}
                                                    evaluated (
                                                    {cat.progress_percentage}%)
                                                </span>
                                            </div>
                                            <Progress
                                                value={cat.progress_percentage}
                                                className="h-2"
                                                indicatorColor={
                                                    cat.progress_percentage ===
                                                    100
                                                        ? 'bg-emerald-500'
                                                        : cat.progress_percentage >
                                                            50
                                                          ? 'bg-primary'
                                                          : 'bg-amber-500'
                                                }
                                            />
                                        </div>
                                    ))
                                ) : (
                                    <div className="flex min-h-[120px] flex-col items-center justify-center rounded-lg border border-dashed bg-background/20 p-4 text-center">
                                        <p className="text-sm text-muted-foreground">
                                            No categories setup for this
                                            session.
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Pending Judges Card */}
                        <Card className="border border-border/50 bg-card/40 backdrop-blur-sm">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                                <div className="space-y-1">
                                    <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                                        <BellRing className="h-5 w-5 text-amber-500" />
                                        Pending Evaluations
                                    </CardTitle>
                                    <CardDescription>
                                        Judges with outstanding assigned
                                        reviews.
                                    </CardDescription>
                                </div>
                                {pendingJudges.length > 0 && (
                                    <Button
                                        onClick={handleNudgeJudges}
                                        disabled={isNudging}
                                        size="sm"
                                        className="gap-1.5 bg-amber-600 text-white shadow-sm hover:bg-amber-700"
                                    >
                                        {isNudging ? (
                                            <Loader2 className="h-3.5 w-3.5 animate-spin text-white" />
                                        ) : (
                                            <BellRing className="h-3.5 w-3.5" />
                                        )}
                                        Nudge Judges
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent>
                                {pendingJudges.length > 0 ? (
                                    <div className="max-h-[320px] space-y-3 overflow-y-auto pr-1">
                                        {pendingJudges.map((judge) => (
                                            <div
                                                key={judge.id}
                                                className="flex flex-col gap-2 rounded-lg border border-border/40 bg-background/30 p-3 transition-colors hover:bg-muted/10"
                                            >
                                                <div className="flex items-start justify-between">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-semibold text-foreground">
                                                            {judge.name}
                                                        </span>
                                                        <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                                            <Mail className="h-3 w-3" />
                                                            {judge.email}
                                                        </span>
                                                    </div>
                                                    <Badge
                                                        variant="destructive"
                                                        className="border-red-500/10 bg-red-500/10 text-[11px] font-semibold text-red-500 hover:bg-red-500/20"
                                                    >
                                                        {
                                                            judge
                                                                .pending_projects
                                                                .length
                                                        }{' '}
                                                        Pending
                                                    </Badge>
                                                </div>

                                                {/* List pending projects briefly */}
                                                <div className="mt-1 flex flex-wrap gap-1.5">
                                                    {judge.pending_projects.map(
                                                        (proj, idx) => (
                                                            <Badge
                                                                key={idx}
                                                                variant="secondary"
                                                                className="border border-border/50 bg-muted px-1.5 py-0 text-[10px] font-normal text-muted-foreground"
                                                                title={`${proj.title} (Round ${proj.round_no})`}
                                                            >
                                                                {proj.pcode ||
                                                                    'No Code'}{' '}
                                                                (R
                                                                {proj.round_no})
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="flex min-h-[220px] flex-col items-center justify-center rounded-lg border border-dashed bg-background/20 p-6 text-center">
                                        <CheckCircle2 className="mb-2 h-8 w-8 text-emerald-500" />
                                        <p className="text-sm font-semibold text-foreground">
                                            All evaluations complete
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            No pending assignments or
                                            outstanding judge scores!
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}

            <Card className="border border-border/50 bg-card/40 backdrop-blur-sm">
                <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle className="text-lg font-semibold">
                            Recently Submitted Projects
                        </CardTitle>
                        <CardDescription>
                            List of recently submitted projects awaiting
                            approval or review.
                        </CardDescription>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link href="/dashboard/approvals">
                            Manage Approvals
                            <ChevronRight className="ml-1 h-4 w-4" />
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    {recent_projects && recent_projects.length > 0 ? (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Project Code</TableHead>
                                        <TableHead>Project Title</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Registrant</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recent_projects.map((proj) => {
                                        const statusInfo = getStatusDetails(
                                            proj.status,
                                        );

                                        return (
                                            <TableRow
                                                key={proj.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <TableCell className="font-mono text-xs font-semibold">
                                                    {proj.pcode || (
                                                        <span className="text-muted-foreground italic">
                                                            None
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell
                                                    className="max-w-[200px] truncate font-medium"
                                                    title={proj.title}
                                                >
                                                    {proj.title}
                                                </TableCell>
                                                <TableCell>
                                                    {proj.category ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="bg-background"
                                                        >
                                                            {proj.category.code}
                                                        </Badge>
                                                    ) : (
                                                        '-'
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col">
                                                        <span className="text-xs font-medium">
                                                            {proj.user?.name}
                                                        </span>
                                                        <span className="text-[10px] text-muted-foreground">
                                                            {proj.user?.email}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            statusInfo.variant
                                                        }
                                                    >
                                                        {statusInfo.label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="ghost"
                                                        className="h-8 w-8 p-0"
                                                    >
                                                        <Link href="/dashboard/approvals">
                                                            <Eye className="h-4 w-4 text-muted-foreground" />
                                                        </Link>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    ) : (
                        <div className="flex min-h-[150px] flex-col items-center justify-center rounded-lg border border-dashed bg-background/20 p-6 text-center">
                            <FolderHeart className="mb-2 h-8 w-8 text-muted-foreground/50" />
                            <p className="text-sm font-medium text-muted-foreground">
                                No recently submitted projects found.
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
};

const LecturerDashboard = ({
    stats,
    my_projects,
    activeSession,
}: {
    stats: Props['stats'];
    my_projects: Project[];
    activeSession: CompetitionSession | null;
}) => {
    return (
        <div className="space-y-6">
            {activeSession && (
                <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/30 p-4 text-sm text-muted-foreground">
                    <Calendar className="h-4 w-4 text-primary" />
                    <span>
                        Active Competition Session:{' '}
                        <strong className="text-foreground">
                            {activeSession.name}
                        </strong>
                    </span>
                </div>
            )}

            <StatsGrid stats={stats} />

            <Card className="border border-border/50 bg-card/40 backdrop-blur-sm">
                <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle className="text-lg font-semibold">
                            Supervised Projects List
                        </CardTitle>
                        <CardDescription>
                            Student innovation projects where you are assigned
                            as the supervisor.
                        </CardDescription>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link href="/dashboard/approvals">
                            Review Approvals
                            <ChevronRight className="ml-1 h-4 w-4" />
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    {my_projects && my_projects.length > 0 ? (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Project Code</TableHead>
                                        <TableHead>Project Title</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Lead Student</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {my_projects.map((proj) => {
                                        const statusInfo = getStatusDetails(
                                            proj.status,
                                        );

                                        return (
                                            <TableRow
                                                key={proj.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <TableCell className="font-mono text-xs font-semibold">
                                                    {proj.pcode || (
                                                        <span className="text-muted-foreground italic">
                                                            None
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell
                                                    className="max-w-[200px] truncate font-medium"
                                                    title={proj.title}
                                                >
                                                    {proj.title}
                                                </TableCell>
                                                <TableCell>
                                                    {proj.category ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="bg-background"
                                                        >
                                                            {proj.category.code}
                                                        </Badge>
                                                    ) : (
                                                        '-'
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col">
                                                        <span className="text-xs font-medium">
                                                            {proj.user?.name}
                                                        </span>
                                                        <span className="text-[10px] text-muted-foreground">
                                                            {proj.user?.email}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            statusInfo.variant
                                                        }
                                                    >
                                                        {statusInfo.label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="ghost"
                                                        className="h-8 w-8 p-0"
                                                    >
                                                        <Link href="/dashboard/approvals">
                                                            <Eye className="h-4 w-4 text-muted-foreground" />
                                                        </Link>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    ) : (
                        <div className="flex min-h-[150px] flex-col items-center justify-center rounded-lg border border-dashed bg-background/20 p-6 text-center">
                            <Users className="mb-2 h-8 w-8 text-muted-foreground/50" />
                            <p className="text-sm font-medium text-muted-foreground">
                                No projects under your supervision.
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
};

const UserDashboard = ({
    activeSession,
    project,
}: {
    activeSession: CompetitionSession | null;
    project: Project | null;
}) => {
    if (!activeSession) {
        return (
            <Card className="mx-auto max-w-lg border border-border/50 bg-card/40 p-8 text-center backdrop-blur-sm">
                <CardContent className="space-y-4 pt-6">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-muted text-muted-foreground">
                        <AlertCircle className="h-8 w-8" />
                    </div>
                    <div className="space-y-2">
                        <h2 className="text-xl font-bold">
                            No Active Competition Session
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            There is currently no active innovation competition
                            session. Please await official announcements from
                            the administration.
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (!project) {
        return (
            <Card className="relative mx-auto max-w-2xl overflow-hidden border border-border/50 bg-gradient-to-br from-card to-background/50 p-6 backdrop-blur-sm md:p-8">
                {/* Decorative background gradients */}
                <div className="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-primary/10 blur-3xl" />
                <div className="absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-emerald-500/10 blur-3xl" />

                <CardContent className="relative flex flex-col items-center space-y-6 pt-6 text-center">
                    <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary shadow-inner">
                        <Sparkles className="h-8 w-8" />
                    </div>
                    <div className="max-w-md space-y-2">
                        <h2 className="text-2xl font-bold tracking-tight">
                            Join the Innovation Competition!
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            You have not registered your innovation project for
                            competition session{' '}
                            <strong className="text-foreground">
                                {activeSession.name}
                            </strong>
                            . Register today to join!
                        </p>
                    </div>

                    <div className="flex w-full flex-col gap-3 pt-2 sm:w-auto sm:flex-row">
                        <Button
                            asChild
                            size="lg"
                            className="shadow-lg transition-all duration-200 hover:shadow-primary/20"
                        >
                            <Link href="/dashboard/projects">
                                Register Innovation Project
                                <ArrowRight className="ml-2 h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        );
    }

    // Has project: Display detailed status & timeline
    const statusInfo = getStatusDetails(project.status);

    // Timeline calculation
    let activeStep = 1;

    if (project.status === 3) {
        activeStep = 2;
    } else if (project.status === 4) {
        activeStep = 3;
    }

    return (
        <div className="grid gap-6 md:grid-cols-3">
            {/* Column 1 & 2: Project Details */}
            <div className="space-y-6 md:col-span-2">
                <Card className="border border-border/50 bg-card/40 backdrop-blur-sm">
                    <CardHeader>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-1">
                                <CardDescription className="text-xs font-semibold tracking-wider text-primary/80 uppercase">
                                    Registered Project
                                </CardDescription>
                                <CardTitle className="text-xl leading-tight font-bold tracking-tight">
                                    {project.title}
                                </CardTitle>
                            </div>
                            <Badge
                                variant={statusInfo.variant}
                                className="w-fit"
                            >
                                {statusInfo.label}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {/* Abstract */}
                        <div className="space-y-2">
                            <h4 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Project Abstract
                            </h4>
                            <p className="max-h-[200px] overflow-y-auto rounded-lg border border-border/40 bg-background/40 p-3.5 text-sm leading-relaxed text-foreground/80">
                                {project.abstract}
                            </p>
                        </div>

                        {(project.poster_url || project.video_url || project.admin_comments) && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                {project.poster_url && (
                                    <a
                                        href={project.poster_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="rounded-lg border border-border/40 bg-background/20 p-3 text-sm font-medium text-primary transition-colors hover:bg-muted/40"
                                    >
                                        View project poster
                                    </a>
                                )}
                                {project.video_url && (
                                    <a
                                        href={project.video_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="rounded-lg border border-border/40 bg-background/20 p-3 text-sm font-medium text-primary transition-colors hover:bg-muted/40"
                                    >
                                        Watch project video
                                    </a>
                                )}
                                {project.admin_comments && (
                                    <div className="space-y-1 rounded-lg border border-orange-200/60 bg-orange-50/50 p-3 text-sm dark:border-orange-900/50 dark:bg-orange-950/20 sm:col-span-2">
                                        <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            Administrator feedback
                                        </span>
                                        <p>{project.admin_comments}</p>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Badges / Attributes Grid */}
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1 rounded-lg border border-border/40 bg-background/20 p-3.5">
                                <span className="block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    Category & Project Code
                                </span>
                                <div className="flex flex-wrap items-center gap-2 pt-0.5">
                                    {project.category && (
                                        <Badge
                                            variant="outline"
                                            className="bg-background"
                                        >
                                            {project.category.code} -{' '}
                                            {project.category.name}
                                        </Badge>
                                    )}
                                    <Badge
                                        variant="secondary"
                                        className="font-mono text-xs"
                                    >
                                        Project Code:{' '}
                                        {project.pcode || 'Not Yet Generated'}
                                    </Badge>
                                </div>
                            </div>

                            <div className="space-y-1 rounded-lg border border-border/40 bg-background/20 p-3.5">
                                <span className="block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    Institution Type
                                </span>
                                <span className="flex items-center gap-2 pt-0.5 text-sm font-medium">
                                    <Building2 className="h-4 w-4 text-muted-foreground" />
                                    {project.institution_type === 'utem'
                                        ? 'UTeM (Internal)'
                                        : 'Other Institution (External)'}
                                </span>
                            </div>
                        </div>

                        {/* Supervisor details */}
                        <div className="space-y-3 rounded-lg border border-border/40 bg-background/20 p-4">
                            <h4 className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                Supervisor Information
                            </h4>
                            <div className="grid gap-3 text-sm sm:grid-cols-3">
                                <div className="flex items-center gap-2">
                                    <UserIcon className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <span
                                        className="truncate"
                                        title={project.supervisor_name}
                                    >
                                        {project.supervisor_name}
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Mail className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <span
                                        className="truncate"
                                        title={project.supervisor_email}
                                    >
                                        {project.supervisor_email}
                                    </span>
                                </div>
                                {project.supervisor_phone && (
                                    <div className="flex items-center gap-2">
                                        <Phone className="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <span>{project.supervisor_phone}</span>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Team members */}
                        {project.category?.allow_team &&
                        project.team_members &&
                        project.team_members.length > 0 ? (
                            <div className="space-y-3 rounded-lg border border-border/40 bg-background/20 p-4">
                                <h4 className="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                    Team Members ({project.team_members.length})
                                </h4>
                                <div className="divide-y divide-border/30">
                                    {project.team_members.map(
                                        (member, index) => (
                                            <div
                                                key={index}
                                                className="flex flex-col gap-1 py-2.5 text-sm first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <span className="font-semibold text-foreground">
                                                    {member.name}
                                                </span>
                                                <div className="flex items-center gap-4 text-xs text-muted-foreground">
                                                    {member.email && (
                                                        <span className="flex items-center gap-1">
                                                            <Mail className="h-3 w-3" />
                                                            {member.email}
                                                        </span>
                                                    )}
                                                    {member.phone && (
                                                        <span className="flex items-center gap-1">
                                                            <Phone className="h-3 w-3" />
                                                            {member.phone}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>
                        ) : null}
                    </CardContent>

                    {/* Actions Card Footer */}
                    <div className="flex flex-col gap-3 border-t border-border/50 bg-muted/20 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <span className="text-xs text-muted-foreground">
                            Updated on:{' '}
                            {new Date(project.created_at).toLocaleDateString(
                                'en-US',
                                {
                                    day: 'numeric',
                                    month: 'short',
                                    year: 'numeric',
                                },
                            )}
                        </span>
                        <Button
                            asChild
                            variant={
                                project.status === 1 || project.status === 2
                                    ? 'default'
                                    : 'outline'
                            }
                            size="sm"
                            className="w-full sm:w-auto"
                        >
                            <Link href="/dashboard/projects">
                                {project.status === 1 ||
                                project.status === 2 ? (
                                    <>
                                        <Edit className="mr-2 h-4 w-4" />
                                        Update Project Info
                                    </>
                                ) : (
                                    <>
                                        <Eye className="mr-2 h-4 w-4" />
                                        View Registration Details
                                    </>
                                )}
                            </Link>
                        </Button>
                    </div>
                </Card>
            </div>

            {/* Column 3: Timeline & Feedback */}
            <div className="space-y-6">
                {/* Feedback Box if corrections required */}
                {project.status === 2 && (
                    <Alert
                        variant="destructive"
                        className="border-orange-400 bg-orange-50/70 text-orange-900 dark:bg-orange-950/20 dark:text-orange-400"
                    >
                        <AlertTriangle className="h-4 w-4 text-orange-600 dark:text-orange-400" />
                        <AlertTitle className="font-semibold text-orange-800 dark:text-orange-300">
                            Action Required: Project Correction
                        </AlertTitle>
                        <AlertDescription className="mt-2 text-xs leading-relaxed text-orange-700 dark:text-orange-400">
                            The supervisor or administrator has requested
                            corrections to your project registration with the
                            following comments:
                            <div className="mt-2 rounded border border-orange-200 bg-white/70 p-2.5 font-mono text-foreground dark:border-orange-900/50 dark:bg-zinc-900/50">
                                {project.admin_comments ||
                                    'No detailed comments provided.'}
                            </div>
                        </AlertDescription>
                    </Alert>
                )}

                {/* Status card & timeline */}
                <Card className="border border-border/50 bg-card/40 backdrop-blur-sm">
                    <CardHeader className="pb-4">
                        <CardTitle className="text-base font-semibold">
                            Project Review Flow
                        </CardTitle>
                        <CardDescription>
                            Current status of the validation & approval process
                            for the innovation project.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-2 pr-4 pl-7">
                        <Timeline value={activeStep} orientation="vertical">
                            <TimelineItem step={1}>
                                <TimelineHeader>
                                    <TimelineIndicator className="flex items-center justify-center border-primary bg-primary text-primary-foreground">
                                        <Check className="h-3 w-3" />
                                    </TimelineIndicator>
                                    <TimelineTitle className="text-sm font-semibold">
                                        Registration & Draft
                                    </TimelineTitle>
                                    <TimelineDate>Draft Completed</TimelineDate>
                                </TimelineHeader>
                                <TimelineContent className="pb-6 text-[11px] text-muted-foreground">
                                    Initial project info and supervisor details
                                    registered.
                                </TimelineContent>
                                <TimelineSeparator />
                            </TimelineItem>

                            <TimelineItem step={2}>
                                <TimelineHeader>
                                    <TimelineIndicator
                                        className={cn(
                                            'flex items-center justify-center border-2',
                                            project.status >= 3
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-muted-foreground/30 bg-background text-muted-foreground',
                                        )}
                                    >
                                        {project.status >= 3 ? (
                                            <Check className="h-3 w-3" />
                                        ) : (
                                            '2'
                                        )}
                                    </TimelineIndicator>
                                    <TimelineTitle className="text-sm font-semibold">
                                        Submission for Review
                                    </TimelineTitle>
                                    {project.status >= 3 && (
                                        <TimelineDate>
                                            Submitted Successfully
                                        </TimelineDate>
                                    )}
                                </TimelineHeader>
                                <TimelineContent className="pb-6 text-[11px] text-muted-foreground">
                                    {project.status >= 3
                                        ? 'Project has been successfully submitted for approval evaluation.'
                                        : 'Draft not yet submitted. Please complete and submit your project.'}
                                </TimelineContent>
                                <TimelineSeparator />
                            </TimelineItem>

                            <TimelineItem step={3}>
                                <TimelineHeader>
                                    <TimelineIndicator
                                        className={cn(
                                            'flex items-center justify-center border-2',
                                            project.status === 4
                                                ? 'border-emerald-500 bg-emerald-500 text-white'
                                                : project.status === 2
                                                  ? 'border-orange-500 bg-orange-500 text-white'
                                                  : 'border-muted-foreground/30 bg-background text-muted-foreground',
                                        )}
                                    >
                                        {project.status === 4 ? (
                                            <Check className="h-3 w-3" />
                                        ) : project.status === 2 ? (
                                            <AlertTriangle className="h-3 w-3" />
                                        ) : (
                                            '3'
                                        )}
                                    </TimelineIndicator>
                                    <TimelineTitle
                                        className={cn(
                                            'text-sm font-semibold',
                                            project.status === 4 &&
                                                'text-emerald-600 dark:text-emerald-400',
                                            project.status === 2 &&
                                                'text-orange-600 dark:text-orange-400',
                                        )}
                                    >
                                        {project.status === 4
                                            ? 'Approved'
                                            : project.status === 2
                                              ? 'Needs Correction'
                                              : 'Approval Decision'}
                                    </TimelineTitle>
                                </TimelineHeader>
                                <TimelineContent className="text-[11px] text-muted-foreground">
                                    {project.status === 4
                                        ? `Project officially approved with code: ${project.pcode}`
                                        : project.status === 2
                                          ? 'Supervisor requested info correction.'
                                          : 'Awaiting validation decision from supervisor/administrator.'}
                                </TimelineContent>
                            </TimelineItem>
                        </Timeline>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
};

export default function Dashboard({
    role,
    activeSession,
    stats,
    recent_projects = [],
    my_projects = [],
    project = null,
    categoryProgress = [],
    pendingJudges = [],
}: Props) {
    const renderContent = () => {
        switch (role) {
            case 'admin':
                return (
                    <AdminDashboard
                        stats={stats}
                        recent_projects={recent_projects}
                        activeSession={activeSession}
                        categoryProgress={categoryProgress}
                        pendingJudges={pendingJudges}
                    />
                );
            case 'lecturer':
                return (
                    <LecturerDashboard
                        stats={stats}
                        my_projects={my_projects}
                        activeSession={activeSession}
                    />
                );
            default:
                return (
                    <UserDashboard
                        activeSession={activeSession}
                        project={project}
                    />
                );
        }
    };

    const getRoleTitle = () => {
        switch (role) {
            case 'admin':
                return 'Administrator';
            case 'lecturer':
                return 'Lecturer / Supervisor';
            default:
                return 'Participant';
        }
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="space-y-6 pb-8">
                <div className="flex flex-col gap-4 rounded-xl border border-border/60 bg-card/50 p-5 shadow-sm sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1.5">
                        <p className="text-xs font-semibold tracking-[0.18em] text-primary uppercase">
                            INOTEK workspace
                        </p>
                        <h1 className="text-2xl font-bold tracking-tight lg:text-3xl">
                            Dashboard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Welcome back! Logged in as{' '}
                            <strong className="text-foreground">
                                {getRoleTitle()}
                            </strong>
                            .
                        </p>
                    </div>
                    {activeSession && (
                        <Badge
                            variant="outline"
                            className="w-fit gap-1.5 rounded-full px-3 py-1 text-xs"
                        >
                            <span className="size-1.5 animate-pulse rounded-full bg-emerald-500" />
                            {activeSession.name}
                        </Badge>
                    )}
                </div>
                <DashboardNotifications />
                {renderContent()}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
    ],
};
