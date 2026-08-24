import { Head, useForm, usePage } from '@inertiajs/react';
import {
    Users,
    Plus,
    Search,
    Edit2,
    Trash2,
    Lock,
    UserCheck,
    Mail,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    Shield,
    AlertTriangle,
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
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface Role {
    id: number;
    name: string;
    label: string;
    description: string | null;
}

interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    role_id: number;
    role: Role | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    users: User[];
    roles: Role[];
}

export default function UsersIndex({ users, roles }: Props) {
    const { auth } = usePage().props as any;
    const currentUser = auth?.user;

    const [searchTerm, setSearchTerm] = useState('');
    const [sortBy, setSortBy] = useState<string>('name');
    const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc');
    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(10);

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);

    // Inertia form for create
    const createForm = useForm({
        name: '',
        username: '',
        email: '',
        password: '',
        role_id: '4', // Default role 'user'
    });

    // Inertia form for edit
    const editForm = useForm({
        name: '',
        username: '',
        email: '',
        password: '', // Optional
        role_id: '',
    });

    // Inertia form for delete
    const deleteForm = useForm({});

    // Client-side Datatable processing: Filtering
    const filteredUsers = useMemo(() => {
        return users.filter((user) => {
            const roleLabel = user.role?.label || 'No Role';
            const term = searchTerm.toLowerCase();

            return (
                user.name.toLowerCase().includes(term) ||
                user.username.toLowerCase().includes(term) ||
                user.email.toLowerCase().includes(term) ||
                roleLabel.toLowerCase().includes(term)
            );
        });
    }, [users, searchTerm]);

    // Client-side Datatable processing: Sorting
    const sortedUsers = useMemo(() => {
        if (!sortBy) {
return filteredUsers;
}

        return [...filteredUsers].sort((a, b) => {
            let valA = '';
            let valB = '';

            if (sortBy === 'role') {
                valA = a.role?.label || '';
                valB = b.role?.label || '';
            } else {
                valA = (a as any)[sortBy]?.toString() || '';
                valB = (b as any)[sortBy]?.toString() || '';
            }

            return sortOrder === 'asc'
                ? valA.localeCompare(valB, undefined, {
                      numeric: true,
                      sensitivity: 'base',
                  })
                : valB.localeCompare(valA, undefined, {
                      numeric: true,
                      sensitivity: 'base',
                  });
        });
    }, [filteredUsers, sortBy, sortOrder]);

    // Client-side Datatable processing: Pagination
    const paginatedUsers = useMemo(() => {
        const startIndex = (currentPage - 1) * pageSize;

        return sortedUsers.slice(startIndex, startIndex + pageSize);
    }, [sortedUsers, currentPage, pageSize]);

    const totalPages = useMemo(() => {
        return Math.ceil(sortedUsers.length / pageSize) || 1;
    }, [sortedUsers, pageSize]);

    // Reset page to 1 when search term changes
    useEffect(() => {
        setCurrentPage(1);
    }, [searchTerm, pageSize]);

    // Role Stats
    const stats = useMemo(() => {
        const total = users.length;
        const admins = users.filter((u) => u.role_id === 1).length;
        const lecturers = users.filter((u) => u.role_id === 2).length;
        const judges = users.filter((u) => u.role_id === 3).length;
        const standardUsers = users.filter((u) => u.role_id === 4).length;

        return { total, admins, lecturers, judges, standardUsers };
    }, [users]);

    const handleSort = (column: string) => {
        if (sortBy === column) {
            setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
        } else {
            setSortBy(column);
            setSortOrder('asc');
        }
    };

    const getRoleBadgeVariant = (roleId: number) => {
        switch (roleId) {
            case 1: // admin
                return 'destructive';
            case 2: // lecturer
                return 'info';
            case 3: // judge
                return 'warning';
            default:
                return 'secondary';
        }
    };

    const handleOpenCreate = () => {
        createForm.reset();
        createForm.clearErrors();
        setIsCreateOpen(true);
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/dashboard/users', {
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const handleOpenEdit = (user: User) => {
        setSelectedUser(user);
        editForm.setData({
            name: user.name,
            username: user.username,
            email: user.email,
            password: '',
            role_id: user.role_id.toString(),
        });
        editForm.clearErrors();
        setIsEditOpen(true);
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedUser) {
return;
}

        editForm.put(`/dashboard/users/${selectedUser.id}`, {
            onSuccess: () => {
                setIsEditOpen(false);
                setSelectedUser(null);
            },
        });
    };

    const handleOpenDelete = (user: User) => {
        setSelectedUser(user);
        setIsDeleteOpen(true);
    };

    const handleDeleteSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedUser) {
return;
}

        deleteForm.delete(`/dashboard/users/${selectedUser.id}`, {
            onSuccess: () => {
                setIsDeleteOpen(false);
                setSelectedUser(null);
            },
        });
    };

    return (
        <>
            <Head title="Users Management" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                            <Users className="h-8 w-8 text-primary" />
                            Users Management
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage user profiles, credential settings, and
                            assign specific operational roles.
                        </p>
                    </div>
                    <div>
                        <Button
                            onClick={handleOpenCreate}
                            className="w-full sm:w-auto"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add User Account
                        </Button>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Total Users
                            </CardTitle>
                            <Users className="h-4 w-4 text-primary" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.total}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Total registered accounts
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Admins
                            </CardTitle>
                            <Shield className="h-4 w-4 text-destructive" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.admins}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                System administrators
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Lecturers
                            </CardTitle>
                            <UserCheck className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.lecturers}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Academic supervisors
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Judges
                            </CardTitle>
                            <UserCheck className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.judges}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Project evaluation panel
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Users
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.standardUsers}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Standard student accounts
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Datatable Card */}
                <Card className="overflow-hidden border border-border/50 bg-card/40 backdrop-blur-sm">
                    <CardHeader className="border-b border-border/40 p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="text-lg font-semibold">
                                    User Database
                                </CardTitle>
                                <CardDescription>
                                    View, filter, edit, or delete users within
                                    the system.
                                </CardDescription>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <div className="relative w-full sm:w-64">
                                    <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Search name, email, role..."
                                        value={searchTerm}
                                        onChange={(e) =>
                                            setSearchTerm(e.target.value)
                                        }
                                        className="w-full border-border/50 bg-background/50 pl-9"
                                    />
                                </div>
                                <div className="flex h-9 items-center gap-1 rounded-md border border-border/50 bg-background/30 px-2 py-1.5 text-sm text-muted-foreground">
                                    <span>Rows:</span>
                                    <select
                                        value={pageSize}
                                        onChange={(e) =>
                                            setPageSize(Number(e.target.value))
                                        }
                                        className="cursor-pointer border-none bg-transparent text-xs font-semibold text-foreground outline-none"
                                    >
                                        <option
                                            value={5}
                                            className="dark:bg-popover"
                                        >
                                            5
                                        </option>
                                        <option
                                            value={10}
                                            className="dark:bg-popover"
                                        >
                                            10
                                        </option>
                                        <option
                                            value={25}
                                            className="dark:bg-popover"
                                        >
                                            25
                                        </option>
                                        <option
                                            value={50}
                                            className="dark:bg-popover"
                                        >
                                            50
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="border-b border-border/40 bg-muted/20 hover:bg-transparent">
                                        <TableHead className="py-3 pl-6 font-semibold">
                                            <button
                                                onClick={() =>
                                                    handleSort('name')
                                                }
                                                className="flex items-center gap-1 hover:text-foreground"
                                            >
                                                Name{' '}
                                                <ArrowUpDown className="h-3.5 w-3.5" />
                                            </button>
                                        </TableHead>
                                        <TableHead className="font-semibold">
                                            <button
                                                onClick={() =>
                                                    handleSort('username')
                                                }
                                                className="flex items-center gap-1 hover:text-foreground"
                                            >
                                                Username{' '}
                                                <ArrowUpDown className="h-3.5 w-3.5" />
                                            </button>
                                        </TableHead>
                                        <TableHead className="font-semibold">
                                            <button
                                                onClick={() =>
                                                    handleSort('email')
                                                }
                                                className="flex items-center gap-1 hover:text-foreground"
                                            >
                                                Email{' '}
                                                <ArrowUpDown className="h-3.5 w-3.5" />
                                            </button>
                                        </TableHead>
                                        <TableHead className="font-semibold">
                                            <button
                                                onClick={() =>
                                                    handleSort('role')
                                                }
                                                className="flex items-center gap-1 hover:text-foreground"
                                            >
                                                Role{' '}
                                                <ArrowUpDown className="h-3.5 w-3.5" />
                                            </button>
                                        </TableHead>
                                        <TableHead className="w-[150px] pr-6 text-right font-semibold">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {paginatedUsers.length > 0 ? (
                                        paginatedUsers.map((user) => (
                                            <TableRow
                                                key={user.id}
                                                className="border-b border-border/30 transition-colors hover:bg-muted/10"
                                            >
                                                <TableCell className="py-4 pl-6 font-medium">
                                                    {user.name}
                                                    {currentUser &&
                                                        currentUser.id ===
                                                            user.id && (
                                                            <Badge
                                                                variant="outline"
                                                                className="ml-2 border-primary/20 bg-primary/10 text-primary"
                                                            >
                                                                You
                                                            </Badge>
                                                        )}
                                                </TableCell>
                                                <TableCell className="font-mono text-xs text-muted-foreground">
                                                    @{user.username}
                                                </TableCell>
                                                <TableCell className="text-sm">
                                                    <div className="flex items-center gap-1.5 text-muted-foreground">
                                                        <Mail className="h-3.5 w-3.5" />
                                                        <span>
                                                            {user.email}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={getRoleBadgeVariant(
                                                            user.role_id,
                                                        )}
                                                    >
                                                        {user.role?.label ||
                                                            'No Role'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="pr-6 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                handleOpenEdit(
                                                                    user,
                                                                )
                                                            }
                                                            className="h-8 w-8 hover:bg-primary/10 hover:text-primary"
                                                            title="Edit user"
                                                        >
                                                            <Edit2 className="h-4 w-4" />
                                                        </Button>
                                                        {currentUser &&
                                                        currentUser.id !==
                                                            user.id ? (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    handleOpenDelete(
                                                                        user,
                                                                    )
                                                                }
                                                                className="h-8 w-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                title="Delete user"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        ) : (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                disabled
                                                                className="h-8 w-8 text-muted-foreground/30"
                                                                title="Cannot delete your logged in account"
                                                            >
                                                                <Lock className="h-4 w-4" />
                                                            </Button>
                                                        )}
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
                                                No users found matching your
                                                search.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Pagination Footer */}
                        {sortedUsers.length > 0 && (
                            <div className="flex flex-col gap-4 border-t border-border/40 bg-muted/10 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="text-center text-xs text-muted-foreground sm:text-left">
                                    Showing{' '}
                                    <span className="font-semibold">
                                        {Math.min(
                                            (currentPage - 1) * pageSize + 1,
                                            sortedUsers.length,
                                        )}
                                    </span>{' '}
                                    to{' '}
                                    <span className="font-semibold">
                                        {Math.min(
                                            currentPage * pageSize,
                                            sortedUsers.length,
                                        )}
                                    </span>{' '}
                                    of{' '}
                                    <span className="font-semibold">
                                        {sortedUsers.length}
                                    </span>{' '}
                                    entries
                                </div>
                                <div className="flex items-center justify-center gap-1.5">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setCurrentPage((prev) =>
                                                Math.max(prev - 1, 1),
                                            )
                                        }
                                        disabled={currentPage === 1}
                                        className="h-8 border-border/50 bg-background/50 px-2 disabled:opacity-50"
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                        Previous
                                    </Button>

                                    {Array.from(
                                        { length: totalPages },
                                        (_, i) => i + 1,
                                    ).map((page) => (
                                        <Button
                                            key={page}
                                            variant={
                                                currentPage === page
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            onClick={() => setCurrentPage(page)}
                                            className="h-8 w-8 border-border/50 bg-background/50"
                                        >
                                            {page}
                                        </Button>
                                    ))}

                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setCurrentPage((prev) =>
                                                Math.min(prev + 1, totalPages),
                                            )
                                        }
                                        disabled={currentPage === totalPages}
                                        className="h-8 border-border/50 bg-background/50 px-2 disabled:opacity-50"
                                    >
                                        Next
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Create Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Add User Account</DialogTitle>
                        <DialogDescription>
                            Create a new user profile and assign operational
                            permissions in the database.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={handleCreateSubmit}
                        className="space-y-4 py-2"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="name">Full Name</Label>
                            <Input
                                id="name"
                                value={createForm.data.name}
                                onChange={(e) =>
                                    createForm.setData('name', e.target.value)
                                }
                                placeholder="e.g. John Doe"
                                className="bg-background/50"
                                required
                            />
                            <InputError message={createForm.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="username">Username</Label>
                            <Input
                                id="username"
                                value={createForm.data.username}
                                onChange={(e) =>
                                    createForm.setData(
                                        'username',
                                        e.target.value,
                                    )
                                }
                                placeholder="e.g. johndoe"
                                className="bg-background/50"
                                required
                            />
                            <InputError message={createForm.errors.username} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email Address</Label>
                            <Input
                                id="email"
                                type="email"
                                value={createForm.data.email}
                                onChange={(e) =>
                                    createForm.setData('email', e.target.value)
                                }
                                placeholder="e.g. john@example.com"
                                className="bg-background/50"
                                required
                            />
                            <InputError message={createForm.errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                value={createForm.data.password}
                                onChange={(e) =>
                                    createForm.setData(
                                        'password',
                                        e.target.value,
                                    )
                                }
                                placeholder="At least 8 characters"
                                className="bg-background/50"
                                required
                            />
                            <InputError message={createForm.errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="role_id">Assigned Role</Label>
                            <NativeSelect
                                id="role_id"
                                value={createForm.data.role_id}
                                onChange={(e) =>
                                    createForm.setData(
                                        'role_id',
                                        e.target.value,
                                    )
                                }
                                className="w-full bg-background/50"
                            >
                                {roles.map((role) => (
                                    <NativeSelectOption
                                        key={role.id}
                                        value={role.id.toString()}
                                    >
                                        {role.label} ({role.name})
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <InputError message={createForm.errors.role_id} />
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
                                    : 'Create Account'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Edit User Details</DialogTitle>
                        <DialogDescription>
                            Modify metadata settings and database role for the
                            selected account.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedUser && (
                        <form
                            onSubmit={handleEditSubmit}
                            className="space-y-4 py-2"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="edit-name">Full Name</Label>
                                <Input
                                    id="edit-name"
                                    value={editForm.data.name}
                                    onChange={(e) =>
                                        editForm.setData('name', e.target.value)
                                    }
                                    placeholder="Full name"
                                    className="bg-background/50"
                                    required
                                />
                                <InputError message={editForm.errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-username">Username</Label>
                                <Input
                                    id="edit-username"
                                    value={editForm.data.username}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'username',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Username"
                                    className="bg-background/50"
                                    required
                                />
                                <InputError
                                    message={editForm.errors.username}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-email">
                                    Email Address
                                </Label>
                                <Input
                                    id="edit-email"
                                    type="email"
                                    value={editForm.data.email}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'email',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Email address"
                                    className="bg-background/50"
                                    required
                                />
                                <InputError message={editForm.errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-password">
                                    Password (Leave blank to keep unchanged)
                                </Label>
                                <Input
                                    id="edit-password"
                                    type="password"
                                    value={editForm.data.password}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'password',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Enter new password if modifying"
                                    className="bg-background/50"
                                />
                                <InputError
                                    message={editForm.errors.password}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-role_id">
                                    Assigned Role
                                </Label>
                                <NativeSelect
                                    id="edit-role_id"
                                    value={editForm.data.role_id}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'role_id',
                                            e.target.value,
                                        )
                                    }
                                    className="w-full bg-background/50"
                                    disabled={
                                        currentUser &&
                                        currentUser.id === selectedUser.id
                                    }
                                >
                                    {roles.map((role) => (
                                        <NativeSelectOption
                                            key={role.id}
                                            value={role.id.toString()}
                                        >
                                            {role.label} ({role.name})
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                {currentUser &&
                                    currentUser.id === selectedUser.id && (
                                        <p className="mt-1 flex items-center gap-1 text-[10px] text-destructive">
                                            <Lock className="h-3 w-3" />
                                            You cannot change your own admin
                                            role.
                                        </p>
                                    )}
                                <InputError message={editForm.errors.role_id} />
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
                            Delete User Account
                        </DialogTitle>
                        <DialogDescription className="text-sm">
                            Are you absolutely sure you want to delete this
                            user? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedUser && (
                        <form
                            onSubmit={handleDeleteSubmit}
                            className="space-y-4 py-2"
                        >
                            <div className="space-y-1 rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-xs text-destructive">
                                <p className="font-semibold">
                                    User Details to Delete:
                                </p>
                                <p>
                                    Name: <strong>{selectedUser.name}</strong>
                                </p>
                                <p>
                                    Email:{' '}
                                    <strong className="font-mono">
                                        {selectedUser.email}
                                    </strong>
                                </p>
                                <p>
                                    Role:{' '}
                                    <strong>
                                        {selectedUser.role?.label || 'None'}
                                    </strong>
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

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Users Management',
            href: '/dashboard/users',
        },
    ],
};
