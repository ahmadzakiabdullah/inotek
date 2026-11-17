import { router } from '@inertiajs/react';
import { Mic, Plus } from 'lucide-react';
import { useState } from 'react';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';

export interface WildcardCategory {
    id: number;
    code: string;
    name: string;
}

export interface Round2Wildcard {
    project_id: number;
    pcode: string | null;
    title: string | null;
    category_id: number | null;
    avg_r1: number | null;
    reason: string;
    added_by: string | null;
    added_at: string | null;
}

export interface WildcardCandidate {
    id: number;
    pcode: string | null;
    title: string;
    category_id: number;
    nominations: number;
    avg_r1: number | null;
}

/**
 * Admin-only panel for adding Best Presenter nominees to Round 2 on top of
 * the automatic shortlist. The server enforces every rule shown here.
 */
export default function WildcardsPanel({
    categories,
    wildcards,
    candidates,
    limit,
    scoringStartedCategoryIds,
    locked,
}: {
    categories: WildcardCategory[];
    wildcards: Round2Wildcard[];
    candidates: WildcardCandidate[];
    limit: number;
    scoringStartedCategoryIds: number[];
    locked: boolean;
}) {
    const [target, setTarget] = useState<WildcardCandidate | null>(null);
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const categoryById = new Map(categories.map((c) => [c.id, c]));
    const usedByCategory = wildcards.reduce<Record<number, number>>(
        (acc, w) => {
            if (w.category_id !== null) {
                acc[w.category_id] = (acc[w.category_id] ?? 0) + 1;
            }

            return acc;
        },
        {},
    );

    // Why a candidate cannot be added, or null when it can.
    const blockedReason = (candidate: WildcardCandidate): string | null => {
        if (locked) {
            return 'Round 2 is finalized';
        }

        if (scoringStartedCategoryIds.includes(candidate.category_id)) {
            return 'Round 2 scoring has started in this category';
        }

        if ((usedByCategory[candidate.category_id] ?? 0) >= limit) {
            return `Category limit reached (${limit})`;
        }

        return null;
    };

    const openDialog = (candidate: WildcardCandidate) => {
        setTarget(candidate);
        setReason('');
        setError(null);
    };

    const submit = () => {
        if (!target) {
            return;
        }

        setSubmitting(true);
        router.post(
            `/dashboard/round2/wildcards/${target.id}`,
            { reason },
            {
                preserveScroll: true,
                onSuccess: () => setTarget(null),
                onError: (errors) =>
                    setError(
                        errors.reason ??
                            errors.error ??
                            'The project could not be added.',
                    ),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Card className="overflow-hidden border border-amber-500/30 bg-card/60 shadow-md">
            <CardHeader className="border-b border-border/40 bg-amber-500/5 p-4">
                <CardTitle className="flex items-center gap-2 text-base font-bold">
                    <Mic className="h-5 w-5 text-amber-600" />
                    Round 2 wildcards (Best Presenter)
                </CardTitle>
                <CardDescription className="text-xs">
                    {limit === 0
                        ? 'Wildcards are turned off. Set a limit per category under System Settings to use them.'
                        : `Add up to ${limit} Best Presenter ${limit === 1 ? 'nominee' : 'nominees'} per category to Round 2, on top of the automatic shortlist. A wildcard is judged and ranked exactly like other finalists. Wildcards can be added only before Round 2 scoring starts in that category. Only admins see the wildcard label.`}
                </CardDescription>
            </CardHeader>
            {limit > 0 && (
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-muted/10 text-xs hover:bg-transparent">
                                <TableHead className="py-2 pl-4 text-xs">
                                    Code
                                </TableHead>
                                <TableHead className="text-xs">
                                    Project
                                </TableHead>
                                <TableHead className="text-xs">
                                    Category
                                </TableHead>
                                <TableHead className="w-[110px] text-center text-xs">
                                    Nominations (R1)
                                </TableHead>
                                <TableHead className="w-[90px] text-center text-xs">
                                    Avg R1
                                </TableHead>
                                <TableHead className="w-[200px] pr-4 text-right text-xs" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {candidates.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="h-20 text-center text-xs text-muted-foreground italic"
                                    >
                                        No Best Presenter nominee is outside
                                        Round 2. Nominees appear here once Round
                                        1 judges nominate them.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                candidates.map((candidate) => {
                                    const blocked = blockedReason(candidate);
                                    const category = categoryById.get(
                                        candidate.category_id,
                                    );

                                    return (
                                        <TableRow
                                            key={candidate.id}
                                            className="text-xs"
                                        >
                                            <TableCell className="py-2.5 pl-4 font-mono font-bold text-primary">
                                                {candidate.pcode ?? '-'}
                                            </TableCell>
                                            <TableCell
                                                className="max-w-[220px] truncate font-medium"
                                                title={candidate.title}
                                            >
                                                {candidate.title}
                                            </TableCell>
                                            <TableCell title={category?.name}>
                                                {category?.code ?? '-'}
                                            </TableCell>
                                            <TableCell className="text-center font-mono font-bold text-amber-700 dark:text-amber-400">
                                                {candidate.nominations}
                                            </TableCell>
                                            <TableCell className="text-center font-mono">
                                                {candidate.avg_r1 !== null
                                                    ? `${candidate.avg_r1.toFixed(2)}%`
                                                    : '-'}
                                            </TableCell>
                                            <TableCell className="pr-4 text-right">
                                                {blocked ? (
                                                    <span className="text-[11px] text-muted-foreground">
                                                        {blocked}
                                                    </span>
                                                ) : (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-7 gap-1 border-amber-500/40 text-xs"
                                                        onClick={() =>
                                                            openDialog(
                                                                candidate,
                                                            )
                                                        }
                                                    >
                                                        <Plus className="h-3.5 w-3.5" />
                                                        Add to Round 2
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            )}

            <Dialog
                open={target !== null}
                onOpenChange={(open) => !open && setTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Add {target?.pcode} to Round 2 as a wildcard?
                        </DialogTitle>
                        <DialogDescription>
                            {target?.title} will be judged in Round 2 like every
                            other finalist. Give the reason, for example the
                            organizer's request; it is kept in the audit log.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Badge
                            variant="outline"
                            className="border-amber-500/40 text-amber-700 dark:text-amber-400"
                        >
                            {target?.nominations} Best Presenter{' '}
                            {target?.nominations === 1
                                ? 'nomination'
                                : 'nominations'}{' '}
                            in Round 1
                        </Badge>
                        <Textarea
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder="Reason (required), e.g. Requested by the organizing committee on 18/11/2026"
                            maxLength={500}
                            className="min-h-24"
                            aria-label="Reason"
                        />
                        {error && (
                            <p className="text-xs text-destructive">{error}</p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setTarget(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={submit}
                            disabled={submitting || reason.trim().length < 5}
                        >
                            {submitting ? 'Adding...' : 'Add to Round 2'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}
