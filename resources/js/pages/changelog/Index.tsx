import { Head, usePage, router } from '@inertiajs/react';
import { 
    Activity, 
    Calendar, 
    PlusCircle, 
    RefreshCw, 
    Wrench, 
    ChevronRight, 
    FileClock, 
    Trash2,
    Info
} from 'lucide-react';
import React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface VersionChange {
    version: string;
    date: string;
    badge: string | null;
    changes: {
        added?: string[];
        changed?: string[];
        fixed?: string[];
        deprecated?: string[];
        removed?: string[];
    };
}

interface ChangelogProps {
    timeline: VersionChange[];
}

export default function Index({ timeline }: ChangelogProps) {
    const { auth } = usePage().props as any;
    const isAdmin = auth?.user?.role === 'admin';

    const handleClearCache = () => {
        router.post('/changelog/clear-cache', {}, {
            onSuccess: () => {
                // Flash success will be caught by global toaster
            }
        });
    };

    const groupMeta: Record<string, { label: string; icon: React.ReactNode; color: string; border: string; bg: string }> = {
        added: {
            label: 'Added',
            icon: <PlusCircle className="h-3.5 w-3.5" />,
            color: 'text-emerald-500 dark:text-emerald-400',
            border: 'border-emerald-500/10 dark:border-emerald-500/20',
            bg: 'bg-emerald-500/5 dark:bg-emerald-500/10'
        },
        changed: {
            label: 'Changed',
            icon: <RefreshCw className="h-3.5 w-3.5" />,
            color: 'text-blue-500 dark:text-blue-400',
            border: 'border-blue-500/10 dark:border-blue-500/20',
            bg: 'bg-blue-500/5 dark:bg-blue-500/10'
        },
        fixed: {
            label: 'Fixed',
            icon: <Wrench className="h-3.5 w-3.5" />,
            color: 'text-amber-500 dark:text-amber-400',
            border: 'border-amber-500/10 dark:border-amber-500/20',
            bg: 'bg-amber-500/5 dark:bg-amber-500/10'
        },
        deprecated: {
            label: 'Deprecated',
            icon: <Info className="h-3.5 w-3.5" />,
            color: 'text-rose-500 dark:text-rose-400',
            border: 'border-rose-500/10 dark:border-rose-500/20',
            bg: 'bg-rose-500/5 dark:bg-rose-500/10'
        },
        removed: {
            label: 'Removed',
            icon: <Trash2 className="h-3.5 w-3.5" />,
            color: 'text-neutral-500 dark:text-neutral-400',
            border: 'border-neutral-500/10 dark:border-neutral-500/20',
            bg: 'bg-neutral-500/5 dark:bg-neutral-500/10'
        }
    };

    return (
        <>
            <Head title="System Updates" />

            <div className="space-y-6 max-w-4xl mx-auto pb-10">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="flex flex-col gap-1.5">
                        <h1 className="text-2xl font-bold tracking-tight lg:text-3xl flex items-center gap-2">
                            <Activity className="h-8 w-8 text-primary" />
                            System Updates
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Follow the development milestones, feature improvements, and release changelogs of INOTEK.
                        </p>
                    </div>

                    {isAdmin && (
                        <Button 
                            variant="outline" 
                            size="sm" 
                            onClick={handleClearCache}
                            className="shrink-0 flex items-center gap-2 border-border/60 hover:bg-muted/40"
                        >
                            <Trash2 className="h-3.5 w-3.5 text-muted-foreground" />
                            Clear Changelog Cache
                        </Button>
                    )}
                </div>

                {timeline.length === 0 ? (
                    <Card className="border border-border/50 bg-card/60 backdrop-blur-sm p-6 text-center">
                        <FileClock className="h-10 w-10 text-muted-foreground/60 mx-auto mb-2" />
                        <CardTitle className="text-base font-semibold">No version updates recorded yet</CardTitle>
                        <CardDescription>Wait for future announcements and development releases.</CardDescription>
                    </Card>
                ) : (
                    <div className="relative pl-6 sm:pl-8 border-l border-border/60 ml-4 space-y-8">
                        {timeline.map((versionItem, index) => {
                            const isCurrent = versionItem.badge && versionItem.badge.toLowerCase().includes('current');
                            
                            return (
                                <div key={versionItem.version} className="relative group">
                                    {/* Vertical Timeline indicator node */}
                                    <span 
                                        className={`absolute -left-[31px] sm:-left-[39px] top-1.5 flex h-4 w-4 rounded-full border-2 transition-all duration-300 ${
                                            isCurrent 
                                                ? 'bg-primary border-primary ring-4 ring-primary/20 scale-125' 
                                                : 'bg-background border-border group-hover:border-primary/50'
                                        }`} 
                                    />

                                    <Card className={`border border-border/50 bg-card/40 backdrop-blur-sm transition-all duration-200 hover:shadow-md ${
                                        isCurrent ? 'ring-1 ring-primary/20 bg-card/60' : ''
                                    }`}>
                                        <CardHeader className="p-4 sm:p-5 pb-3">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <div className="flex items-center gap-2">
                                                    <h3 className="text-lg font-bold tracking-tight text-foreground">
                                                        {versionItem.version}
                                                    </h3>
                                                    {versionItem.badge && (
                                                        <Badge 
                                                            variant="secondary"
                                                            className={`text-[10px] font-bold px-2 py-0.5 ${
                                                                isCurrent 
                                                                    ? 'bg-primary/10 text-primary border border-primary/20' 
                                                                    : 'bg-muted text-muted-foreground'
                                                            }`}
                                                        >
                                                            {versionItem.badge}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-1.5 text-xs text-muted-foreground font-mono">
                                                    <Calendar className="h-3.5 w-3.5" />
                                                    {versionItem.date}
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="p-4 sm:p-5 pt-0 space-y-4">
                                            {Object.entries(versionItem.changes).map(([group, items]) => {
                                                const meta = groupMeta[group] || {
                                                    label: group.toUpperCase(),
                                                    icon: <Info className="h-3.5 w-3.5" />,
                                                    color: 'text-muted-foreground',
                                                    border: 'border-border',
                                                    bg: 'bg-muted/10'
                                                };

                                                if (items.length === 0) {
return null;
}

                                                return (
                                                    <div key={group} className="space-y-2">
                                                        <span className={`inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded border ${meta.bg} ${meta.color} ${meta.border}`}>
                                                            {meta.icon}
                                                            {meta.label}
                                                        </span>
                                                        <ul className="space-y-1.5 pl-2">
                                                            {items.map((item, idx) => (
                                                                <li key={idx} className="text-xs sm:text-sm text-muted-foreground leading-relaxed flex items-start gap-2">
                                                                    <ChevronRight className="h-3.5 w-3.5 text-muted-foreground/40 shrink-0 mt-0.5" />
                                                                    <span dangerouslySetInnerHTML={{ __html: item.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') }} />
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    </div>
                                                );
                                            })}
                                        </CardContent>
                                    </Card>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'System Updates',
            href: '/changelog',
        },
    ],
};
