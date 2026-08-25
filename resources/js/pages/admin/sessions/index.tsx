import { Head, useForm } from '@inertiajs/react';
import {
    Calendar,
    Plus,
    Search,
    Edit2,
    Trash2,
    Lock,
    Unlock,
    CheckCircle2,
    XCircle,
    AlertTriangle,
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
} from '@/components/ui/card';
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
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface CompetitionSession {
    id: number;
    name: string;
    is_active: boolean;
    r2_locked: boolean;
    categories_count?: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    sessions: CompetitionSession[];
}

export default function SessionsIndex({ sessions }: Props) {
    const [searchTerm, setSearchTerm] = useState('');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [selectedSession, setSelectedSession] =
        useState<CompetitionSession | null>(null);

    // Form for create
    const createForm = useForm({
        name: '',
        is_active: false,
        r2_locked: false,
    });

    // Form for edit
    const editForm = useForm({
        name: '',
        is_active: false,
        r2_locked: false,
    });

    // Form for delete
    const deleteForm = useForm({});

    // Filter sessions based on search query
    const filteredSessions = useMemo(() => {
        return sessions.filter((session) =>
            session.name.toLowerCase().includes(searchTerm.toLowerCase()),
        );
    }, [sessions, searchTerm]);

    const activeSessionCount = useMemo(() => {
        return sessions.filter((s) => s.is_active).length;
    }, [sessions]);

    const lockedSessionCount = useMemo(() => {
        return sessions.filter((s) => s.r2_locked).length;
    }, [sessions]);

    const handleOpenCreate = () => {
        createForm.reset();
        createForm.clearErrors();
        setIsCreateOpen(true);
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/dashboard/sessions', {
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const handleOpenEdit = (session: CompetitionSession) => {
        setSelectedSession(session);
        editForm.setData({
            name: session.name,
            is_active: session.is_active,
            r2_locked: session.r2_locked,
        });
        editForm.clearErrors();
        setIsEditOpen(true);
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedSession) {
return;
}

        editForm.put(`/dashboard/sessions/${selectedSession.id}`, {
            onSuccess: () => {
                setIsEditOpen(false);
                setSelectedSession(null);
            },
        });
    };

    const handleOpenDelete = (session: CompetitionSession) => {
        setSelectedSession(session);
        setIsDeleteOpen(true);
    };

    const handleDeleteSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedSession) {
return;
}

        deleteForm.delete(`/dashboard/sessions/${selectedSession.id}`, {
            onSuccess: () => {
                setIsDeleteOpen(false);
                setSelectedSession(null);
            },
        });
    };

    return (
        <>
            <Head title="Sessions Management" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                            <Calendar className="h-8 w-8 text-primary" />
                            Sessions Management
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Configure active competition semesters, sessions,
                            and Round 2 judging lock statuses.
                        </p>
                    </div>
                    <div>
                        <Button
                            onClick={handleOpenCreate}
                            className="w-full sm:w-auto"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Create Session
                        </Button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Total Sessions
                            </CardTitle>
                            <Calendar className="h-4 w-4 text-primary" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {sessions.length}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Competition sessions configured
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Active Session
                            </CardTitle>
                            <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {activeSessionCount}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Currently receiving submissions
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Round 2 Locked
                            </CardTitle>
                            <Lock className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {lockedSessionCount}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Sessions with score modification blocked
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Table Card */}
                <Card className="overflow-hidden border border-border/50 bg-card/40 backdrop-blur-sm">
                    <CardHeader className="border-b border-border/40 p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="text-lg font-semibold">
                                    Configured Sessions
                                </CardTitle>
                                <CardDescription>
                                    List and edit competition semesters.
                                </CardDescription>
                            </div>
                            <div className="relative w-full sm:w-72">
                                <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search sessions..."
                                    value={searchTerm}
                                    onChange={(e) =>
                                        setSearchTerm(e.target.value)
                                    }
                                    className="w-full border-border/50 bg-background/50 pl-9"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="border-b border-border/40 bg-muted/20 hover:bg-transparent">
                                        <TableHead className="py-3 pl-6 font-semibold">
                                            Session Name
                                        </TableHead>
                                        <TableHead className="w-[120px] text-center font-semibold">
                                            Categories
                                        </TableHead>
                                        <TableHead className="w-[120px] text-center font-semibold">
                                            Status
                                        </TableHead>
                                        <TableHead className="w-[150px] text-center font-semibold">
                                            Round 2 Judging
                                        </TableHead>
                                        <TableHead className="w-[150px] pr-6 text-right font-semibold">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredSessions.length > 0 ? (
                                        filteredSessions.map((session) => (
                                            <TableRow
                                                key={session.id}
                                                className="border-b border-border/30 transition-colors hover:bg-muted/10"
                                            >
                                                <TableCell className="py-4 pl-6 font-medium">
                                                    {session.name}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <Badge
                                                        variant="outline"
                                                        className="border-border/50 bg-background/50 px-2 py-0.5"
                                                    >
                                                        {session.categories_count ??
                                                            0}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {session.is_active ? (
                                                        <Badge
                                                            variant="success"
                                                            className="gap-1"
                                                        >
                                                            <CheckCircle2 className="h-3 w-3" />
                                                            Active
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            variant="secondary"
                                                            className="gap-1"
                                                        >
                                                            <XCircle className="h-3 w-3" />
                                                            Inactive
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {session.r2_locked ? (
                                                        <Badge
                                                            variant="destructive"
                                                            className="gap-1"
                                                        >
                                                            <Lock className="h-3 w-3" />
                                                            Locked
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1 border-emerald-500/20 bg-emerald-500/5 text-emerald-500"
                                                        >
                                                            <Unlock className="h-3 w-3" />
                                                            Open
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="pr-6 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                handleOpenEdit(
                                                                    session,
                                                                )
                                                            }
                                                            className="h-8 w-8 hover:bg-primary/10 hover:text-primary"
                                                            title="Edit session"
                                                        >
                                                            <Edit2 className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                handleOpenDelete(
                                                                    session,
                                                                )
                                                            }
                                                            disabled={
                                                                session.is_active
                                                            }
                                                            className="h-8 w-8 text-destructive hover:bg-destructive/10 hover:text-destructive disabled:opacity-30 disabled:hover:bg-transparent"
                                                            title={
                                                                session.is_active
                                                                    ? 'Cannot delete active session'
                                                                    : 'Delete session'
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    ) : (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="h-32 text-center text-muted-foreground"
                                            >
                                                No sessions found matching your
                                                search.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Create Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Create Competition Session</DialogTitle>
                        <DialogDescription>
                            Create a new session or semester for the INOTEK
                            competition.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={handleCreateSubmit}
                        className="space-y-4 py-2"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="name">Session Name</Label>
                            <Input
                                id="name"
                                value={createForm.data.name}
                                onChange={(e) =>
                                    createForm.setData('name', e.target.value)
                                }
                                placeholder="e.g. Semester 1 2026/2027"
                                className="bg-background/50"
                                required
                            />
                            <InputError message={createForm.errors.name} />
                        </div>

                        <div className="flex items-center justify-between rounded-lg border border-border/50 bg-muted/20 p-3">
                            <div className="space-y-0.5">
                                <Label htmlFor="is_active">
                                    Set as Active Session
                                </Label>
                                <p className="text-[10px] text-muted-foreground">
                                    Enabling this will automatically deactivate
                                    all other sessions.
                                </p>
                            </div>
                            <Switch
                                id="is_active"
                                checked={createForm.data.is_active}
                                onCheckedChange={(checked) =>
                                    createForm.setData('is_active', checked)
                                }
                            />
                        </div>

                        <DialogFooter className="pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsCreateOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                            >
                                {createForm.processing
                                    ? 'Creating...'
                                    : 'Create Session'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Edit Session</DialogTitle>
                        <DialogDescription>
                            Modify parameters for the selected session.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedSession && (
                        <form
                            onSubmit={handleEditSubmit}
                            className="space-y-4 py-2"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="edit-name">Session Name</Label>
                                <Input
                                    id="edit-name"
                                    value={editForm.data.name}
                                    onChange={(e) =>
                                        editForm.setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Semester 1 2026/2027"
                                    className="bg-background/50"
                                    required
                                />
                                <InputError message={editForm.errors.name} />
                            </div>

                            <div className="flex items-center justify-between rounded-lg border border-border/50 bg-muted/20 p-3">
                                <div className="space-y-0.5">
                                    <Label htmlFor="edit-is_active">
                                        Set as Active Session
                                    </Label>
                                    <p className="text-[10px] text-muted-foreground">
                                        Enabling this will automatically
                                        deactivate all other sessions.
                                    </p>
                                </div>
                                <Switch
                                    id="edit-is_active"
                                    checked={editForm.data.is_active}
                                    onCheckedChange={(checked) =>
                                        editForm.setData('is_active', checked)
                                    }
                                    disabled={selectedSession.is_active} // Cannot directly toggle off active session unless another is activated
                                />
                            </div>

                            <div className="flex items-center justify-between rounded-lg border border-border/50 bg-muted/20 p-3">
                                <div className="space-y-0.5">
                                    <Label htmlFor="edit-r2_locked">
                                        Finalize Round 2 Judging
                                    </Label>
                                    <p className="text-[10px] text-muted-foreground">
                                        Prevent any further score entry or edits
                                        for Round 2.
                                    </p>
                                </div>
                                <Switch
                                    id="edit-r2_locked"
                                    checked={editForm.data.r2_locked}
                                    onCheckedChange={(checked) =>
                                        editForm.setData('r2_locked', checked)
                                    }
                                />
                            </div>

                            <DialogFooter className="pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsEditOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={editForm.processing}
                                >
                                    {editForm.processing
                                        ? 'Saving...'
                                        : 'Save Changes'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-destructive">
                            <AlertTriangle className="h-5 w-5" />
                            Delete Session
                        </DialogTitle>
                        <DialogDescription className="text-sm">
                            Are you sure you want to delete this session? This
                            action is permanent. All related categories and data
                            will be removed.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedSession && (
                        <form
                            onSubmit={handleDeleteSubmit}
                            className="space-y-4 py-2"
                        >
                            <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-xs text-destructive">
                                <p className="font-semibold">
                                    Session to delete:
                                </p>
                                <p className="mt-1 font-mono font-bold">
                                    {selectedSession.name}
                                </p>
                            </div>
                            <DialogFooter className="gap-2 pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsDeleteOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={deleteForm.processing}
                                >
                                    {deleteForm.processing
                                        ? 'Deleting...'
                                        : 'Confirm Delete'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

SessionsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Sessions Management',
            href: '/dashboard/sessions',
        },
    ],
};
