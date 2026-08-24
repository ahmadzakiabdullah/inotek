import { Head, useForm } from '@inertiajs/react';
import {
    ClipboardList,
    Plus,
    Search,
    Edit2,
    Trash2,
    Check,
    X,
    Percent,
    AlertCircle,
    AlertTriangle,
    Trash,
    Layers,
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';

interface CompetitionSession {
    id: number;
    name: string;
}

interface Category {
    id: number;
    code: string;
    name: string;
    session?: CompetitionSession | null;
}

interface RubricItem {
    id?: number;
    criteria_name: string;
    weight: number | string;
    max_points: number;
    section?: string | null;
    code?: string | null;
    description?: string | null;
    scale_descriptions?: Record<number, string> | null;
}

interface Rubric {
    id: number;
    name: string;
    description: string | null;
    items: RubricItem[];
    categories: Category[];
    created_at: string;
    updated_at: string;
}

interface Props {
    rubrics: Rubric[];
    categories: Category[];
}

export default function RubricsIndex({ rubrics, categories }: Props) {
    const [searchTerm, setSearchTerm] = useState('');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [selectedRubric, setSelectedRubric] = useState<Rubric | null>(null);

    // Modal state for editing detailed criteria
    const [isCriteriaEditOpen, setIsCriteriaEditOpen] = useState(false);
    const [editingCriteriaIndex, setEditingCriteriaIndex] = useState<number | null>(null);
    const [editingCriteriaForm, setEditingCriteriaForm] = useState<'create' | 'edit' | null>(null);
    const [criteriaEditData, setCriteriaEditData] = useState<{
        section: string;
        code: string;
        criteria_name: string;
        description: string;
        weight: string | number;
        max_points: number;
        scale_descriptions: Record<number, string>;
    }>({
        section: '',
        code: '',
        criteria_name: '',
        description: '',
        weight: '',
        max_points: 5,
        scale_descriptions: { 0: '', 1: '', 2: '', 3: '', 4: '', 5: '' },
    });

    // Form for create
    const createForm = useForm({
        name: '',
        description: '',
        items: [] as RubricItem[],
        category_ids: [] as number[],
    });

    // Form for edit
    const editForm = useForm({
        name: '',
        description: '',
        items: [] as RubricItem[],
        category_ids: [] as number[],
    });

    // Form for delete
    const deleteForm = useForm({});

    // Filter rubrics based on search query
    const filteredRubrics = useMemo(() => {
        return rubrics.filter(
            (rubric) =>
                rubric.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                (rubric.description &&
                    rubric.description
                        .toLowerCase()
                        .includes(searchTerm.toLowerCase())),
        );
    }, [rubrics, searchTerm]);

    const handleOpenCreate = () => {
        createForm.setData({
            name: '',
            description: '',
            items: [
                {
                    criteria_name: 'Project Functionality',
                    weight: 0.3,
                    max_points: 5,
                    section: 'A',
                    code: 'A1',
                    description: 'Grading Scale: [LO2, PO3]',
                    scale_descriptions: {
                        0: 'No effort to show functionality at all',
                        1: 'Not functioning but showed video recording',
                        2: 'Demonstrated but not functioning properly',
                        3: 'Demonstrated the functionality but not as expected',
                        4: 'Demonstrated the functionality up to the expectation',
                        5: 'Demonstrated the functionality beyond the expectation',
                    },
                },
                {
                    criteria_name: 'Project Innovation and Quality',
                    weight: 0.3,
                    max_points: 5,
                    section: 'A',
                    code: 'A2',
                    description: 'Grading Scale: [LO8, PO10] EA2',
                    scale_descriptions: {
                        0: 'Poor/No innovation',
                        1: 'Very basic innovation',
                        2: 'Moderate quality and innovation',
                        3: 'Good innovation with room for improvement',
                        4: 'High quality innovation',
                        5: 'Outstanding and highly advanced innovation',
                    },
                },
                {
                    criteria_name: 'Sustainability, Social and Environmental Impact',
                    weight: 0.1,
                    max_points: 5,
                    section: 'A',
                    code: 'A3',
                    description: 'Grading Scale: [LO6, PO7] EA4',
                    scale_descriptions: { 0: '', 1: '', 2: '', 3: '', 4: '', 5: '' },
                },
                {
                    criteria_name: 'Ability to Address Social Design Criteria',
                    weight: 0.1,
                    max_points: 5,
                    section: 'A',
                    code: 'A4',
                    description: 'Grading Scale: [LO5, PO6]',
                    scale_descriptions: { 0: '', 1: '', 2: '', 3: '', 4: '', 5: '' },
                },
                {
                    criteria_name: 'Potential Application',
                    weight: 0.05,
                    max_points: 5,
                    section: 'A',
                    code: 'A5',
                    description: 'Grading Scale: [LO6, PO7]',
                    scale_descriptions: { 0: '', 1: '', 2: '', 3: '', 4: '', 5: '' },
                },
                {
                    criteria_name: 'Pitching Skill',
                    weight: 0.1,
                    max_points: 5,
                    section: 'B',
                    code: 'B1',
                    description: 'Presenter Appearance & Professionalism',
                    scale_descriptions: { 0: '', 1: '', 2: '', 3: '', 4: '', 5: '' },
                },
                {
                    criteria_name: 'Project Poster / Material',
                    weight: 0.05,
                    max_points: 5,
                    section: 'B',
                    code: 'B2',
                    description: 'Evaluate the quality and clarity of the project poster/materials.',
                    scale_descriptions: { 0: '', 1: '', 2: '', 3: '', 4: '', 5: '' },
                },
                {
                    criteria_name: 'Bonus',
                    weight: 0.05,
                    max_points: 5,
                    section: 'C',
                    code: 'C1',
                    description: 'Marketability or Ready to Be Commercialized',
                    scale_descriptions: { 0: '', 1: '', 2: '', 3: '', 4: '', 5: '' },
                },
            ],
            category_ids: [],
        });
        createForm.clearErrors();
        setIsCreateOpen(true);
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const formattedItems = createForm.data.items.map((item) => ({
            ...item,
            weight: parseFloat(item.weight.toString()),
        }));

        createForm.transform((data) => ({
            ...data,
            items: formattedItems,
        }));

        createForm.post('/dashboard/rubrics', {
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const handleOpenEdit = (rubric: Rubric) => {
        setSelectedRubric(rubric);
        editForm.setData({
            name: rubric.name,
            description: rubric.description || '',
            items: rubric.items.map((item) => ({
                criteria_name: item.criteria_name,
                weight: parseFloat(item.weight.toString()),
                max_points: item.max_points,
                section: item.section || '',
                code: item.code || '',
                description: item.description || '',
                scale_descriptions: item.scale_descriptions || { 0: '', 1: '', 2: '', 3: '', 4: '', 5: '' },
            })),
            category_ids: rubric.categories.map((c) => c.id),
        });
        editForm.clearErrors();
        setIsEditOpen(true);
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedRubric) {
return;
}

        const formattedItems = editForm.data.items.map((item) => ({
            ...item,
            weight: parseFloat(item.weight.toString()),
        }));

        editForm.transform((data) => ({
            ...data,
            items: formattedItems,
        }));

        editForm.put(`/dashboard/rubrics/${selectedRubric.id}`, {
            onSuccess: () => {
                setIsEditOpen(false);
                setSelectedRubric(null);
            },
        });
    };

    const handleOpenDelete = (rubric: Rubric) => {
        setSelectedRubric(rubric);
        setIsDeleteOpen(true);
    };

    const handleDeleteSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedRubric) {
return;
}

        deleteForm.delete(`/dashboard/rubrics/${selectedRubric.id}`, {
            onSuccess: () => {
                setIsDeleteOpen(false);
                setSelectedRubric(null);
            },
        });
    };

    // Helper to calculate total weight for form items
    const getWeightsSum = (items: RubricItem[]) => {
        return items.reduce((sum, item) => {
            const val = parseFloat(item.weight.toString());

            return sum + (isNaN(val) ? 0 : val);
        }, 0);
    };

    const createWeightsSum = useMemo(
        () => getWeightsSum(createForm.data.items),
        [createForm.data.items],
    );
    const editWeightsSum = useMemo(
        () => getWeightsSum(editForm.data.items),
        [editForm.data.items],
    );

    // Opening criteria detail modal helper
    const handleOpenCriteriaEdit = (index: number, formType: 'create' | 'edit') => {
        setEditingCriteriaIndex(index);
        setEditingCriteriaForm(formType);
        
        const form = formType === 'create' ? createForm : editForm;
        const item = form.data.items[index];
        
        // Weight is decimal in form (e.g. 0.30), show as percentage in modal (e.g. 30)
        const displayWeight = parseFloat((item.weight || 0).toString()) * 100;
        
        setCriteriaEditData({
            section: item.section || '',
            code: item.code || '',
            criteria_name: item.criteria_name || '',
            description: item.description || '',
            weight: isNaN(displayWeight) ? 0 : displayWeight,
            max_points: item.max_points || 5,
            scale_descriptions: {
                0: item.scale_descriptions?.[0] || '',
                1: item.scale_descriptions?.[1] || '',
                2: item.scale_descriptions?.[2] || '',
                3: item.scale_descriptions?.[3] || '',
                4: item.scale_descriptions?.[4] || '',
                5: item.scale_descriptions?.[5] || '',
            }
        });
        
        setIsCriteriaEditOpen(true);
    };

    const handleSaveCriteriaEdit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingCriteriaIndex === null || !editingCriteriaForm) {
return;
}

        const form = editingCriteriaForm === 'create' ? createForm : editForm;
        const currentItems = [...form.data.items];
        
        // Convert weight back to decimal (e.g. 30 -> 0.30)
        const decimalWeight = parseFloat(criteriaEditData.weight.toString()) / 100;

        currentItems[editingCriteriaIndex] = {
            ...currentItems[editingCriteriaIndex],
            section: criteriaEditData.section,
            code: criteriaEditData.code,
            criteria_name: criteriaEditData.criteria_name,
            description: criteriaEditData.description,
            weight: isNaN(decimalWeight) ? 0 : decimalWeight,
            max_points: criteriaEditData.max_points,
            scale_descriptions: criteriaEditData.scale_descriptions,
        };

        (form as any).setData('items', currentItems);
        setIsCriteriaEditOpen(false);
    };

    // Helper to handle items management
    const addFormItem = (form: typeof createForm | typeof editForm) => {
        const currentItems = [...form.data.items];
        currentItems.push({
            criteria_name: '',
            weight: 0.05,
            max_points: 5,
            section: '',
            code: '',
            description: '',
            scale_descriptions: { 0: '', 1: '', 2: '', 3: '', 4: '', 5: '' }
        });
        (form as any).setData('items', currentItems);
        
        // Open the criteria modal immediately for the newly added item
        const formType = form === createForm ? 'create' : 'edit';
        handleOpenCriteriaEdit(currentItems.length - 1, formType);
    };

    const removeFormItem = (
        form: typeof createForm | typeof editForm,
        index: number,
    ) => {
        const currentItems = [...form.data.items];
        currentItems.splice(index, 1);
        (form as any).setData('items', currentItems);
    };

    const toggleCategoryCheckbox = (
        form: typeof createForm | typeof editForm,
        categoryId: number,
        checked: boolean,
    ) => {
        const currentIds = [...form.data.category_ids];

        if (checked) {
            currentIds.push(categoryId);
        } else {
            const idx = currentIds.indexOf(categoryId);

            if (idx > -1) {
currentIds.splice(idx, 1);
}
        }

        (form as any).setData('category_ids', currentIds);
    };

    return (
        <>
            <Head title="Rubrics Management" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                            <ClipboardList className="h-8 w-8 text-primary" />
                            Rubrics Management
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Configure evaluation criteria weightages and bind
                            rubrics to active competition categories.
                        </p>
                    </div>
                    <div>
                        <Button
                            onClick={handleOpenCreate}
                            className="w-full sm:w-auto"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Create Rubric
                        </Button>
                    </div>
                </div>

                {/* Main Table Card */}
                <Card className="overflow-hidden border border-border/50 bg-card/40 backdrop-blur-sm">
                    <CardHeader className="border-b border-border/40 p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle className="text-lg font-semibold">
                                    Configured Rubrics
                                </CardTitle>
                                <CardDescription>
                                    View and map dynamic scoring parameters.
                                </CardDescription>
                            </div>
                            <div className="relative w-full sm:w-72">
                                <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search rubrics..."
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
                                        <TableHead className="w-[250px] py-3 pl-6 font-semibold">
                                            Rubric Details
                                        </TableHead>
                                        <TableHead className="font-semibold">
                                            Criteria breakdown
                                        </TableHead>
                                        <TableHead className="w-[200px] font-semibold">
                                            Assigned Categories
                                        </TableHead>
                                        <TableHead className="w-[120px] pr-6 text-right font-semibold">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredRubrics.length > 0 ? (
                                        filteredRubrics.map((rubric) => (
                                            <TableRow
                                                key={rubric.id}
                                                className="border-b border-border/30 transition-colors hover:bg-muted/10"
                                            >
                                                <TableCell className="py-4 pl-6 align-top w-[250px] max-w-[250px]">
                                                    <div className="text-base font-semibold break-words whitespace-normal">
                                                        {rubric.name}
                                                    </div>
                                                    <div className="mt-1 text-xs text-muted-foreground break-words whitespace-pre-wrap leading-relaxed">
                                                        {rubric.description || (
                                                            <span className="italic opacity-60">
                                                                No description
                                                            </span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="py-4 align-top">
                                                    <div className="max-w-lg space-y-2">
                                                        {rubric.items.map(
                                                            (item, idx) => (
                                                                <div
                                                                    key={idx}
                                                                    className="flex flex-col gap-1 border-b border-border/30 pb-2 last:border-0 last:pb-0"
                                                                >
                                                                    <div className="flex items-start justify-between gap-4 text-xs">
                                                                        <span className="font-semibold text-foreground break-words whitespace-normal flex items-center gap-1.5">
                                                                            {item.section && item.code ? (
                                                                                <Badge
                                                                                    variant="outline"
                                                                                    className="px-1 py-0 h-4 font-mono text-[9px] bg-muted/65 text-muted-foreground border-border/40"
                                                                                >
                                                                                    {item.section}{item.code}
                                                                                </Badge>
                                                                            ) : null}
                                                                            {item.criteria_name}
                                                                        </span>
                                                                        <span className="flex items-center gap-2 font-mono text-muted-foreground shrink-0 mt-0.5">
                                                                            <span>
                                                                                Max:{' '}
                                                                                {
                                                                                    item.max_points
                                                                                }{' '}
                                                                                pts
                                                                            </span>
                                                                            <Badge
                                                                                variant="outline"
                                                                                className="border-primary/20 bg-primary/5 px-1 py-0 text-[10px] text-primary"
                                                                            >
                                                                                {(
                                                                                    parseFloat(
                                                                                        item.weight.toString(),
                                                                                    ) *
                                                                                    100
                                                                                ).toFixed(
                                                                                    0,
                                                                                )}
                                                                                %
                                                                            </Badge>
                                                                        </span>
                                                                    </div>
                                                                    {item.description && (
                                                                        <div className="text-[10px] text-muted-foreground italic pl-1 leading-relaxed">
                                                                            {item.description}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="py-4 align-top text-sm">
                                                    <div className="flex max-w-[200px] flex-wrap gap-1">
                                                        {rubric.categories &&
                                                        rubric.categories
                                                            .length > 0 ? (
                                                            rubric.categories.map(
                                                                (cat) => (
                                                                    <Badge
                                                                        key={
                                                                            cat.id
                                                                        }
                                                                        variant="secondary"
                                                                        className="gap-1 border-border/50 bg-secondary/80 py-0 text-[10px] text-secondary-foreground"
                                                                    >
                                                                        <Layers className="h-3 w-3 opacity-60" />
                                                                        {
                                                                            cat.code
                                                                        }
                                                                    </Badge>
                                                                ),
                                                            )
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground/50 italic">
                                                                Unassigned
                                                            </span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="py-4 pr-6 text-right align-top">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                handleOpenEdit(
                                                                    rubric,
                                                                )
                                                            }
                                                            className="h-8 w-8 hover:bg-primary/10 hover:text-primary"
                                                            title="Edit rubric"
                                                        >
                                                            <Edit2 className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                handleOpenDelete(
                                                                    rubric,
                                                                )
                                                            }
                                                            className="h-8 w-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                            title="Delete rubric"
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
                                                colSpan={4}
                                                className="h-32 text-center text-muted-foreground"
                                            >
                                                No rubrics found matching your
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
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-[620px]">
                    <DialogHeader>
                        <DialogTitle>Create Rubric</DialogTitle>
                        <DialogDescription>
                            Define assessment criteria and map them to project
                            categories.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={handleCreateSubmit}
                        className="space-y-4 py-2"
                    >
                        {createWeightsSum !== 1.0 && (
                            <div className="flex items-start gap-2.5 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-amber-700 text-xs">
                                <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5 text-amber-500" />
                                <div>
                                    <span className="font-semibold">Weight Notice:</span> Total rubric weight is {(createWeightsSum * 100).toFixed(2)}%. Please review criteria weights so the total is 100%.
                                </div>
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="name">Rubric Name</Label>
                            <Input
                                id="name"
                                value={createForm.data.name}
                                onChange={(e) =>
                                    createForm.setData('name', e.target.value)
                                }
                                placeholder="e.g. Standard Engineering Rubric"
                                className="bg-background/50"
                                required
                            />
                            <InputError message={createForm.errors.name} />
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
                                placeholder="Brief summary of where this rubric is applied..."
                                className="h-16 resize-none bg-background/50"
                            />
                            <InputError
                                message={createForm.errors.description}
                            />
                        </div>

                        {/* Criteria Items Table */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label className="text-sm font-semibold">
                                    Scoring Criteria & Weightage
                                </Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => addFormItem(createForm)}
                                    className="h-8"
                                >
                                    <Plus className="mr-1 h-3.5 w-3.5" />
                                    Add Criteria
                                </Button>
                            </div>

                            <div className="overflow-hidden rounded-lg border border-border/50 bg-background/30">
                                <Table>
                                    <TableHeader className="bg-muted/30">
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead className="w-[60px] py-2">Sec</TableHead>
                                            <TableHead className="w-[80px] py-2">Code</TableHead>
                                            <TableHead className="py-2">Label / Criteria</TableHead>
                                            <TableHead className="w-[90px] py-2 font-mono">Weight</TableHead>
                                            <TableHead className="w-[90px] py-2 text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {createForm.data.items.length > 0 ? (
                                            createForm.data.items.map((item, idx) => (
                                                <TableRow
                                                    key={idx}
                                                    className="hover:bg-transparent border-b border-border/20 last:border-0"
                                                >
                                                    <TableCell className="py-2 font-semibold font-mono text-xs text-muted-foreground">
                                                        {item.section || <span className="opacity-30">-</span>}
                                                    </TableCell>
                                                    <TableCell className="py-2 font-semibold font-mono text-xs">
                                                        {item.code || <span className="opacity-30">-</span>}
                                                    </TableCell>
                                                    <TableCell className="py-2">
                                                        <div className="text-xs font-semibold break-all leading-snug">
                                                            {item.criteria_name || (
                                                                <span className="italic text-muted-foreground/50">Unnamed criteria</span>
                                                            )}
                                                        </div>
                                                        {item.description && (
                                                            <div className="text-[10px] text-muted-foreground truncate max-w-[220px]">
                                                                {item.description}
                                                            </div>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="py-2 font-mono text-xs">
                                                        {(parseFloat((item.weight || 0).toString()) * 100).toFixed(0)}%
                                                    </TableCell>
                                                    <TableCell className="py-2 text-right">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() => handleOpenCriteriaEdit(idx, 'create')}
                                                                className="h-8 w-8 text-primary hover:bg-primary/10 hover:text-primary"
                                                                title="Edit details"
                                                            >
                                                                <Edit2 className="h-4 w-4" />
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() => removeFormItem(createForm, idx)}
                                                                className="h-8 w-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                disabled={createForm.data.items.length <= 1}
                                                            >
                                                                <Trash className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        ) : (
                                            <TableRow>
                                                <TableCell colSpan={5} className="text-center py-4 text-xs text-muted-foreground">
                                                    No criteria added yet.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>

                                {/* Weights Sum Summary */}
                                <div className="flex items-center justify-between border-t border-border/40 bg-muted/10 p-3">
                                    <span className="text-xs font-semibold text-muted-foreground">
                                        Total Weight Sum:
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <span className="font-mono text-sm font-bold">
                                            {(createWeightsSum * 100).toFixed(2)}%
                                        </span>
                                        {Math.abs(createWeightsSum - 1.0) < 0.0001 ? (
                                            <Badge
                                                variant="success"
                                                className="h-5 border-emerald-500/20 bg-emerald-500/10 px-1 text-[10px] text-emerald-500"
                                            >
                                                <Check className="mr-0.5 h-3 w-3" /> Ready
                                            </Badge>
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className="h-5 border-amber-500/20 bg-amber-500/10 px-1 text-[10px] text-amber-600"
                                            >
                                                <AlertCircle className="mr-0.5 h-3 w-3" /> Not 100%
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            </div>
                            <InputError message={createForm.errors.items} />
                        </div>

                        {/* Category Mapping checkboxes */}
                        <div className="space-y-2">
                            <Label className="text-sm font-semibold">
                                Assign to Categories
                            </Label>
                            <div className="grid max-h-[140px] grid-cols-2 gap-3 overflow-y-auto rounded-lg border border-border/50 bg-background/30 p-3">
                                {categories.length > 0 ? (
                                    categories.map((cat) => (
                                        <div
                                            key={cat.id}
                                            className="flex items-center space-x-2"
                                        >
                                            <Checkbox
                                                id={`create-cat-${cat.id}`}
                                                checked={createForm.data.category_ids.includes(
                                                    cat.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggleCategoryCheckbox(
                                                        createForm,
                                                        cat.id,
                                                        !!checked,
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor={`create-cat-${cat.id}`}
                                                className="cursor-pointer truncate text-xs select-none"
                                            >
                                                <span className="mr-1 font-mono font-bold">
                                                    [{cat.code}]
                                                </span>
                                                {cat.name}
                                                {cat.session && (
                                                    <span className="ml-1 text-[10px] opacity-50">
                                                        ({cat.session.name})
                                                    </span>
                                                )}
                                            </Label>
                                        </div>
                                    ))
                                ) : (
                                    <p className="col-span-2 text-xs text-muted-foreground italic">
                                        No categories defined. Configure
                                        categories first.
                                    </p>
                                )}
                            </div>
                            <InputError
                                message={createForm.errors.category_ids}
                            />
                        </div>

                        <DialogFooter className="border-t border-border/30 pt-4">
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
                                    : 'Create Rubric'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-[620px]">
                    <DialogHeader>
                        <DialogTitle>Edit Rubric</DialogTitle>
                        <DialogDescription>
                            Modify rubric details and mapping assignments.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedRubric && (
                        <form
                            onSubmit={handleEditSubmit}
                            className="space-y-4 py-2"
                        >
                            {editWeightsSum !== 1.0 && (
                                <div className="flex items-start gap-2.5 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-amber-700 text-xs">
                                    <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5 text-amber-500" />
                                    <div>
                                        <span className="font-semibold">Weight Notice:</span> Total rubric weight is {(editWeightsSum * 100).toFixed(2)}%. Please review criteria weights so the total is 100%.
                                    </div>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="edit-name">Rubric Name</Label>
                                <Input
                                    id="edit-name"
                                    value={editForm.data.name}
                                    onChange={(e) =>
                                        editForm.setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Standard Engineering Rubric"
                                    className="bg-background/50"
                                    required
                                />
                                <InputError message={editForm.errors.name} />
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
                                    placeholder="Brief summary..."
                                    className="h-16 resize-none bg-background/50"
                                />
                                <InputError
                                    message={editForm.errors.description}
                                />
                            </div>

                            {/* Criteria Items Table */}
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <Label className="text-sm font-semibold">
                                        Scoring Criteria & Weightage
                                    </Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => addFormItem(editForm)}
                                        className="h-8"
                                    >
                                        <Plus className="mr-1 h-3.5 w-3.5" />
                                        Add Criteria
                                    </Button>
                                </div>

                                <div className="overflow-hidden rounded-lg border border-border/50 bg-background/30">
                                    <Table>
                                        <TableHeader className="bg-muted/30">
                                            <TableRow className="hover:bg-transparent">
                                                <TableHead className="w-[60px] py-2">Sec</TableHead>
                                                <TableHead className="w-[80px] py-2">Code</TableHead>
                                                <TableHead className="py-2">Label / Criteria</TableHead>
                                                <TableHead className="w-[90px] py-2 font-mono">Weight</TableHead>
                                                <TableHead className="w-[90px] py-2 text-right">Actions</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {editForm.data.items.length > 0 ? (
                                                editForm.data.items.map((item, idx) => (
                                                    <TableRow
                                                        key={idx}
                                                        className="hover:bg-transparent border-b border-border/20 last:border-0"
                                                    >
                                                        <TableCell className="py-2 font-semibold font-mono text-xs text-muted-foreground">
                                                            {item.section || <span className="opacity-30">-</span>}
                                                        </TableCell>
                                                        <TableCell className="py-2 font-semibold font-mono text-xs">
                                                            {item.code || <span className="opacity-30">-</span>}
                                                        </TableCell>
                                                        <TableCell className="py-2">
                                                            <div className="text-xs font-semibold break-all leading-snug">
                                                                {item.criteria_name || (
                                                                    <span className="italic text-muted-foreground/50">Unnamed criteria</span>
                                                                )}
                                                            </div>
                                                            {item.description && (
                                                                <div className="text-[10px] text-muted-foreground truncate max-w-[220px]">
                                                                    {item.description}
                                                                </div>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="py-2 font-mono text-xs">
                                                            {(parseFloat((item.weight || 0).toString()) * 100).toFixed(0)}%
                                                        </TableCell>
                                                        <TableCell className="py-2 text-right">
                                                            <div className="flex items-center justify-end gap-1">
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() => handleOpenCriteriaEdit(idx, 'edit')}
                                                                    className="h-8 w-8 text-primary hover:bg-primary/10 hover:text-primary"
                                                                    title="Edit details"
                                                                >
                                                                    <Edit2 className="h-4 w-4" />
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() => removeFormItem(editForm, idx)}
                                                                    className="h-8 w-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                    disabled={editForm.data.items.length <= 1}
                                                                >
                                                                    <Trash className="h-4 w-4" />
                                                                </Button>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                ))
                                            ) : (
                                                <TableRow>
                                                    <TableCell colSpan={5} className="text-center py-4 text-xs text-muted-foreground">
                                                        No criteria added yet.
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </TableBody>
                                    </Table>

                                    {/* Weights Sum Summary */}
                                    <div className="flex items-center justify-between border-t border-border/40 bg-muted/10 p-3">
                                        <span className="text-xs font-semibold text-muted-foreground">
                                            Total Weight Sum:
                                        </span>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono text-sm font-bold">
                                                {(editWeightsSum * 100).toFixed(2)}%
                                            </span>
                                            {Math.abs(editWeightsSum - 1.0) < 0.0001 ? (
                                                <Badge
                                                    variant="success"
                                                    className="h-5 border-emerald-500/20 bg-emerald-500/10 px-1 text-[10px] text-emerald-500"
                                                >
                                                    <Check className="mr-0.5 h-3 w-3" /> Ready
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="h-5 border-amber-500/20 bg-amber-500/10 px-1 text-[10px] text-amber-600"
                                                >
                                                    <AlertCircle className="mr-0.5 h-3 w-3" /> Not 100%
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <InputError message={editForm.errors.items} />
                            </div>

                            {/* Category Mapping checkboxes */}
                            <div className="space-y-2">
                                <Label className="text-sm font-semibold">
                                    Assign to Categories
                                </Label>
                                <div className="grid max-h-[140px] grid-cols-2 gap-3 overflow-y-auto rounded-lg border border-border/50 bg-background/30 p-3">
                                    {categories.map((cat) => (
                                        <div
                                            key={cat.id}
                                            className="flex items-center space-x-2"
                                        >
                                            <Checkbox
                                                id={`edit-cat-${cat.id}`}
                                                checked={editForm.data.category_ids.includes(
                                                    cat.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggleCategoryCheckbox(
                                                        editForm,
                                                        cat.id,
                                                        !!checked,
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor={`edit-cat-${cat.id}`}
                                                className="cursor-pointer truncate text-xs select-none"
                                            >
                                                <span className="mr-1 font-mono font-bold">
                                                    [{cat.code}]
                                                </span>
                                                {cat.name}
                                                {cat.session && (
                                                    <span className="ml-1 text-[10px] opacity-50">
                                                        ({cat.session.name})
                                                    </span>
                                                )}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                <InputError
                                    message={editForm.errors.category_ids}
                                />
                            </div>

                            <DialogFooter className="border-t border-border/30 pt-4">
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
                            Delete Rubric
                        </DialogTitle>
                        <DialogDescription className="text-sm">
                            Are you sure you want to delete this rubric? This
                            action is permanent and will delete all its scoring
                            items and category mappings.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedRubric && (
                        <form
                            onSubmit={handleDeleteSubmit}
                            className="space-y-4 py-2"
                        >
                            <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-xs text-destructive">
                                <p className="font-semibold">
                                    Rubric to delete:
                                </p>
                                <p className="mt-1 font-mono font-bold">
                                    {selectedRubric.name}
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

            {/* Edit Criteria Details Modal */}
            <Dialog open={isCriteriaEditOpen} onOpenChange={setIsCriteriaEditOpen}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>Edit Criteria {criteriaEditData.code ? criteriaEditData.code : ''}</DialogTitle>
                        <DialogDescription>
                            Configure standard labels, weights, and detailed rubric scale guides.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSaveCriteriaEdit} className="space-y-4 py-2">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="criteria-sec">Section</Label>
                                <Input
                                    id="criteria-sec"
                                    value={criteriaEditData.section}
                                    onChange={(e) => setCriteriaEditData(prev => ({ ...prev, section: e.target.value }))}
                                    placeholder="e.g. A"
                                    className="bg-background/50"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="criteria-code">Code</Label>
                                <Input
                                    id="criteria-code"
                                    value={criteriaEditData.code}
                                    onChange={(e) => setCriteriaEditData(prev => ({ ...prev, code: e.target.value }))}
                                    placeholder="e.g. A1"
                                    className="bg-background/50"
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="criteria-label">Label / Criteria</Label>
                            <Input
                                id="criteria-label"
                                value={criteriaEditData.criteria_name}
                                onChange={(e) => setCriteriaEditData(prev => ({ ...prev, criteria_name: e.target.value }))}
                                placeholder="e.g. Project Functionality"
                                className="bg-background/50"
                                required
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="criteria-guide">Description / Guide</Label>
                            <Textarea
                                id="criteria-guide"
                                value={criteriaEditData.description}
                                onChange={(e) => setCriteriaEditData(prev => ({ ...prev, description: e.target.value }))}
                                placeholder="e.g. Grading Scale: [LO2, PO3]"
                                className="h-20 resize-none bg-background/50"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="criteria-weight">Weight (%)</Label>
                            <div className="relative">
                                <Input
                                    id="criteria-weight"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    value={criteriaEditData.weight}
                                    onChange={(e) => setCriteriaEditData(prev => ({ ...prev, weight: e.target.value }))}
                                    className="bg-background/50 pr-8 font-mono"
                                    required
                                />
                                <Percent className="absolute top-2.5 right-2.5 h-3.5 w-3.5 text-muted-foreground" />
                            </div>
                        </div>

                        <div className="space-y-2.5">
                            <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Grading Scale Criteria (0 - 5)
                            </Label>
                            <div className="space-y-2">
                                {[0, 1, 2, 3, 4, 5].map((point) => (
                                    <div key={point} className="flex items-center gap-3">
                                        <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-border bg-muted/50 font-mono text-xs font-bold">
                                            {point}
                                        </span>
                                        <Input
                                            value={criteriaEditData.scale_descriptions[point] || ''}
                                            onChange={(e) => {
                                                const text = e.target.value;
                                                setCriteriaEditData(prev => ({
                                                    ...prev,
                                                    scale_descriptions: {
                                                        ...prev.scale_descriptions,
                                                        [point]: text
                                                    }
                                                }));
                                            }}
                                            placeholder={`Scale guide for score ${point}...`}
                                            className="h-9 bg-background/50"
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>

                        <DialogFooter className="border-t border-border/30 pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsCriteriaEditOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">
                                Save Changes
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

RubricsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Rubrics Management',
            href: '/dashboard/rubrics',
        },
    ],
};
