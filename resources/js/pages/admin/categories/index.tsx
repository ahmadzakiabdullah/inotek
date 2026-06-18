import React, { useState, useMemo } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import InputError from '@/components/input-error';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Tag,
    Plus,
    Search,
    Edit2,
    Trash2,
    Calendar,
    AlertTriangle,
} from 'lucide-react';

interface CompetitionSession {
    id: number;
    name: string;
    is_active: boolean;
}

interface Category {
    id: number;
    session_id: number | null;
    code: string;
    name: string;
    allow_team: boolean;
    session?: CompetitionSession | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    categories: Category[];
    sessions: CompetitionSession[];
}

export default function CategoriesIndex({ categories, sessions }: Props) {
    const [searchTerm, setSearchTerm] = useState('');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState<Category | null>(
        null,
    );

    // Form for create
    const createForm = useForm({
        session_id: '',
        code: '',
        name: '',
        allow_team: false,
    });

    // Form for edit
    const editForm = useForm({
        session_id: '',
        code: '',
        name: '',
        allow_team: false,
    });

    // Form for delete
    const deleteForm = useForm({});

    // Filter categories based on search query
    const filteredCategories = useMemo(() => {
        return categories.filter(
            (category) =>
                category.name
                    .toLowerCase()
                    .includes(searchTerm.toLowerCase()) ||
                category.code
                    .toLowerCase()
                    .includes(searchTerm.toLowerCase()) ||
                (category.session &&
                    category.session.name
                        .toLowerCase()
                        .includes(searchTerm.toLowerCase())),
        );
    }, [categories, searchTerm]);

    const handleOpenCreate = () => {
        const activeSession = sessions.find((s) => s.is_active);
        createForm.setData({
            session_id: activeSession ? activeSession.id.toString() : '',
            code: '',
            name: '',
            allow_team: false,
        });
        createForm.clearErrors();
        setIsCreateOpen(true);
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/admin/categories', {
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const handleOpenEdit = (category: Category) => {
        setSelectedCategory(category);
        editForm.setData({
            session_id: category.session_id
                ? category.session_id.toString()
                : '',
            code: category.code,
            name: category.name,
            allow_team: category.allow_team ?? false,
        });
        editForm.clearErrors();
        setIsEditOpen(true);
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedCategory) return;

        editForm.put(`/admin/categories/${selectedCategory.id}`, {
            onSuccess: () => {
                setIsEditOpen(false);
                setSelectedCategory(null);
            },
        });
    };

    const handleOpenDelete = (category: Category) => {
        setSelectedCategory(category);
        setIsDeleteOpen(true);
    };

    const handleDeleteSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedCategory) return;

        deleteForm.delete(`/admin/categories/${selectedCategory.id}`, {
            onSuccess: () => {
                setIsDeleteOpen(false);
                setSelectedCategory(null);
            },
        });
    };

    return (
        <>
            <Head title="Categories Management" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                            <Tag className="h-8 w-8 text-primary" />
                            Categories Management
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Configure active evaluation categories (e.g. C1
                            Green Tech) scoped by competition sessions.
                        </p>
                    </div>
                    <div>
                        <Button
                            onClick={handleOpenCreate}
                            className="w-full sm:w-auto"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Create Category
                        </Button>
                    </div>
                </div>

                {/* Main Table Card */}
                <Card className="overflow-hidden border border-border/50 bg-card/40 backdrop-blur-sm">
                    <CardHeader className="border-b border-border/40 p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="text-lg font-semibold">
                                    Configured Categories
                                </CardTitle>
                                <CardDescription>
                                    List and edit dynamic categories.
                                </CardDescription>
                            </div>
                            <div className="relative w-full sm:w-72">
                                <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search categories..."
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
                                        <TableHead className="w-[120px] py-3 pl-6 font-semibold">
                                            Code
                                        </TableHead>
                                        <TableHead className="font-semibold">
                                            Category Name
                                        </TableHead>
                                        <TableHead className="font-semibold">
                                            Assigned Session
                                        </TableHead>
                                        <TableHead className="font-semibold">
                                            Team Mode
                                        </TableHead>
                                        <TableHead className="w-[150px] pr-6 text-right font-semibold">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredCategories.length > 0 ? (
                                        filteredCategories.map((category) => (
                                            <TableRow
                                                key={category.id}
                                                className="border-b border-border/30 transition-colors hover:bg-muted/10"
                                            >
                                                <TableCell className="py-4 pl-6">
                                                    <Badge
                                                        variant="outline"
                                                        className="border-primary/20 bg-primary/5 px-2.5 py-0.5 font-mono font-bold text-primary"
                                                    >
                                                        {category.code}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {category.name}
                                                </TableCell>
                                                <TableCell className="text-sm text-muted-foreground">
                                                    {category.session ? (
                                                        <span className="flex items-center gap-1.5">
                                                            <Calendar className="h-3.5 w-3.5 text-muted-foreground/60" />
                                                            {
                                                                category.session
                                                                    .name
                                                            }
                                                            {category.session
                                                                .is_active && (
                                                                <Badge
                                                                    variant="success"
                                                                    className="h-4 px-1 py-0 text-[9px]"
                                                                >
                                                                    Active
                                                                </Badge>
                                                            )}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground/50 italic">
                                                            Global / No Session
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {category.allow_team ? (
                                                        <Badge
                                                            variant="success"
                                                            className="border-emerald-500/20 bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20"
                                                        >
                                                            Group Allowed
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            variant="secondary"
                                                            className="border-transparent bg-muted/50 text-muted-foreground"
                                                        >
                                                            Individual Only
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
                                                                    category,
                                                                )
                                                            }
                                                            className="h-8 w-8 hover:bg-primary/10 hover:text-primary"
                                                            title="Edit category"
                                                        >
                                                            <Edit2 className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                handleOpenDelete(
                                                                    category,
                                                                )
                                                            }
                                                            className="h-8 w-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                            title="Delete category"
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
                                                No categories found matching
                                                your search.
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
                        <DialogTitle>Create Category</DialogTitle>
                        <DialogDescription>
                            Create a new project category.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={handleCreateSubmit}
                        className="space-y-4 py-2"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="session_id">
                                Competition Session
                            </Label>
                            <Select
                                value={createForm.data.session_id}
                                onValueChange={(val) =>
                                    createForm.setData('session_id', val)
                                }
                            >
                                <SelectTrigger className="bg-background/50">
                                    <SelectValue placeholder="Select session" />
                                </SelectTrigger>
                                <SelectContent>
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
                            <InputError
                                message={createForm.errors.session_id}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="code">Category Code</Label>
                            <Input
                                id="code"
                                value={createForm.data.code}
                                onChange={(e) =>
                                    createForm.setData('code', e.target.value)
                                }
                                placeholder="e.g. C1"
                                className="bg-background/50"
                                required
                            />
                            <InputError message={createForm.errors.code} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="name">Category Name</Label>
                            <Input
                                id="name"
                                value={createForm.data.name}
                                onChange={(e) =>
                                    createForm.setData('name', e.target.value)
                                }
                                placeholder="e.g. Green Technology"
                                className="bg-background/50"
                                required
                            />
                            <InputError message={createForm.errors.name} />
                        </div>

                        <div className="flex items-center justify-between rounded-lg border border-border/40 bg-muted/10 p-3">
                            <div className="space-y-0.5">
                                <Label htmlFor="allow_team">
                                    Allow Team Members
                                </Label>
                                <p className="text-[11px] text-muted-foreground">
                                    Allow students/participants to register
                                    multiple team members.
                                </p>
                            </div>
                            <Switch
                                id="allow_team"
                                checked={createForm.data.allow_team}
                                onCheckedChange={(checked) =>
                                    createForm.setData('allow_team', checked)
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
                                    : 'Create Category'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Edit Category</DialogTitle>
                        <DialogDescription>
                            Modify category parameters.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedCategory && (
                        <form
                            onSubmit={handleEditSubmit}
                            className="space-y-4 py-2"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="edit-session_id">
                                    Competition Session
                                </Label>
                                <Select
                                    value={editForm.data.session_id}
                                    onValueChange={(val) =>
                                        editForm.setData('session_id', val)
                                    }
                                >
                                    <SelectTrigger className="bg-background/50">
                                        <SelectValue placeholder="Select session" />
                                    </SelectTrigger>
                                    <SelectContent>
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
                                <InputError
                                    message={editForm.errors.session_id}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="edit-code">Category Code</Label>
                                <Input
                                    id="edit-code"
                                    value={editForm.data.code}
                                    onChange={(e) =>
                                        editForm.setData('code', e.target.value)
                                    }
                                    placeholder="e.g. C1"
                                    className="bg-background/50"
                                    required
                                />
                                <InputError message={editForm.errors.code} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="edit-name">Category Name</Label>
                                <Input
                                    id="edit-name"
                                    value={editForm.data.name}
                                    onChange={(e) =>
                                        editForm.setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Green Technology"
                                    className="bg-background/50"
                                    required
                                />
                                <InputError message={editForm.errors.name} />
                            </div>

                            <div className="flex items-center justify-between rounded-lg border border-border/40 bg-muted/10 p-3">
                                <div className="space-y-0.5">
                                    <Label htmlFor="edit-allow_team">
                                        Allow Team Members
                                    </Label>
                                    <p className="text-[11px] text-muted-foreground">
                                        Allow students/participants to register
                                        multiple team members.
                                    </p>
                                </div>
                                <Switch
                                    id="edit-allow_team"
                                    checked={editForm.data.allow_team}
                                    onCheckedChange={(checked) =>
                                        editForm.setData('allow_team', checked)
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
                            Delete Category
                        </DialogTitle>
                        <DialogDescription className="text-sm">
                            Are you sure you want to delete this category? This
                            action is permanent and cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedCategory && (
                        <form
                            onSubmit={handleDeleteSubmit}
                            className="space-y-4 py-2"
                        >
                            <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-xs text-destructive">
                                <p className="font-semibold">
                                    Category to delete:
                                </p>
                                <p className="mt-1 font-mono font-bold">
                                    [{selectedCategory.code}]{' '}
                                    {selectedCategory.name}
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

CategoriesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Categories Management',
            href: '/admin/categories',
        },
    ],
};
