import { Head, useForm, router, usePage } from '@inertiajs/react';
import {
    CheckCheck,
    XOctagon,
    Edit3,
    Search,
    ChevronDown,
    ChevronUp,
    FileText,
    Video,
    User,
    Mail,
    Phone,
    Layers,
    FileCheck,
    ExternalLink,
    AlertCircle,
    Plus,
    Trash2,
    UserPlus,
} from 'lucide-react';
import React, { useState, useMemo, useEffect } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { Textarea } from '@/components/ui/textarea';

interface TeamMember {
    name: string;
    email: string | null;
    phone: string | null;
}

interface CompetitionSession {
    id: number;
    name: string;
    is_active: boolean;
}

interface Category {
    id: number;
    code: string;
    name: string;
    session_id?: number;
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
    user?: {
        name: string;
        email: string;
    };
    category?: Category;
    session?: CompetitionSession;
}

interface Student {
    id: number;
    name: string;
    email: string;
}

interface Props {
    projects: Project[];
    sessions: CompetitionSession[];
    categories: Category[];
    students: Student[];
}

export default function ApprovalsIndex({ projects, sessions, categories, students }: Props) {
    const { auth } = usePage().props as any;
    const userRole = auth?.user?.role;

    const [searchQuery, setSearchQuery] = useState('');
    const [selectedSessionFilter, setSelectedSessionFilter] = useState('all');
    const [selectedStatusFilter, setSelectedStatusFilter] = useState('all');

    const [selectedProjectIds, setSelectedProjectIds] = useState<number[]>([]);
    const [expandedRowId, setExpandedRowId] = useState<number | null>(null);

    const [rejectProject, setRejectProject] = useState<Project | null>(null);
    const [editCodeProject, setEditCodeProject] = useState<Project | null>(
        null,
    );

    // Form for reject
    const rejectForm = useForm({
        admin_comments: '',
    });

    // Form for edit code
    const editCodeForm = useForm({
        pcode: '',
    });

    const [isRegisterOpen, setIsRegisterOpen] = useState(false);
    const [studentSearch, setStudentSearch] = useState('');
    const [isStudentDropdownOpen, setIsStudentDropdownOpen] = useState(false);

    // Form for register
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        user_id: '',
        category_id: '',
        title: '',
        abstract: '',
        institution_type: 'utem',
        supervisor_name: '',
        supervisor_email: '',
        supervisor_phone: '',
        video_url: '',
        poster_url: '',
        team_members: [] as TeamMember[],
    });

    const activeSession = useMemo(() => {
        return sessions.find((s) => s.is_active);
    }, [sessions]);

    const activeCategories = useMemo(() => {
        if (!activeSession) {
return [];
}

        return categories.filter((c) => c.session_id === activeSession.id);
    }, [categories, activeSession]);

    const filteredStudents = useMemo(() => {
        const query = studentSearch.toLowerCase();

        if (!query) {
return students;
}

        return students.filter((s) =>
            s.name.toLowerCase().includes(query) ||
            s.email.toLowerCase().includes(query)
        );
    }, [students, studentSearch]);

    const selectedStudent = useMemo(() => {
        return students.find((s) => s.id.toString() === data.user_id);
    }, [students, data.user_id]);

    useEffect(() => {
        const handleOutsideClick = (e: MouseEvent) => {
            const target = e.target as HTMLElement;

            if (!target.closest('.student-search-container')) {
                setIsStudentDropdownOpen(false);
            }
        };
        document.addEventListener('click', handleOutsideClick);

        return () => document.removeEventListener('click', handleOutsideClick);
    }, []);

    const addTeamMember = () => {
        setData('team_members', [
            ...data.team_members,
            { name: '', email: '', phone: '' },
        ]);
    };

    const removeTeamMember = (index: number) => {
        const updated = [...data.team_members];
        updated.splice(index, 1);
        setData('team_members', updated);
    };

    const updateTeamMember = (index: number, field: keyof TeamMember, value: string) => {
        const updated = [...data.team_members];
        updated[index] = { ...updated[index], [field]: value };
        setData('team_members', updated);
    };

    const handleCloseRegister = () => {
        setIsRegisterOpen(false);
        reset();
        clearErrors();
        setStudentSearch('');
    };

    const handleRegisterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/dashboard/approvals/store', {
            onSuccess: () => {
                handleCloseRegister();
            },
        });
    };

    // Filter projects based on query and filters
    const filteredProjects = useMemo(() => {
        return projects.filter((project) => {
            const matchesSearch =
                project.title
                    .toLowerCase()
                    .includes(searchQuery.toLowerCase()) ||
                (project.pcode &&
                    project.pcode
                        .toLowerCase()
                        .includes(searchQuery.toLowerCase())) ||
                (project.user &&
                    project.user.name
                        .toLowerCase()
                        .includes(searchQuery.toLowerCase()));

            const matchesSession =
                selectedSessionFilter === 'all' ||
                project.session_id.toString() === selectedSessionFilter;

            const matchesStatus =
                selectedStatusFilter === 'all' ||
                project.status.toString() === selectedStatusFilter;

            return matchesSearch && matchesSession && matchesStatus;
        });
    }, [projects, searchQuery, selectedSessionFilter, selectedStatusFilter]);

    // Stats
    const stats = useMemo(() => {
        const total = projects.length;
        const pending = projects.filter((p) => p.status === 3).length; // Submitted status
        const approved = projects.filter((p) => p.status === 4).length; // Approved status
        const rejected = projects.filter((p) => p.status === 2).length; // Edit mode/rejected status

        return { total, pending, approved, rejected };
    }, [projects]);

    const handleSelectAll = (checked: boolean) => {
        if (checked) {
            // Only select pending/submitted projects for bulk actions
            const pendingIds = filteredProjects
                .filter((p) => p.status === 3)
                .map((p) => p.id);
            setSelectedProjectIds(pendingIds);
        } else {
            setSelectedProjectIds([]);
        }
    };

    const handleSelectRow = (checked: boolean, id: number) => {
        if (checked) {
            setSelectedProjectIds([...selectedProjectIds, id]);
        } else {
            setSelectedProjectIds(selectedProjectIds.filter((x) => x !== id));
        }
    };

    const handleBulkApprove = () => {
        if (selectedProjectIds.length === 0) {
return;
}

        if (
            confirm(
                `Are you sure you want to approve ${selectedProjectIds.length} selected projects?`,
            )
        ) {
            router.post(
                '/dashboard/approvals/approve',
                {
                    project_ids: selectedProjectIds,
                },
                {
                    onSuccess: () => setSelectedProjectIds([]),
                },
            );
        }
    };

    const handleBulkCancel = () => {
        if (selectedProjectIds.length === 0) {
return;
}

        if (
            confirm(
                `Are you sure you want to cancel/reject ${selectedProjectIds.length} selected projects?`,
            )
        ) {
            router.post(
                '/dashboard/approvals/cancel',
                {
                    project_ids: selectedProjectIds,
                },
                {
                    onSuccess: () => setSelectedProjectIds([]),
                },
            );
        }
    };

    const handleOpenReject = (project: Project) => {
        setRejectProject(project);
        rejectForm.setData('admin_comments', '');
        rejectForm.clearErrors();
    };

    const handleRejectSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!rejectProject) {
return;
}

        rejectForm.post(`/dashboard/approvals/${rejectProject.id}/reject`, {
            onSuccess: () => {
                setRejectProject(null);
                rejectForm.reset();
            },
        });
    };

    const handleOpenEditCode = (project: Project) => {
        setEditCodeProject(project);
        editCodeForm.setData('pcode', project.pcode || '');
        editCodeForm.clearErrors();
    };

    const handleEditCodeSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!editCodeProject) {
return;
}

        editCodeForm.put(`/dashboard/approvals/${editCodeProject.id}/code`, {
            onSuccess: () => {
                setEditCodeProject(null);
                editCodeForm.reset();
            },
        });
    };

    const toggleRow = (id: number) => {
        setExpandedRowId(expandedRowId === id ? null : id);
    };

    const getStatusBadgeVariant = (status: number) => {
        switch (status) {
            case 1: // New
                return 'secondary';
            case 2: // Edit (Rejected/Requires changes)
                return 'warning';
            case 3: // Submitted (Pending check)
                return 'info';
            case 4: // Approved
                return 'success';
            case 6: // Cancelled
                return 'destructive';
            default:
                return 'outline';
        }
    };

    return (
        <>
            <Head title="Project Approvals" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                            <FileCheck className="h-8 w-8 text-primary" />
                            Project Approvals
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Review student/lecturer submissions, bulk approve or
                            cancel, and manage project codes.
                        </p>
                    </div>
                    {userRole === 'admin' && (
                        <Button
                            onClick={() => setIsRegisterOpen(true)}
                            className="gap-2 text-white bg-primary hover:bg-primary/95 shadow-md hover:shadow-lg transition-all"
                        >
                            <Plus className="h-4 w-4" />
                            Register Project
                        </Button>
                    )}
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Total Projects
                            </CardTitle>
                            <Layers className="h-4 w-4 text-primary" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.total}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Registrations in database
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Pending Review
                            </CardTitle>
                            <Badge
                                variant="secondary"
                                className="h-5 animate-pulse border-blue-500/20 bg-blue-500/10 text-blue-500"
                            >
                                Pending
                            </Badge>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-500">
                                {stats.pending}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Awaiting verification
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Approved Projects
                            </CardTitle>
                            <FileCheck className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-emerald-500">
                                {stats.approved}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Active participants in sessions
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Requires Changes
                            </CardTitle>
                            <AlertCircle className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-amber-500">
                                {stats.rejected}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Reverted back to participants
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters Board */}
                <Card className="border border-border/50 bg-card/25 backdrop-blur-sm">
                    <CardContent className="grid gap-4 p-4 sm:grid-cols-4">
                        <div className="relative">
                            <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Search title, code, owner..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="h-9 w-full border-border/50 bg-background/50 pl-9"
                            />
                        </div>

                        <div>
                            <Select
                                value={selectedSessionFilter}
                                onValueChange={setSelectedSessionFilter}
                            >
                                <SelectTrigger className="h-9 bg-background/50">
                                    <SelectValue placeholder="All Sessions" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Sessions
                                    </SelectItem>
                                    {sessions.map((s) => (
                                        <SelectItem
                                            key={s.id}
                                            value={s.id.toString()}
                                        >
                                            {s.name}{' '}
                                            {s.is_active ? '(Active)' : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Select
                                value={selectedStatusFilter}
                                onValueChange={setSelectedStatusFilter}
                            >
                                <SelectTrigger className="h-9 bg-background/50">
                                    <SelectValue placeholder="All Statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Statuses
                                    </SelectItem>
                                    <SelectItem value="1">
                                        Draft (New)
                                    </SelectItem>
                                    <SelectItem value="2">
                                        Requires Changes
                                    </SelectItem>
                                    <SelectItem value="3">
                                        Submitted (Pending)
                                    </SelectItem>
                                    <SelectItem value="4">Approved</SelectItem>
                                    <SelectItem value="6">Cancelled</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Bulk Action Controls */}
                        <div className="flex justify-end gap-2 sm:col-span-1">
                            <Button
                                onClick={handleBulkApprove}
                                disabled={selectedProjectIds.length === 0}
                                className="h-9 flex-1 gap-1 bg-emerald-600 px-3 text-xs text-white hover:bg-emerald-700"
                            >
                                <CheckCheck className="h-3.5 w-3.5" />
                                Approve ({selectedProjectIds.length})
                            </Button>
                            <Button
                                onClick={handleBulkCancel}
                                disabled={selectedProjectIds.length === 0}
                                variant="destructive"
                                className="h-9 flex-1 gap-1 px-3 text-xs"
                            >
                                <XOctagon className="h-3.5 w-3.5" />
                                Cancel ({selectedProjectIds.length})
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Main Table Card */}
                <Card className="overflow-hidden border border-border/50 bg-card/40 backdrop-blur-sm">
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="border-b border-border/40 bg-muted/20 hover:bg-transparent">
                                        <TableHead className="w-[50px] py-3 pl-4 text-center">
                                            <Checkbox
                                                checked={
                                                    filteredProjects.filter(
                                                        (p) => p.status === 3,
                                                    ).length > 0 &&
                                                    selectedProjectIds.length ===
                                                        filteredProjects.filter(
                                                            (p) =>
                                                                p.status === 3,
                                                        ).length
                                                }
                                                onCheckedChange={
                                                    handleSelectAll
                                                }
                                            />
                                        </TableHead>
                                        <TableHead className="w-[120px] font-semibold">
                                            Project Code
                                        </TableHead>
                                        <TableHead className="font-semibold">
                                            Project Title
                                        </TableHead>
                                        <TableHead className="font-semibold">
                                            Registered By
                                        </TableHead>
                                        <TableHead className="w-[120px] text-center font-semibold">
                                            Status
                                        </TableHead>
                                        <TableHead className="w-[150px] pr-6 text-right font-semibold">
                                            Review
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredProjects.length > 0 ? (
                                        filteredProjects.map((project) => {
                                            const isExpanded =
                                                expandedRowId === project.id;
                                            const isPending =
                                                project.status === 3;

                                            return (
                                                <React.Fragment
                                                    key={project.id}
                                                >
                                                    <TableRow className="border-b border-border/30 transition-colors hover:bg-muted/10">
                                                        <TableCell className="pl-4 text-center">
                                                            <Checkbox
                                                                checked={selectedProjectIds.includes(
                                                                    project.id,
                                                                )}
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    handleSelectRow(
                                                                        !!checked,
                                                                        project.id,
                                                                    )
                                                                }
                                                                disabled={
                                                                    !isPending
                                                                }
                                                            />
                                                        </TableCell>
                                                        <TableCell className="font-mono text-xs font-bold text-foreground">
                                                            {project.pcode ? (
                                                                <span className="flex items-center gap-1">
                                                                    {
                                                                        project.pcode
                                                                    }
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        onClick={() =>
                                                                            handleOpenEditCode(
                                                                                project,
                                                                            )
                                                                        }
                                                                        className="h-6 w-6 text-muted-foreground hover:text-primary"
                                                                        title="Override project code"
                                                                    >
                                                                        <Edit3 className="h-3 w-3" />
                                                                    </Button>
                                                                </span>
                                                            ) : (
                                                                <span className="text-muted-foreground/50 italic">
                                                                    Unassigned
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="max-w-sm truncate font-medium">
                                                            <button
                                                                onClick={() =>
                                                                    toggleRow(
                                                                        project.id,
                                                                    )
                                                                }
                                                                className="flex w-full items-center gap-1.5 truncate text-left font-semibold hover:underline"
                                                            >
                                                                {isExpanded ? (
                                                                    <ChevronUp className="h-4 w-4 shrink-0 opacity-60" />
                                                                ) : (
                                                                    <ChevronDown className="h-4 w-4 shrink-0 opacity-60" />
                                                                )}
                                                                <span className="truncate">
                                                                    {
                                                                        project.title
                                                                    }
                                                                </span>
                                                            </button>
                                                        </TableCell>
                                                        <TableCell className="text-sm">
                                                            {project.user ? (
                                                                <div>
                                                                    <div className="font-medium">
                                                                        {
                                                                            project
                                                                                .user
                                                                                .name
                                                                        }
                                                                    </div>
                                                                    <div className="text-xs text-muted-foreground">
                                                                        {
                                                                            project
                                                                                .user
                                                                                .email
                                                                        }
                                                                    </div>
                                                                </div>
                                                            ) : (
                                                                <span className="italic opacity-60">
                                                                    Unknown
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-center">
                                                            <Badge
                                                                variant={getStatusBadgeVariant(
                                                                    project.status,
                                                                )}
                                                            >
                                                                {
                                                                    project.status_label
                                                                }
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="pr-6 text-right">
                                                            <div className="flex items-center justify-end gap-1.5">
                                                                {isPending ? (
                                                                    <>
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            onClick={() => {
                                                                                router.post(
                                                                                    '/dashboard/approvals/approve',
                                                                                    {
                                                                                        project_ids:
                                                                                            [
                                                                                                project.id,
                                                                                            ],
                                                                                    },
                                                                                );
                                                                            }}
                                                                            className="h-8 w-8 text-emerald-500 hover:bg-emerald-500/10"
                                                                            title="Approve project"
                                                                        >
                                                                            <CheckCheck className="h-4 w-4" />
                                                                        </Button>
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            onClick={() =>
                                                                                handleOpenReject(
                                                                                    project,
                                                                                )
                                                                            }
                                                                            className="h-8 w-8 text-amber-500 hover:bg-amber-500/10"
                                                                            title="Reject & Request Changes"
                                                                        >
                                                                            <XOctagon className="h-4 w-4" />
                                                                        </Button>
                                                                    </>
                                                                ) : project.status ===
                                                                      4 &&
                                                                  (userRole ===
                                                                      'admin' ||
                                                                      userRole ===
                                                                          'lecturer') ? (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        onClick={() =>
                                                                            handleOpenReject(
                                                                                project,
                                                                            )
                                                                        }
                                                                        className="h-8 w-8 text-amber-500 hover:bg-amber-500/10"
                                                                        title="Reject & Send Back for Changes"
                                                                    >
                                                                        <XOctagon className="h-4 w-4" />
                                                                    </Button>
                                                                ) : (
                                                                    <span className="mr-2 text-xs text-muted-foreground italic opacity-60">
                                                                        Reviewed
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>

                                                    {/* Expanded Row */}
                                                    {isExpanded && (
                                                        <TableRow className="border-b border-border/30 bg-muted/10">
                                                            <TableCell
                                                                colSpan={6}
                                                                className="p-6"
                                                            >
                                                                <div className="grid gap-6 md:grid-cols-3">
                                                                    <div className="space-y-4 md:col-span-2">
                                                                        <div>
                                                                            <Label className="text-xs tracking-wider text-muted-foreground uppercase">
                                                                                Abstract
                                                                                Description
                                                                            </Label>
                                                                            <p className="mt-1 rounded-lg border border-border/40 bg-background/50 p-4 text-sm leading-relaxed whitespace-pre-wrap">
                                                                                {
                                                                                    project.abstract
                                                                                }
                                                                            </p>
                                                                        </div>

                                                                        <div className="flex flex-wrap gap-4">
                                                                            {project.video_url && (
                                                                                <a
                                                                                    href={
                                                                                        project.video_url
                                                                                    }
                                                                                    target="_blank"
                                                                                    rel="noreferrer"
                                                                                    className="flex items-center gap-2 rounded-lg border border-red-500/20 bg-red-500/5 px-3 py-2 text-xs font-semibold text-red-500 transition-colors hover:bg-red-500/10"
                                                                                >
                                                                                    <Video className="h-4 w-4" />
                                                                                    Watch
                                                                                    Video
                                                                                    Demo
                                                                                    <ExternalLink className="h-3 w-3 opacity-60" />
                                                                                </a>
                                                                            )}
                                                                            {project.poster_url && (
                                                                                <a
                                                                                    href={
                                                                                        project.poster_url
                                                                                    }
                                                                                    target="_blank"
                                                                                    rel="noreferrer"
                                                                                    className="flex items-center gap-2 rounded-lg border border-blue-500/20 bg-blue-500/5 px-3 py-2 text-xs font-semibold text-blue-500 transition-colors hover:bg-blue-500/10"
                                                                                >
                                                                                    <FileText className="h-4 w-4" />
                                                                                    View
                                                                                    Project
                                                                                    Poster
                                                                                    <ExternalLink className="h-3 w-3 opacity-60" />
                                                                                </a>
                                                                            )}
                                                                        </div>
                                                                    </div>

                                                                    <div className="space-y-4">
                                                                        {/* Supervisor Specs */}
                                                                        <div className="space-y-2 rounded-lg border border-border/40 bg-background/50 p-4">
                                                                            <Label className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                                                Supervisor
                                                                            </Label>
                                                                            <div className="flex items-center gap-2 text-sm font-medium">
                                                                                <User className="h-4 w-4 text-muted-foreground" />
                                                                                {
                                                                                    project.supervisor_name
                                                                                }
                                                                            </div>
                                                                            <div className="flex items-center gap-1.5 truncate text-xs text-muted-foreground">
                                                                                <Mail className="h-3.5 w-3.5" />
                                                                                <span className="truncate">
                                                                                    {
                                                                                        project.supervisor_email
                                                                                    }
                                                                                </span>
                                                                            </div>
                                                                            {project.supervisor_phone && (
                                                                                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                                                    <Phone className="h-3.5 w-3.5" />
                                                                                    {
                                                                                        project.supervisor_phone
                                                                                    }
                                                                                </div>
                                                                            )}
                                                                        </div>

                                                                        {/* Team Members List */}
                                                                        <div className="space-y-2 rounded-lg border border-border/40 bg-background/50 p-4">
                                                                            <Label className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                                                Team
                                                                                Members
                                                                            </Label>
                                                                            {project.team_members &&
                                                                            project
                                                                                .team_members
                                                                                .length >
                                                                                0 ? (
                                                                                <ul className="space-y-1.5 divide-y divide-border/20">
                                                                                    {project.team_members.map(
                                                                                        (
                                                                                            member,
                                                                                            i,
                                                                                        ) => (
                                                                                            <li
                                                                                                key={
                                                                                                    i
                                                                                                }
                                                                                                className="pt-1.5 text-xs first:pt-0"
                                                                                            >
                                                                                                <span className="font-semibold">
                                                                                                    {
                                                                                                        member.name
                                                                                                    }
                                                                                                </span>
                                                                                                {member.email && (
                                                                                                    <span className="block text-[10px] text-muted-foreground">
                                                                                                        {
                                                                                                            member.email
                                                                                                        }
                                                                                                    </span>
                                                                                                )}
                                                                                            </li>
                                                                                        ),
                                                                                    )}
                                                                                </ul>
                                                                            ) : (
                                                                                <p className="text-xs text-muted-foreground italic">
                                                                                    No
                                                                                    members
                                                                                    listed
                                                                                </p>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </TableCell>
                                                        </TableRow>
                                                    )}
                                                </React.Fragment>
                                            );
                                        })
                                    ) : (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="h-32 text-center text-muted-foreground"
                                            >
                                                No projects found matching
                                                filters.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Rejection Comments Dialog */}
            <Dialog
                open={!!rejectProject}
                onOpenChange={(open) => !open && setRejectProject(null)}
            >
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Reject & Request Modification</DialogTitle>
                        <DialogDescription>
                            Explain what changes the participant needs to make
                            to their project registration.
                        </DialogDescription>
                    </DialogHeader>
                    {rejectProject && (
                        <form
                            onSubmit={handleRejectSubmit}
                            className="space-y-4 py-2"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="comments">
                                    Audit / Review Comments
                                </Label>
                                <Textarea
                                    id="comments"
                                    value={rejectForm.data.admin_comments}
                                    onChange={(e) =>
                                        rejectForm.setData(
                                            'admin_comments',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. Please upload a higher resolution poster and fill in the correct student email address."
                                    className="h-32 resize-none bg-background/50"
                                    required
                                />
                                <InputError
                                    message={rejectForm.errors.admin_comments}
                                />
                            </div>
                            <DialogFooter className="pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setRejectProject(null)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={rejectForm.processing}
                                >
                                    {rejectForm.processing
                                        ? 'Rejecting...'
                                        : 'Confirm Rejection'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>

            {/* Edit Code Dialog */}
            <Dialog
                open={!!editCodeProject}
                onOpenChange={(open) => !open && setEditCodeProject(null)}
            >
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Override Project Code</DialogTitle>
                        <DialogDescription>
                            Override the automatically generated code for this
                            competition session.
                        </DialogDescription>
                    </DialogHeader>
                    {editCodeProject && (
                        <form
                            onSubmit={handleEditCodeSubmit}
                            className="space-y-4 py-2"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="pcode">Project Code</Label>
                                <Input
                                    id="pcode"
                                    value={editCodeForm.data.pcode}
                                    onChange={(e) =>
                                        editCodeForm.setData(
                                            'pcode',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. C1-002"
                                    className="bg-background/50 font-mono"
                                    required
                                />
                                <InputError
                                    message={editCodeForm.errors.pcode}
                                />
                            </div>
                            <DialogFooter className="pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setEditCodeProject(null)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={editCodeForm.processing}
                                >
                                    {editCodeForm.processing
                                        ? 'Saving...'
                                        : 'Update Code'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>

            {/* Register Project Dialog */}
            <Dialog
                open={isRegisterOpen}
                onOpenChange={(open) => !open && handleCloseRegister()}
            >
                <DialogContent className="sm:max-w-[700px] max-h-[90vh] overflow-y-auto border border-border/50 bg-card/95 backdrop-blur-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-xl font-bold text-foreground">
                            <Plus className="h-5 w-5 text-primary" />
                            Register New Project
                        </DialogTitle>
                        <DialogDescription>
                            Create a direct, approved project registration for an existing registered student.
                        </DialogDescription>
                    </DialogHeader>

                    {!activeSession && (
                        <div className="rounded-lg border border-destructive/20 bg-destructive/10 p-3 text-sm text-destructive flex items-center gap-2">
                            <AlertCircle className="h-4 w-4 shrink-0" />
                            <span>No active competition session is currently open. You cannot register a project.</span>
                        </div>
                    )}

                    <form onSubmit={handleRegisterSubmit} className="space-y-6 py-2">
                        {/* Student Search Select */}
                        <div className="student-search-container relative space-y-2">
                            <Label htmlFor="student-search" className="text-sm font-semibold">
                                Select Student (Registered User)
                            </Label>
                            {selectedStudent ? (
                                <div className="flex items-center justify-between rounded-lg border border-primary/30 bg-primary/5 p-3 text-sm shadow-sm">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <User className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <p className="font-semibold text-foreground">{selectedStudent.name}</p>
                                            <p className="text-xs text-muted-foreground">{selectedStudent.email}</p>
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setData('user_id', '');
                                            setStudentSearch('');
                                        }}
                                        className="text-xs text-destructive hover:bg-destructive/10"
                                    >
                                        Change Student
                                    </Button>
                                </div>
                            ) : (
                                <div className="relative">
                                    <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        id="student-search"
                                        placeholder="Search by student name or email..."
                                        value={studentSearch}
                                        onChange={(e) => {
                                            setStudentSearch(e.target.value);
                                            setIsStudentDropdownOpen(true);
                                        }}
                                        onFocus={() => setIsStudentDropdownOpen(true)}
                                        className="pl-9 bg-background/50 border-border/50"
                                        disabled={!activeSession}
                                        required
                                    />
                                    {isStudentDropdownOpen && (
                                        <div className="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-border bg-popover p-1 shadow-lg backdrop-blur-md">
                                            {filteredStudents.length > 0 ? (
                                                filteredStudents.map((student) => (
                                                    <button
                                                        key={student.id}
                                                        type="button"
                                                        onClick={() => {
                                                            setData('user_id', student.id.toString());
                                                            setStudentSearch(student.name);
                                                            setIsStudentDropdownOpen(false);
                                                        }}
                                                        className="flex w-full flex-col rounded-sm px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground transition-colors"
                                                    >
                                                        <span className="font-medium text-foreground">{student.name}</span>
                                                        <span className="text-xs text-muted-foreground">{student.email}</span>
                                                    </button>
                                                ))
                                            ) : (
                                                <p className="p-3 text-center text-xs text-muted-foreground">
                                                    No registered students found
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}
                            <InputError message={errors.user_id} />
                        </div>

                        {/* Category & Institution Type */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="category_id" className="text-sm font-semibold">Category</Label>
                                <Select
                                    value={data.category_id}
                                    onValueChange={(val) => setData('category_id', val)}
                                    disabled={!activeSession}
                                >
                                    <SelectTrigger id="category_id" className="bg-background/50 border-border/50">
                                        <SelectValue placeholder="Select a category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {activeCategories.map((cat) => (
                                            <SelectItem key={cat.id} value={cat.id.toString()}>
                                                {cat.name} ({cat.code})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.category_id} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="institution_type" className="text-sm font-semibold">Institution Type</Label>
                                <Select
                                    value={data.institution_type}
                                    onValueChange={(val) => setData('institution_type', val)}
                                    disabled={!activeSession}
                                >
                                    <SelectTrigger id="institution_type" className="bg-background/50 border-border/50">
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="utem">UTeM</SelectItem>
                                        <SelectItem value="ipt">Other IPT</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.institution_type} />
                            </div>
                        </div>

                        {/* Title & Abstract */}
                        <div className="space-y-2">
                            <Label htmlFor="title" className="text-sm font-semibold">Project Title</Label>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                placeholder="Enter project title"
                                className="bg-background/50 border-border/50"
                                disabled={!activeSession}
                                required
                            />
                            <InputError message={errors.title} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="abstract" className="text-sm font-semibold">Abstract Description</Label>
                            <Textarea
                                id="abstract"
                                value={data.abstract}
                                onChange={(e) => setData('abstract', e.target.value)}
                                placeholder="Provide a detailed project abstract..."
                                className="h-28 resize-none bg-background/50 border-border/50"
                                disabled={!activeSession}
                                required
                            />
                            <InputError message={errors.abstract} />
                        </div>

                        {/* Supervisor Details */}
                        <div className="rounded-lg border border-border/50 bg-muted/5 p-4 space-y-4">
                            <h3 className="text-sm font-semibold text-foreground flex items-center gap-1.5">
                                <User className="h-4 w-4 text-primary" />
                                Supervisor Information
                            </h3>
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="supervisor_name" className="text-xs font-semibold">Supervisor Name</Label>
                                    <Input
                                        id="supervisor_name"
                                        value={data.supervisor_name}
                                        onChange={(e) => setData('supervisor_name', e.target.value)}
                                        placeholder="e.g. Dr. Ahmad"
                                        className="h-9 bg-background/50 border-border/50"
                                        disabled={!activeSession}
                                        required
                                    />
                                    <InputError message={errors.supervisor_name} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="supervisor_email" className="text-xs font-semibold">Supervisor Email</Label>
                                    <Input
                                        id="supervisor_email"
                                        type="email"
                                        value={data.supervisor_email}
                                        onChange={(e) => setData('supervisor_email', e.target.value)}
                                        placeholder="e.g. supervisor@utem.edu.my"
                                        className="h-9 bg-background/50 border-border/50"
                                        disabled={!activeSession}
                                        required
                                    />
                                    <InputError message={errors.supervisor_email} />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="supervisor_phone" className="text-xs font-semibold">Supervisor Phone (Optional)</Label>
                                <Input
                                    id="supervisor_phone"
                                    value={data.supervisor_phone}
                                    onChange={(e) => setData('supervisor_phone', e.target.value)}
                                    placeholder="e.g. +60123456789"
                                    className="h-9 bg-background/50 border-border/50"
                                    disabled={!activeSession}
                                />
                                <InputError message={errors.supervisor_phone} />
                            </div>
                        </div>

                        {/* Video & Poster URLs */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="video_url" className="text-sm font-semibold">Video Demo URL (Optional)</Label>
                                <Input
                                    id="video_url"
                                    type="url"
                                    value={data.video_url}
                                    onChange={(e) => setData('video_url', e.target.value)}
                                    placeholder="e.g. https://youtube.com/watch?v=..."
                                    className="bg-background/50 border-border/50"
                                    disabled={!activeSession}
                                />
                                <InputError message={errors.video_url} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="poster_url" className="text-sm font-semibold">Poster URL (Optional)</Label>
                                <Input
                                    id="poster_url"
                                    type="url"
                                    value={data.poster_url}
                                    onChange={(e) => setData('poster_url', e.target.value)}
                                    placeholder="e.g. https://drive.google.com/..."
                                    className="bg-background/50 border-border/50"
                                    disabled={!activeSession}
                                />
                                <InputError message={errors.poster_url} />
                            </div>
                        </div>

                        {/* Team Members */}
                        <div className="space-y-4 rounded-lg border border-border/50 bg-muted/5 p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="text-sm font-semibold text-foreground flex items-center gap-1.5">
                                        <UserPlus className="h-4 w-4 text-primary" />
                                        Team Members (Optional)
                                    </h3>
                                    <p className="text-xs text-muted-foreground">Add extra team members to this project registration.</p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addTeamMember}
                                    disabled={!activeSession}
                                    className="h-8 gap-1 text-xs"
                                >
                                    <Plus className="h-3.5 w-3.5" />
                                    Add Member
                                </Button>
                            </div>

                            {data.team_members.length > 0 ? (
                                <div className="space-y-3">
                                    {data.team_members.map((member, index) => (
                                        <div key={index} className="relative rounded-lg border border-border/40 bg-background/30 p-3 space-y-3">
                                            <div className="flex items-center justify-between">
                                                <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                                    Member #{index + 1}
                                                </span>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => removeTeamMember(index)}
                                                    className="h-7 w-7 text-destructive hover:bg-destructive/10"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                <div className="space-y-1">
                                                    <Label htmlFor={`member-${index}-name`} className="text-[11px] font-semibold">Full Name</Label>
                                                    <Input
                                                        id={`member-${index}-name`}
                                                        value={member.name}
                                                        onChange={(e) => updateTeamMember(index, 'name', e.target.value)}
                                                        placeholder="Member Name"
                                                        className="h-8 text-xs bg-background/50 border-border/50"
                                                        required
                                                    />
                                                    <InputError message={errors[`team_members.${index}.name` as any]} />
                                                </div>
                                                <div className="space-y-1">
                                                    <Label htmlFor={`member-${index}-email`} className="text-[11px] font-semibold">Email</Label>
                                                    <Input
                                                        id={`member-${index}-email`}
                                                        type="email"
                                                        value={member.email || ''}
                                                        onChange={(e) => updateTeamMember(index, 'email', e.target.value)}
                                                        placeholder="member@email.com"
                                                        className="h-8 text-xs bg-background/50 border-border/50"
                                                    />
                                                    <InputError message={errors[`team_members.${index}.email` as any]} />
                                                </div>
                                                <div className="space-y-1">
                                                    <Label htmlFor={`member-${index}-phone`} className="text-[11px] font-semibold">Phone</Label>
                                                    <Input
                                                        id={`member-${index}-phone`}
                                                        value={member.phone || ''}
                                                        onChange={(e) => updateTeamMember(index, 'phone', e.target.value)}
                                                        placeholder="+601..."
                                                        className="h-8 text-xs bg-background/50 border-border/50"
                                                    />
                                                    <InputError message={errors[`team_members.${index}.phone` as any]} />
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-xs text-muted-foreground italic text-center py-4 border border-dashed border-border/40 rounded-lg bg-background/10">
                                    No team members added yet. Single-presenter project.
                                </p>
                            )}
                        </div>

                        <DialogFooter className="pt-4 gap-2 sm:gap-0">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleCloseRegister}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing || !activeSession}
                                className="bg-primary hover:bg-primary/95 text-white"
                            >
                                {processing ? 'Registering...' : 'Register Project'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

ApprovalsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Project Approvals',
            href: '/dashboard/approvals',
        },
    ],
};
