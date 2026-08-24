import { Head, useForm } from '@inertiajs/react';
import {
    Shield,
    Plus,
    Search,
    Edit2,
    Trash2,
    Lock,
    Users,
    Key,
    ShieldCheck,
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';

interface Role {
    id: number;
    name: string;
    label: string;
    description: string | null;
    users_count?: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    roles: Role[];
}

export default function RolesIndex({ roles }: Props) {
    const [searchTerm, setSearchTerm] = useState('');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [selectedRole, setSelectedRole] = useState<Role | null>(null);

    // Inertia form for create
    const createForm = useForm({
        name: '',
        label: '',
        description: '',
    });

    // Inertia form for edit
    const editForm = useForm({
        name: '',
        label: '',
        description: '',
    });

    // Inertia form for delete
    const deleteForm = useForm({});

    const protectedRoleIds = [1, 2, 3, 4];

    // Filter roles based on search query
    const filteredRoles = useMemo(() => {
        return roles.filter(
            (role) =>
                role.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                role.label.toLowerCase().includes(searchTerm.toLowerCase()) ||
                (role.description &&
                    role.description
                        .toLowerCase()
                        .includes(searchTerm.toLowerCase())),
        );
    }, [roles, searchTerm]);

    // Role stats
    const stats = useMemo(() => {
        const total = roles.length;
        const system = roles.filter((r) =>
            protectedRoleIds.includes(r.id),
        ).length;
        const custom = total - system;
        const totalUsers = roles.reduce(
            (sum, r) => sum + (r.users_count || 0),
            0,
        );

        return { total, system, custom, totalUsers };
    }, [roles]);

    const getRoleBadgeVariant = (name: string) => {
        switch (name) {
            case 'admin':
                return 'destructive';
            case 'lecturer':
                return 'info';
            case 'judge':
                return 'warning';
            case 'user':
                return 'secondary';
            default:
                return 'success';
        }
    };

    const handleOpenCreate = () => {
        createForm.reset();
        createForm.clearErrors();
        setIsCreateOpen(true);
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/dashboard/roles', {
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const handleOpenEdit = (role: Role) => {
        setSelectedRole(role);
        editForm.setData({
            name: role.name,
            label: role.label,
            description: role.description || '',
        });
        editForm.clearErrors();
        setIsEditOpen(true);
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedRole) {
return;
}

        editForm.put(`/dashboard/roles/${selectedRole.id}`, {
            onSuccess: () => {
                setIsEditOpen(false);
                setSelectedRole(null);
            },
        });
    };

    const handleOpenDelete = (role: Role) => {
        setSelectedRole(role);
        setIsDeleteOpen(true);
    };

    const handleDeleteSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedRole) {
return;
}

        deleteForm.delete(`/dashboard/roles/${selectedRole.id}`, {
            onSuccess: () => {
                setIsDeleteOpen(false);
                setSelectedRole(null);
            },
        });
    };

    return (
        <>
            <Head title="Roles Management" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                            <Shield className="h-8 w-8 text-primary" />
                            Roles Management
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage application access levels, assign
                            definitions, and configure user permissions.
                        </p>
                    </div>
                    <div>
                        <Button
                            onClick={handleOpenCreate}
                            className="w-full sm:w-auto"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add Custom Role
                        </Button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Total Roles
                            </CardTitle>
                            <Shield className="h-4 w-4 text-primary" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.total}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Configured access levels
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                System Roles
                            </CardTitle>
                            <Lock className="h-4 w-4 text-destructive" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.system}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Default immutable definitions
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Custom Roles
                            </CardTitle>
                            <Key className="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.custom}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Tenant-defined additions
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Assigned Users
                            </CardTitle>
                            <Users className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.totalUsers}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Active user assignments
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
                                    Configured Roles
                                </CardTitle>
                                <CardDescription>
                                    View, create, edit, or delete role
                                    definitions.
                                </CardDescription>
                            </div>
                            <div className="relative w-full sm:w-72">
                                <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search roles..."
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
                                        <TableHead className="w-[200px] py-3 pl-6 font-semibold">
                                            Role
                                        </TableHead>
                                        <TableHead className="w-[120px] font-semibold">
                                            Key Name
                                        </TableHead>
                                        <TableHead className="font-semibold">
                                            Description
                                        </TableHead>
                                        <TableHead className="w-[120px] text-center font-semibold">
                                            Users Count
                                        </TableHead>
                                        <TableHead className="w-[150px] pr-6 text-right font-semibold">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredRoles.length > 0 ? (
                                        filteredRoles.map((role) => {
                                            const isProtected =
                                                protectedRoleIds.includes(
                                                    role.id,
                                                );

                                            return (
                                                <TableRow
                                                    key={role.id}
                                                    className="border-b border-border/30 transition-colors hover:bg-muted/10"
                                                >
                                                    <TableCell className="py-4 pl-6 font-medium">
                                                        <div className="flex items-center gap-2">
                                                            <span>
                                                                {role.label}
                                                            </span>
                                                            <Badge
                                                                variant={getRoleBadgeVariant(
                                                                    role.name,
                                                                )}
                                                            >
                                                                {isProtected ? (
                                                                    <Lock className="mr-1 h-3 w-3" />
                                                                ) : (
                                                                    <ShieldCheck className="mr-1 h-3 w-3" />
                                                                )}
                                                                {role.name}
                                                            </Badge>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="font-mono text-xs text-muted-foreground">
                                                        {role.name}
                                                    </TableCell>
                                                    <TableCell className="text-muted-foregroundmax-w-xs truncate text-sm">
                                                        {role.description || (
                                                            <span className="text-muted-foreground/50 italic">
                                                                No description
                                                                provided
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-center text-sm font-semibold">
                                                        <Badge
                                                            variant="outline"
                                                            className="border-border/50 bg-background/50 px-2 py-0.5"
                                                        >
                                                            {role.users_count ??
                                                                0}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="pr-6 text-right">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    handleOpenEdit(
                                                                        role,
                                                                    )
                                                                }
                                                                className="h-8 w-8 hover:bg-primary/10 hover:text-primary"
                                                                title="Edit role"
                                                            >
                                                                <Edit2 className="h-4 w-4" />
                                                            </Button>
                                                            {!isProtected ? (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() =>
                                                                        handleOpenDelete(
                                                                            role,
                                                                        )
                                                                    }
                                                                    className="h-8 w-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                    title="Delete role"
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            ) : (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    disabled
                                                                    className="h-8 w-8 text-muted-foreground/30"
                                                                    title="System role (Locked)"
                                                                >
                                                                    <Lock className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })
                                    ) : (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="h-32 text-center text-muted-foreground"
                                            >
                                                No roles found matching your
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
                        <DialogTitle>Add Custom Role</DialogTitle>
                        <DialogDescription>
                            Create a new customized permission level. Roles map
                            user access across the site.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={handleCreateSubmit}
                        className="space-y-4 py-2"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="name">
                                Key Name (Technical ID)
                            </Label>
                            <Input
                                id="name"
                                value={createForm.data.name}
                                onChange={(e) =>
                                    createForm.setData('name', e.target.value)
                                }
                                placeholder="e.g. team_manager"
                                className="bg-background/50"
                                required
                            />
                            <p className="text-[10px] text-muted-foreground">
                                Lowercase, alphanumeric, and underscores only.
                                Cannot be changed later.
                            </p>
                            <InputError message={createForm.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="label">
                                Display Label (Human Readable)
                            </Label>
                            <Input
                                id="label"
                                value={createForm.data.label}
                                onChange={(e) =>
                                    createForm.setData('label', e.target.value)
                                }
                                placeholder="e.g. Team Manager"
                                className="bg-background/50"
                                required
                            />
                            <InputError message={createForm.errors.label} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={createForm.data.description}
                                onChange={(e) =>
                                    createForm.setData(
                                        'description',
                                        e.target.value,
                                    )
                                }
                                placeholder="Briefly describe what members of this role are allowed to do..."
                                className="h-20 resize-none bg-background/50"
                            />
                            <InputError
                                message={createForm.errors.description}
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
                                    : 'Create Role'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Edit Role</DialogTitle>
                        <DialogDescription>
                            Modify display characteristics for the chosen role
                            definition.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedRole && (
                        <form
                            onSubmit={handleEditSubmit}
                            className="space-y-4 py-2"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="edit-name">
                                    Key Name (Technical ID)
                                </Label>
                                <Input
                                    id="edit-name"
                                    value={editForm.data.name}
                                    onChange={(e) =>
                                        editForm.setData('name', e.target.value)
                                    }
                                    placeholder="e.g. administrator"
                                    disabled={protectedRoleIds.includes(
                                        selectedRole.id,
                                    )}
                                    className="bg-background/50 disabled:opacity-50"
                                    required
                                />
                                {protectedRoleIds.includes(selectedRole.id) && (
                                    <p className="mt-1 flex items-center gap-1 text-[10px] text-destructive">
                                        <Lock className="h-3 w-3" />
                                        This is a protected system role name and
                                        cannot be renamed.
                                    </p>
                                )}
                                <InputError message={editForm.errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-label">
                                    Display Label (Human Readable)
                                </Label>
                                <Input
                                    id="edit-label"
                                    value={editForm.data.label}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'label',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. Administrator"
                                    className="bg-background/50"
                                    required
                                />
                                <InputError message={editForm.errors.label} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-description">
                                    Description
                                </Label>
                                <Textarea
                                    id="edit-description"
                                    value={editForm.data.description}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Describe this role's purpose..."
                                    className="h-20 resize-none bg-background/50"
                                />
                                <InputError
                                    message={editForm.errors.description}
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
                            Delete Role Definition
                        </DialogTitle>
                        <DialogDescription className="text-sm">
                            Are you absolutely sure you want to delete this
                            role? This action is permanent and cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedRole && (
                        <form
                            onSubmit={handleDeleteSubmit}
                            className="space-y-4 py-2"
                        >
                            <div className="space-y-2 rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-xs text-destructive">
                                <p className="font-semibold">
                                    Details of Deletion:
                                </p>
                                <ul className="list-disc space-y-1 pl-4">
                                    <li>
                                        Role Name:{' '}
                                        <strong className="font-mono">
                                            {selectedRole.name}
                                        </strong>{' '}
                                        ({selectedRole.label})
                                    </li>
                                    <li>
                                        Users Affected:{' '}
                                        <strong>
                                            {selectedRole.users_count ?? 0}
                                        </strong>{' '}
                                        users currently assigned to this role
                                        will have their role set to NULL.
                                    </li>
                                </ul>
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

RolesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Roles Management',
            href: '/dashboard/roles',
        },
    ],
};
