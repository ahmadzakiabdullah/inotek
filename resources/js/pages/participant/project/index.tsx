import { Head, useForm, router, usePage } from '@inertiajs/react';
import {
    FolderHeart,
    Plus,
    Trash2,
    UploadCloud,
    Video,
    User,
    Mail,
    Phone,
    Users,
    ArrowRight,
    Edit,
    Send,
    CheckCircle,
    Calendar,
    AlertCircle,
    Building2,
    FileText,
    Award,
} from 'lucide-react';
import React, { useState, useMemo } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    CardFooter,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';

interface CompetitionSession {
    id: number;
    name: string;
}

interface Category {
    id: number;
    code: string;
    name: string;
    allow_team: boolean;
}

interface TeamMember {
    name: string;
    email: string | null;
    phone: string | null;
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
    status_label: string;
    supervisor_name: string;
    supervisor_email: string;
    supervisor_phone: string | null;
    admin_comments: string | null;
    team_members?: TeamMember[];
    category?: Category;
    award_level?: string | null;
}

interface Props {
    activeSession: CompetitionSession | null;
    categories: Category[];
    project: Project | null;
}

export default function ProjectIndex({
    activeSession,
    categories,
    project,
}: Props) {
    const { auth } = usePage().props as any;
    const currentUser = auth?.user;

    const [isEditing, setIsEditing] = useState(false);
    const [activeTab, setActiveTab] = useState('details');

    const initialTeamMembers = useMemo(() => {
        if (project && project.team_members) {
            return project.team_members.map((tm) => ({
                name: tm.name,
                email: tm.email || '',
                phone: tm.phone || '',
            }));
        }

        return [] as { name: string; email: string; phone: string }[];
    }, [project]);

    // Form setup for create/edit
    const form = useForm({
        category_id: project ? project.category_id.toString() : '',
        title: project ? project.title : '',
        abstract: project ? project.abstract : '',
        institution_type: project ? project.institution_type : 'utem',
        supervisor_name: project ? project.supervisor_name : '',
        supervisor_email: project ? project.supervisor_email : '',
        supervisor_phone: project ? project.supervisor_phone || '' : '',
        video_url: project ? project.video_url || '' : '',
        poster_url: project ? project.poster_url || '' : '',
        team_members: initialTeamMembers,
    });

    const isTeamAllowed = useMemo(() => {
        if (!form.data.category_id) {
return false;
}

        const cat = categories.find(
            (c) => c.id.toString() === form.data.category_id,
        );

        return cat ? !!cat.allow_team : false;
    }, [form.data.category_id, categories]);

    React.useEffect(() => {
        if (!isTeamAllowed) {
            if (activeTab === 'team') {
                setActiveTab('details');
            }

            if (form.data.team_members && form.data.team_members.length > 0) {
                form.setData('team_members', []);
            }
        }
    }, [isTeamAllowed, activeTab]);

    const handleAddMember = () => {
        form.setData('team_members', [
            ...form.data.team_members,
            { name: '', email: '', phone: '' },
        ]);
    };

    const handleRemoveMember = (idx: number) => {
        const list = [...form.data.team_members];
        list.splice(idx, 1);
        form.setData('team_members', list);
    };

    const handleUpdateMember = (idx: number, field: string, val: string) => {
        const list = [...form.data.team_members];
        list[idx] = { ...list[idx], [field]: val };
        form.setData('team_members', list);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (project) {
            // Update mode
            form.put(`/projects/${project.id}`, {
                onSuccess: () => {
                    setIsEditing(false);
                },
            });
        } else {
            // Create mode
            form.post('/projects', {
                onSuccess: () => {
                    setIsEditing(false);
                },
            });
        }
    };

    const handleFinalSubmit = () => {
        if (!project) {
return;
}

        if (
            confirm(
                'Are you sure you want to submit your project? No further edits can be made after submission.',
            )
        ) {
            router.post(`/projects/${project.id}/submit`);
        }
    };

    const handleDelete = () => {
        if (!project) {
return;
}

        if (
            confirm(
                'Are you absolutely sure you want to delete this project draft? This cannot be undone.',
            )
        ) {
            router.delete(`/projects/${project.id}`);
        }
    };

    const getStatusBadgeVariant = (status: number) => {
        switch (status) {
            case 1: // New
                return 'secondary';
            case 2: // Edit
                return 'warning';
            case 3: // Submitted
                return 'info';
            case 4: // Approved
                return 'success';
            case 6: // Cancelled
                return 'destructive';
            default:
                return 'outline';
        }
    };

    if (!activeSession) {
        return (
            <>
                <Head title="My Project Submission" />
                <div className="flex min-h-[50vh] flex-col items-center justify-center space-y-4 p-6 text-center">
                    <AlertCircle className="h-16 w-16 text-muted-foreground/50" />
                    <h1 className="text-xl font-bold">No Active Session</h1>
                    <p className="max-w-sm text-sm text-muted-foreground">
                        There is currently no active competition session. Please
                        wait until administrators launch the session.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="My Project Submission" />

            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                            <FolderHeart className="h-8 w-8 text-primary" />
                            My Project Submission
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Register and manage your project for{' '}
                            <span className="font-semibold text-foreground">
                                {activeSession.name}
                            </span>
                            .
                        </p>
                    </div>
                </div>

                {/* Show Details View or Edit Form */}
                {project && !isEditing ? (
                    <div className="space-y-6">
                        {/* Status timeline */}
                        <Card className="border border-border/50 bg-card/40 backdrop-blur-sm">
                            <CardHeader className="pb-3">
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                                            Status Overview
                                            <Badge
                                                variant={getStatusBadgeVariant(
                                                    project.status,
                                                )}
                                            >
                                                {project.status_label}
                                            </Badge>
                                        </CardTitle>
                                        <CardDescription>
                                            Track your project registration
                                            lifecycle.
                                        </CardDescription>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {inArray(project.status, [1, 2]) && (
                                            <>
                                                <Button
                                                    onClick={() =>
                                                        setIsEditing(true)
                                                    }
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-9"
                                                >
                                                    <Edit className="mr-2 h-4 w-4" />
                                                    Edit Project
                                                </Button>
                                                <Button
                                                    onClick={handleFinalSubmit}
                                                    size="sm"
                                                    className="h-9"
                                                >
                                                    <Send className="mr-2 h-4 w-4" />
                                                    Submit Project
                                                </Button>
                                                <Button
                                                    onClick={handleDelete}
                                                    variant="destructive"
                                                    size="sm"
                                                    className="h-9"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </>
                                        )}
                                        {project.status === 4 && (
                                            <>
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-9 border-primary/30 text-primary hover:bg-primary/5"
                                                >
                                                    <a
                                                        href={`/projects/${project.id}/certificate/participation`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <FileText className="mr-2 h-4 w-4" />
                                                        Participation Certificate
                                                    </a>
                                                </Button>
                                                {project.award_level && (
                                                    <Button
                                                        asChild
                                                        variant="default"
                                                        size="sm"
                                                        className="h-9 bg-amber-600 hover:bg-amber-700 text-white border-amber-600"
                                                    >
                                                        <a
                                                            href={`/projects/${project.id}/certificate/achievement`}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <Award className="mr-2 h-4 w-4" />
                                                            Achievement Certificate
                                                        </a>
                                                    </Button>
                                                )}
                                            </>
                                        )}
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {/* Timeline display */}
                                <div className="relative flex flex-col items-start justify-between gap-6 py-6 md:flex-row md:items-center md:gap-0">
                                    <div className="absolute top-1/2 right-0 left-4 hidden h-0.5 -translate-y-1/2 bg-border/40 md:left-0 md:block"></div>

                                    {/* Timeline Item 1 */}
                                    <div className="relative z-10 flex items-center gap-3 text-left md:flex-col md:gap-2 md:text-center">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground shadow-md">
                                            1
                                        </div>
                                        <div>
                                            <h4 className="text-sm font-semibold">
                                                Registered
                                            </h4>
                                            <p className="text-xs text-muted-foreground">
                                                Draft created
                                            </p>
                                        </div>
                                    </div>

                                    {/* Timeline Item 2 */}
                                    <div
                                        className={`relative z-10 flex items-center gap-3 text-left md:flex-col md:gap-2 md:text-center ${project.status >= 3 ? 'opacity-100' : 'opacity-40'}`}
                                    >
                                        <div
                                            className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold shadow-md ${project.status >= 3 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'}`}
                                        >
                                            2
                                        </div>
                                        <div>
                                            <h4 className="text-sm font-semibold">
                                                Submitted
                                            </h4>
                                            <p className="text-xs text-muted-foreground">
                                                Pending verification
                                            </p>
                                        </div>
                                    </div>

                                    {/* Timeline Item 3 */}
                                    <div
                                        className={`relative z-10 flex items-center gap-3 text-left md:flex-col md:gap-2 md:text-center ${project.status === 4 ? 'opacity-100' : 'opacity-40'}`}
                                    >
                                        <div
                                            className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold shadow-md ${project.status === 4 ? 'bg-emerald-500 text-white' : 'bg-muted text-muted-foreground'}`}
                                        >
                                            {project.status === 4 ? (
                                                <CheckCircle className="h-4 w-4" />
                                            ) : (
                                                '3'
                                            )}
                                        </div>
                                        <div>
                                            <h4 className="text-sm font-semibold">
                                                Approved
                                            </h4>
                                            <p className="text-xs text-muted-foreground">
                                                Verified by Admin
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {/* Rejection Alerts */}
                                {project.admin_comments && (
                                    <div className="border-warning/20 bg-warning/5 text-warning mt-4 flex gap-3 rounded-lg border p-4">
                                        <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />
                                        <div className="space-y-1 text-sm">
                                            <p className="font-semibold">
                                                Administrator Comments:
                                            </p>
                                            <p className="opacity-90">
                                                {project.admin_comments}
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Project Details Cards */}
                        <div className="grid gap-6 md:grid-cols-3">
                            <Card className="border border-border/50 bg-card/30 backdrop-blur-sm md:col-span-2">
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-xl font-bold">
                                            {project.title}
                                        </CardTitle>
                                        {project.pcode && (
                                            <Badge
                                                variant="outline"
                                                className="border-primary/20 bg-primary/5 font-mono font-bold text-primary"
                                            >
                                                {project.pcode}
                                            </Badge>
                                        )}
                                    </div>
                                    <CardDescription className="mt-1 flex items-center gap-1 text-xs">
                                        <Building2 className="h-3.5 w-3.5" />
                                        {project.institution_type === 'utem'
                                            ? 'UTeM Institution'
                                            : 'External Institution (IPT)'}
                                        {project.category && (
                                            <span className="ml-2 rounded bg-muted px-1.5 py-0.5 font-bold text-muted-foreground">
                                                {project.category.name}
                                            </span>
                                        )}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-1.5">
                                        <Label className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Abstract
                                        </Label>
                                        <p className="rounded-lg border border-border/40 bg-background/30 p-4 text-sm leading-relaxed whitespace-pre-wrap text-foreground">
                                            {project.abstract}
                                        </p>
                                    </div>

                                    {/* Media links */}
                                    <div className="grid gap-4 pt-2 sm:grid-cols-2">
                                        {project.video_url && (
                                            <a
                                                href={project.video_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="flex items-center gap-3 rounded-lg border border-border/40 bg-background/50 p-3 text-sm font-medium transition-colors hover:bg-muted/10"
                                            >
                                                <Video className="h-5 w-5 text-red-500" />
                                                <span className="truncate">
                                                    Demo Video URL
                                                </span>
                                            </a>
                                        )}
                                        {project.poster_url && (
                                            <a
                                                href={project.poster_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="flex items-center gap-3 rounded-lg border border-border/40 bg-background/50 p-3 text-sm font-medium transition-colors hover:bg-muted/10"
                                            >
                                                <FileText className="h-5 w-5 text-blue-500" />
                                                <span className="truncate">
                                                    View Poster File
                                                </span>
                                            </a>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Supervisor & Team sidebar */}
                            <div className="space-y-6">
                                {/* Participant Details Card */}
                                <Card className="border border-border/50 bg-card/30 backdrop-blur-sm">
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                            Registered By
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        <div className="flex items-center gap-2 text-sm">
                                            <User className="h-4 w-4 text-muted-foreground" />
                                            <span className="font-medium">
                                                {currentUser?.name}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <Mail className="h-3.5 w-3.5" />
                                            <span className="truncate">
                                                {currentUser?.email}
                                            </span>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Supervisor Card */}
                                <Card className="border border-border/50 bg-card/30 backdrop-blur-sm">
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                            Project Supervisor
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        <div className="flex items-center gap-2 text-sm">
                                            <User className="h-4 w-4 text-muted-foreground" />
                                            <span className="font-medium">
                                                {project.supervisor_name}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <Mail className="h-3.5 w-3.5" />
                                            <span className="truncate">
                                                {project.supervisor_email}
                                            </span>
                                        </div>
                                        {project.supervisor_phone && (
                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <Phone className="h-3.5 w-3.5" />
                                                <span>
                                                    {project.supervisor_phone}
                                                </span>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>

                                {/* Team Members Card */}
                                {project.category?.allow_team && (
                                    <Card className="border border-border/50 bg-card/30 backdrop-blur-sm">
                                        <CardHeader className="pb-2">
                                            <CardTitle className="flex items-center justify-between text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                                <span>Team Members</span>
                                                <Badge
                                                    variant="outline"
                                                    className="h-5"
                                                >
                                                    {project.team_members
                                                        ?.length ?? 0}
                                                </Badge>
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="p-0">
                                            {project.team_members &&
                                            project.team_members.length > 0 ? (
                                                <div className="divide-y divide-border/30">
                                                    {project.team_members.map(
                                                        (member, idx) => (
                                                            <div
                                                                key={idx}
                                                                className="space-y-1 p-3"
                                                            >
                                                                <div className="text-xs font-semibold">
                                                                    {
                                                                        member.name
                                                                    }
                                                                </div>
                                                                {member.email && (
                                                                    <div className="flex items-center gap-1 text-[10px] text-muted-foreground">
                                                                        <Mail className="h-3 w-3" />{' '}
                                                                        {
                                                                            member.email
                                                                        }
                                                                    </div>
                                                                )}
                                                                {member.phone && (
                                                                    <div className="flex items-center gap-1 text-[10px] text-muted-foreground">
                                                                        <Phone className="h-3 w-3" />{' '}
                                                                        {
                                                                            member.phone
                                                                        }
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            ) : (
                                                <div className="p-4 text-center text-xs text-muted-foreground italic">
                                                    No team members added.
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                )}
                            </div>
                        </div>
                    </div>
                ) : (
                    // Registration / Editing Form View
                    <Card className="border border-border/50 bg-card/40 backdrop-blur-sm">
                        <CardHeader>
                            <CardTitle>
                                {project
                                    ? 'Edit Project Submission'
                                    : 'Register Project'}
                            </CardTitle>
                            <CardDescription>
                                Please complete all tabs before saving your
                                project details.
                            </CardDescription>
                        </CardHeader>
                        <form onSubmit={handleSubmit}>
                            <CardContent className="space-y-6">
                                <Tabs
                                    value={activeTab}
                                    onValueChange={setActiveTab}
                                    className="w-full"
                                >
                                    <TabsList
                                        className={`grid w-full bg-muted/30 ${isTeamAllowed ? 'grid-cols-4' : 'grid-cols-3'}`}
                                    >
                                        <TabsTrigger
                                            value="details"
                                            className="text-xs"
                                        >
                                            Project Details
                                        </TabsTrigger>
                                        <TabsTrigger
                                            value="supervisor"
                                            className="text-xs"
                                        >
                                            Supervisor
                                        </TabsTrigger>
                                        <TabsTrigger
                                            value="media"
                                            className="text-xs"
                                        >
                                            Media Links
                                        </TabsTrigger>
                                        {isTeamAllowed && (
                                            <TabsTrigger
                                                value="team"
                                                className="text-xs"
                                            >
                                                Team Members
                                            </TabsTrigger>
                                        )}
                                    </TabsList>

                                    {/* Tab 1: Project Details */}
                                    <TabsContent
                                        value="details"
                                        className="space-y-4 pt-4"
                                    >
                                        <div className="grid gap-2">
                                            <Label htmlFor="category_id">
                                                Competition Category
                                            </Label>
                                            <Select
                                                value={form.data.category_id}
                                                onValueChange={(val) =>
                                                    form.setData(
                                                        'category_id',
                                                        val,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="bg-background/50">
                                                    <SelectValue placeholder="Select category" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {categories.map((cat) => (
                                                        <SelectItem
                                                            key={cat.id}
                                                            value={cat.id.toString()}
                                                        >
                                                            [{cat.code}]{' '}
                                                            {cat.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    form.errors.category_id
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="institution_type">
                                                Institution Category
                                            </Label>
                                            <Select
                                                value={
                                                    form.data.institution_type
                                                }
                                                onValueChange={(val) =>
                                                    form.setData(
                                                        'institution_type',
                                                        val,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="bg-background/50">
                                                    <SelectValue placeholder="Select origin" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="utem">
                                                        UTeM Participant
                                                    </SelectItem>
                                                    <SelectItem value="ipt">
                                                        External University/IPT
                                                        Participant
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    form.errors.institution_type
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="title">
                                                Project Title
                                            </Label>
                                            <Input
                                                id="title"
                                                value={form.data.title}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'title',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Enter project name..."
                                                className="bg-background/50"
                                                required
                                            />
                                            <InputError
                                                message={form.errors.title}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="abstract">
                                                Abstract
                                            </Label>
                                            <Textarea
                                                id="abstract"
                                                value={form.data.abstract}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'abstract',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Briefly summarize your project goals, design, and impact..."
                                                className="h-32 resize-none bg-background/50"
                                                required
                                            />
                                            <InputError
                                                message={form.errors.abstract}
                                            />
                                        </div>
                                    </TabsContent>

                                    {/* Tab 2: Supervisor Details */}
                                    <TabsContent
                                        value="supervisor"
                                        className="space-y-4 pt-4"
                                    >
                                        <div className="grid gap-2">
                                            <Label htmlFor="supervisor_name">
                                                Supervisor Full Name
                                            </Label>
                                            <Input
                                                id="supervisor_name"
                                                value={
                                                    form.data.supervisor_name
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        'supervisor_name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Enter supervisor's name..."
                                                className="bg-background/50"
                                                required
                                            />
                                            <InputError
                                                message={
                                                    form.errors.supervisor_name
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="supervisor_email">
                                                Supervisor Email
                                            </Label>
                                            <Input
                                                id="supervisor_email"
                                                type="email"
                                                value={
                                                    form.data.supervisor_email
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        'supervisor_email',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="supervisor@example.com"
                                                className="bg-background/50"
                                                required
                                            />
                                            <InputError
                                                message={
                                                    form.errors.supervisor_email
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="supervisor_phone">
                                                Supervisor Phone (Optional)
                                            </Label>
                                            <Input
                                                id="supervisor_phone"
                                                value={
                                                    form.data.supervisor_phone
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        'supervisor_phone',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="e.g. +60123456789"
                                                className="bg-background/50"
                                            />
                                            <InputError
                                                message={
                                                    form.errors.supervisor_phone
                                                }
                                            />
                                        </div>
                                    </TabsContent>

                                    {/* Tab 3: Media & Uploads */}
                                    <TabsContent
                                        value="media"
                                        className="space-y-4 pt-4"
                                    >
                                        <div className="grid gap-2">
                                            <Label htmlFor="video_url">
                                                Demonstration Video Link
                                                (YouTube/Drive)
                                            </Label>
                                            <div className="relative">
                                                <Video className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                                                <Input
                                                    id="video_url"
                                                    value={form.data.video_url}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'video_url',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="https://www.youtube.com/watch?v=..."
                                                    className="bg-background/50 pl-9"
                                                />
                                            </div>
                                            <InputError
                                                message={form.errors.video_url}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="poster_url">
                                                Project Poster Link (Google
                                                Drive/Dropbox/Image URL)
                                            </Label>
                                            <div className="relative">
                                                <FileText className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                                                <Input
                                                    id="poster_url"
                                                    value={form.data.poster_url}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'poster_url',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="https://drive.google.com/file/d/..."
                                                    className="bg-background/50 pl-9"
                                                />
                                            </div>
                                            <InputError
                                                message={form.errors.poster_url}
                                            />
                                        </div>
                                    </TabsContent>

                                    {/* Tab 4: Team Members */}
                                    {isTeamAllowed && (
                                        <TabsContent
                                            value="team"
                                            className="space-y-4 pt-4"
                                        >
                                            <div className="flex items-center justify-between">
                                                <Label className="text-sm font-semibold">
                                                    Team Members Listing
                                                </Label>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={handleAddMember}
                                                    className="h-8"
                                                >
                                                    <Plus className="mr-1 h-3.5 w-3.5" />
                                                    Add Member
                                                </Button>
                                            </div>

                                            <div className="max-h-[300px] space-y-3 overflow-y-auto pr-1">
                                                {form.data.team_members.map(
                                                    (member, idx) => (
                                                        <div
                                                            key={idx}
                                                            className="relative flex flex-col gap-3 rounded-lg border border-border/50 bg-background/30 p-3 sm:flex-row"
                                                        >
                                                            <div className="grid flex-1 gap-1.5">
                                                                <Input
                                                                    value={
                                                                        member.name
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        handleUpdateMember(
                                                                            idx,
                                                                            'name',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="Member Name"
                                                                    className="h-9 bg-background/50"
                                                                    required
                                                                />
                                                            </div>
                                                            <div className="grid flex-1 gap-1.5">
                                                                <Input
                                                                    type="email"
                                                                    value={
                                                                        member.email
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        handleUpdateMember(
                                                                            idx,
                                                                            'email',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="Email Address"
                                                                    className="h-9 bg-background/50"
                                                                />
                                                            </div>
                                                            <div className="grid flex-1 gap-1.5">
                                                                <Input
                                                                    value={
                                                                        member.phone
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        handleUpdateMember(
                                                                            idx,
                                                                            'phone',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="Phone Number"
                                                                    className="h-9 bg-background/50"
                                                                />
                                                            </div>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    handleRemoveMember(
                                                                        idx,
                                                                    )
                                                                }
                                                                className="h-9 w-9 self-end text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    ),
                                                )}
                                                {form.data.team_members
                                                    .length === 0 && (
                                                    <p className="py-4 text-center text-xs text-muted-foreground italic">
                                                        No team members added.
                                                        Group submissions
                                                        require adding members
                                                        here.
                                                    </p>
                                                )}
                                            </div>
                                        </TabsContent>
                                    )}
                                </Tabs>
                            </CardContent>

                            <CardFooter className="flex justify-between border-t border-border/30 pt-4">
                                <div className="flex items-center gap-1 text-[10px] text-muted-foreground">
                                    <Calendar className="h-3.5 w-3.5" />
                                    Active Session: {activeSession.name}
                                </div>
                                <div className="flex gap-2">
                                    {project && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setIsEditing(false)}
                                        >
                                            Cancel
                                        </Button>
                                    )}
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {form.processing
                                            ? 'Saving...'
                                            : 'Save Draft'}
                                    </Button>
                                </div>
                            </CardFooter>
                        </form>
                    </Card>
                )}
            </div>
        </>
    );
}

// In-array helper
function inArray(needle: any, haystack: any[]) {
    return haystack.indexOf(needle) > -1;
}

ProjectIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'My Project',
            href: '/projects',
        },
    ],
};
