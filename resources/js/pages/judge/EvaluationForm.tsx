import React, { useState, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/input-error';
import { ArrowLeft, Award, Lock, ShieldAlert, Sparkles, CheckCircle2 } from 'lucide-react';

interface RubricItem {
    id: number;
    rubric_id: number;
    section: string | null;
    code: string | null;
    criteria_name: string;
    description: string | null;
    weight: number; // e.g. 0.30
    max_points: number; // e.g. 5
    scale_descriptions?: Record<number, string> | null;
}

interface Rubric {
    id: number;
    name: string;
    description: string;
    items: RubricItem[];
}

interface Project {
    id: number;
    title: string;
    pcode: string | null;
    abstract: string;
    poster_url: string | null;
    video_url: string | null;
    institution_type: string;
    category: {
        id: number;
        name: string;
    } | null;
    team_members: Array<{
        id: number;
        name: string;
        email: string | null;
    }>;
    user?: {
        id: number;
        name: string;
        email: string | null;
    } | null;
}

interface Score {
    id: number;
    total: number;
    score_details: Record<number, number>;
    comments: string | null;
    best_presenter: string | null;
}

interface Props {
    project: Project;
    rubric: Rubric;
    existingScore: Score | null;
    roundNo: number;
    isLocked: boolean;
}

export default function EvaluationForm({ project, rubric, existingScore, roundNo, isLocked }: Props) {
    // Initialize points object mapping item.id to points (default 0 or existing points)
    const initialPoints: Record<number, number> = {};
    rubric.items.forEach((item) => {
        initialPoints[item.id] = existingScore?.score_details?.[item.id] ?? 0;
    });

    const [points, setPoints] = useState<Record<number, number>>(initialPoints);

    const presenters = React.useMemo(() => {
        const list: string[] = [];
        if (project.user?.name) {
            list.push(project.user.name);
        }
        project.team_members.forEach((member) => {
            if (member.name && !list.includes(member.name)) {
                list.push(member.name);
            }
        });
        return list;
    }, [project.user, project.team_members]);

    const { data, setData, post, processing, errors } = useForm({
        scores: points,
        comments: existingScore?.comments ?? '',
        best_presenter: existingScore?.best_presenter ?? '',
        round_no: roundNo,
    });

    // Synchronize points state with form data scores
    useEffect(() => {
        setData('scores', points);
    }, [points]);

    // Calculate dynamic live total score out of 100
    const liveTotalScore = React.useMemo(() => {
        let total = 0;
        rubric.items.forEach((item) => {
            const currentPoints = points[item.id] ?? 0;
            const maxPoints = item.max_points;
            const weight = item.weight;
            if (maxPoints > 0) {
                total += (currentPoints / maxPoints) * weight * 100;
            }
        });
        return Math.round(total * 100) / 100;
    }, [points, rubric.items]);

    const handleSelectPoint = (itemId: number, point: number) => {
        if (isLocked) return;
        setPoints((prev) => ({
            ...prev,
            [itemId]: point,
        }));
    };

    // Check if any items have sections defined
    const hasSections = React.useMemo(() => {
        return rubric.items.some((item) => !!item.section);
    }, [rubric.items]);

    // Group rubric items by section.
    const groupedItems = React.useMemo(() => {
        const groups: Record<string, RubricItem[]> = {};
        rubric.items.forEach((item) => {
            const sec = item.section || 'General';
            if (!groups[sec]) {
                groups[sec] = [];
            }
            groups[sec].push(item);
        });
        return groups;
    }, [rubric.items]);

    const renderItemRow = (item: RubricItem, index: number) => {
        const currentPoint = points[item.id] ?? 0;
        const currentScaleText = item.scale_descriptions?.[currentPoint] || '';

        return (
            <div key={item.id} className="p-6 space-y-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h3 className="font-bold text-base flex items-center gap-2 flex-wrap">
                            <span className="flex items-center justify-center h-6 w-6 rounded-full bg-primary/10 text-primary text-xs font-bold font-mono">
                                {index + 1}
                            </span>
                            {item.section && item.code && (
                                <Badge variant="outline" className="px-1.5 py-0 h-5 font-mono text-xs bg-primary/5 text-primary border-primary/20">
                                    {item.section}{item.code}
                                </Badge>
                            )}
                            <span>{item.criteria_name}</span>
                        </h3>
                        {item.description && (
                            <p className="text-xs text-muted-foreground whitespace-pre-line leading-relaxed">
                                {item.description}
                            </p>
                        )}
                        <p className="text-[11px] text-muted-foreground">
                            Weight: <span className="font-semibold text-foreground font-mono">{(item.weight * 100).toFixed(0)}%</span> | 
                            Max Points: <span className="font-semibold text-foreground font-mono">{item.max_points}</span>
                        </p>
                    </div>
                </div>

                {/* Point Selector Buttons */}
                <div className="space-y-3 pt-1">
                    <div className="flex flex-wrap items-center gap-2">
                        {Array.from({ length: item.max_points + 1 }).map((_, p) => {
                            const isSelected = currentPoint === p;
                            return (
                                <Button
                                    key={p}
                                    type="button"
                                    disabled={isLocked}
                                    onClick={() => handleSelectPoint(item.id, p)}
                                    variant={isSelected ? "default" : "outline"}
                                    className={`h-10 w-12 text-sm font-bold transition-all duration-200 ${
                                        isSelected 
                                            ? "scale-105 shadow-md font-mono" 
                                            : "border-border/50 hover:bg-muted font-mono"
                                    }`}
                                    title={item.scale_descriptions?.[p] || `Score ${p}`}
                                >
                                    {p}
                                </Button>
                            );
                        })}
                        {/* Selection Feedback badge */}
                        <span className="text-xs font-semibold text-muted-foreground ml-2 font-mono shrink-0">
                            ({((currentPoint / item.max_points) * item.weight * 100).toFixed(1)}% contribution)
                        </span>
                    </div>

                    {/* Scale Description text */}
                    {item.scale_descriptions && Object.values(item.scale_descriptions).some(t => !!t) && (
                        <div className="rounded-lg border border-border/40 bg-muted/20 p-3 text-xs transition-all duration-200">
                            <span className="font-bold text-muted-foreground">Rubric Guide: </span>
                            {currentScaleText ? (
                                <span className="text-foreground font-medium">
                                    <span className="font-bold font-mono mr-1">[{currentPoint}]</span>
                                    {currentScaleText}
                                </span>
                            ) : (
                                <span className="text-muted-foreground/60 italic">
                                    No description guide defined for score {currentPoint}
                                </span>
                            )}
                        </div>
                    )}
                </div>
            </div>
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isLocked) return;

        post(`/judge/evaluations/${project.id}`, {
            onSuccess: () => {
                // Success redirect handled by Inertia response
            },
        });
    };

    return (
        <>
            <Head title={`Evaluation: ${project.title}`} />

            <div className="space-y-6">
                {/* Navigation Back */}
                <div>
                    <Link 
                        href="/judge/evaluations"
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Tasks
                    </Link>
                </div>

                {/* Lock Alert Banner */}
                {isLocked && (
                    <div className="flex items-center gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-destructive">
                        <Lock className="h-5 w-5 shrink-0" />
                        <div>
                            <p className="font-semibold">Round 2 Evaluation Locked</p>
                            <p className="text-xs opacity-90 mt-0.5">Admin has closed or locked Round 2 scores. No further additions or edits are allowed.</p>
                        </div>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left & Middle Column: Evaluation & Rubric Form */}
                    <div className="lg:col-span-2 space-y-6">
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Rubric Card */}
                            <Card className="border border-border/50 bg-card/60 backdrop-blur-sm shadow-md">
                                <CardHeader className="border-b border-border/40 pb-4">
                                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                        <div>
                                            <Badge variant="outline" className="mb-1 bg-primary/5 text-primary border-primary/20">
                                                Rubric: {rubric.name}
                                            </Badge>
                                            <CardTitle className="text-xl font-bold">Evaluation Criteria (Round {roundNo})</CardTitle>
                                            <CardDescription className="mt-1">{rubric.description}</CardDescription>
                                        </div>
                                        {/* Live Score Display */}
                                        <div className="flex flex-col items-center justify-center rounded-xl bg-primary/5 border border-primary/15 px-6 py-4 self-start sm:self-center">
                                            <span className="text-[10px] uppercase font-semibold text-muted-foreground tracking-wider">Total Score</span>
                                            <span className="text-3xl font-black font-mono text-primary mt-1">{liveTotalScore.toFixed(2)}%</span>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="divide-y divide-border/30 p-0">
                                    {hasSections ? (
                                        Object.entries(groupedItems).map(([section, items], sIdx) => (
                                            <div key={section} className="border-b border-border/30 last:border-0">
                                                <div className="bg-muted/15 px-6 py-2.5 border-b border-border/15 flex items-center justify-between">
                                                    <span className="text-xs font-black tracking-wider text-primary uppercase font-sans">
                                                        Section {section}
                                                    </span>
                                                    <span className="text-[10px] text-muted-foreground font-semibold">
                                                        {items.length} criteria
                                                    </span>
                                                </div>
                                                <div className="divide-y divide-border/20 bg-background/5">
                                                    {items.map((item, idx) => renderItemRow(item, idx))}
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <div className="divide-y divide-border/30">
                                            {rubric.items.map((item, idx) => renderItemRow(item, idx))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Additional Comments Card */}
                            <Card className="border border-border/50 bg-card/60 backdrop-blur-sm shadow-md">
                                <CardHeader>
                                    <CardTitle className="text-lg font-bold flex items-center gap-2">
                                        <Sparkles className="h-5 w-5 text-amber-500" />
                                        Additional Feedback
                                    </CardTitle>
                                    <CardDescription>Provide written comments or nominate a best presenter.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="comments">Judge's Comments</Label>
                                        <Textarea
                                            id="comments"
                                            value={data.comments}
                                            onChange={(e) => setData('comments', e.target.value)}
                                            placeholder="Enter constructive feedback on the innovation, implementation, or presentation..."
                                            className="min-h-24 bg-background/50 border-border/50 resize-none focus:resize-y"
                                            disabled={isLocked}
                                        />
                                        <InputError message={errors.comments} />
                                    </div>

                                    <div className="space-y-3">
                                        <Label className="text-sm font-semibold">Nominate for Best Presenter?</Label>
                                        
                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                variant={data.best_presenter ? "default" : "outline"}
                                                disabled={isLocked}
                                                onClick={() => {
                                                    if (presenters.length === 1) {
                                                        setData('best_presenter', presenters[0]);
                                                    } else if (presenters.length > 1) {
                                                        setData('best_presenter', data.best_presenter || presenters[0]);
                                                    } else {
                                                        setData('best_presenter', 'Yes');
                                                    }
                                                }}
                                                className={`h-9 px-4 text-xs font-semibold ${
                                                    data.best_presenter 
                                                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white' 
                                                        : 'border-border/50 hover:bg-muted'
                                                }`}
                                            >
                                                Yes
                                            </Button>
                                            
                                            <Button
                                                type="button"
                                                variant={!data.best_presenter ? "default" : "outline"}
                                                disabled={isLocked}
                                                onClick={() => setData('best_presenter', '')}
                                                className={`h-9 px-4 text-xs font-semibold ${
                                                    !data.best_presenter 
                                                        ? 'bg-neutral-600 hover:bg-neutral-700 text-white' 
                                                        : 'border-border/50 hover:bg-muted'
                                                }`}
                                            >
                                                No
                                            </Button>
                                        </div>

                                        {/* If nominated and there are multiple team members, show selection list */}
                                        {data.best_presenter && presenters.length > 1 && (
                                            <div className="space-y-1.5 pt-2 animate-fadeIn">
                                                <Label htmlFor="select_presenter" className="text-xs text-muted-foreground">Select Nominee</Label>
                                                <Select
                                                    value={presenters.includes(data.best_presenter) ? data.best_presenter : presenters[0]}
                                                    onValueChange={(val) => setData('best_presenter', val)}
                                                    disabled={isLocked}
                                                >
                                                    <SelectTrigger id="select_presenter">
                                                        <SelectValue placeholder="Select Nominee" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {presenters.map((name) => (
                                                            <SelectItem key={name} value={name}>
                                                                {name} {name === project.user?.name ? '(Owner/Creator)' : '(Team Member)'}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        )}

                                        {/* If nominated and single presenter, show automatic nomination info */}
                                        {data.best_presenter && presenters.length <= 1 && (
                                            <div className="text-xs text-muted-foreground bg-muted/30 p-2.5 rounded border border-border/40 mt-2 flex items-center gap-2">
                                                <CheckCircle2 className="h-4 w-4 text-emerald-500 shrink-0" />
                                                <span>Nominee: <strong className="text-foreground font-semibold">{data.best_presenter}</strong></span>
                                            </div>
                                        )}

                                        <InputError message={errors.best_presenter} />
                                    </div>
                                </CardContent>
                                <CardFooter className="border-t border-border/40 p-4 flex justify-between items-center">
                                    <span className="text-xs text-muted-foreground">Please make sure all criteria have been scored before submitting.</span>
                                    <Button
                                        type="submit"
                                        disabled={processing || isLocked}
                                        className="gap-2 shadow-md w-40"
                                    >
                                        <CheckCircle2 className="h-4 w-4" />
                                        {processing ? 'Submitting...' : 'Submit Score'}
                                    </Button>
                                </CardFooter>
                            </Card>
                        </form>
                    </div>

                    {/* Right Column: Project Showcase details */}
                    <div className="space-y-6">
                        <Card className="border border-border/50 bg-card/60 backdrop-blur-sm shadow-md overflow-hidden">
                            <CardHeader className="bg-primary/5 border-b border-border/40 p-4">
                                <span className="text-[10px] uppercase font-bold text-primary tracking-wider font-mono">
                                    {project.pcode || 'PENDING_CODE'}
                                </span>
                                <CardTitle className="text-base font-bold mt-1 leading-snug">{project.title}</CardTitle>
                                <CardDescription className="text-xs">
                                    Category: <span className="font-semibold text-foreground">{project.category?.name ?? 'N/A'}</span>
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-4 space-y-4 text-sm">
                                {/* Abstract */}
                                <div className="space-y-1">
                                    <Label className="text-xs text-muted-foreground uppercase font-bold tracking-wider">Project Abstract</Label>
                                    <p className="text-xs leading-relaxed text-muted-foreground/90 bg-background/40 p-3 rounded-lg border border-border/30 max-h-48 overflow-y-auto font-sans">
                                        {project.abstract}
                                    </p>
                                </div>

                                {/* Media URLs */}
                                <div className="grid gap-2 pt-2">
                                    {project.poster_url && (
                                        <a 
                                            href={project.poster_url} 
                                            target="_blank" 
                                            rel="noopener noreferrer"
                                            className="flex items-center justify-between p-2.5 rounded-lg border border-border/50 bg-background/50 text-xs font-semibold text-primary hover:bg-primary/5 transition-colors"
                                        >
                                            <span>📂 Download Project Poster</span>
                                            <span className="text-[10px] font-mono opacity-80">OPEN</span>
                                        </a>
                                    )}
                                    {project.video_url && (
                                        <a 
                                            href={project.video_url} 
                                            target="_blank" 
                                            rel="noopener noreferrer"
                                            className="flex items-center justify-between p-2.5 rounded-lg border border-border/50 bg-background/50 text-xs font-semibold text-primary hover:bg-primary/5 transition-colors"
                                        >
                                            <span>🎥 Watch Presentation Video</span>
                                            <span className="text-[10px] font-mono opacity-80">OPEN</span>
                                        </a>
                                    )}
                                </div>

                                {/* Team Members */}
                                <div className="space-y-2 pt-2 border-t border-border/40">
                                    <Label className="text-xs text-muted-foreground uppercase font-bold tracking-wider">Team Members</Label>
                                    {project.team_members.length > 0 ? (
                                        <ul className="space-y-1.5">
                                            {project.team_members.map((member) => (
                                                <li key={member.id} className="flex items-center justify-between text-xs bg-background/30 px-3 py-1.5 rounded-md border border-border/20">
                                                    <span className="font-semibold">{member.name}</span>
                                                    <span className="text-[10px] text-muted-foreground">{member.email || '-'}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-xs text-muted-foreground italic">No team members registered.</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

EvaluationForm.layout = {
    breadcrumbs: [
        {
            title: 'Judge Dashboard',
            href: '/judge/evaluations',
        },
        {
            title: 'Criteria Evaluation',
            href: '#',
        },
    ],
};
