import { MessageSquareQuote } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export interface FeedbackCriterion {
    name: string;
    section: string | null;
    score: number;
    max: number;
}

export interface FeedbackEvaluation {
    label: string;
    total: number;
    comments: string | null;
    criteria: FeedbackCriterion[];
}

export interface FeedbackRound {
    round: number;
    average: number;
    evaluations: FeedbackEvaluation[];
}

/**
 * Judges' anonymous feedback, shown to the participant once results are finalized.
 */
export default function JudgesFeedback({ rounds }: { rounds: FeedbackRound[] }) {
    return (
        <Card className="border border-border/50 bg-card/30 backdrop-blur-sm">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-lg font-bold">
                    <MessageSquareQuote className="h-5 w-5 text-primary" />
                    Judges&rsquo; Feedback
                </CardTitle>
                <CardDescription>
                    Comments and scores from each judge. Judges are shown anonymously.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {rounds.length === 0 && (
                    <p className="text-sm text-muted-foreground italic">No evaluations were recorded for this project.</p>
                )}

                {rounds.map((round) => (
                    <section key={round.round} className="space-y-3" aria-labelledby={`feedback-round-${round.round}`}>
                        <div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-border/50 pb-2">
                            <h3 id={`feedback-round-${round.round}`} className="text-sm font-semibold">
                                Round {round.round}
                            </h3>
                            <span className="text-xs text-muted-foreground">
                                Average score: <strong className="font-mono text-foreground">{round.average}%</strong>
                            </span>
                        </div>

                        <div className="grid gap-3 md:grid-cols-2">
                            {round.evaluations.map((evaluation) => (
                                <article
                                    key={evaluation.label}
                                    className="space-y-3 rounded-lg border border-border/50 bg-background/40 p-4"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-sm font-semibold">{evaluation.label}</span>
                                        <Badge variant="outline" className="font-mono">
                                            {evaluation.total}%
                                        </Badge>
                                    </div>

                                    {evaluation.criteria.length > 0 && (
                                        <table className="w-full text-xs">
                                            <caption className="sr-only">
                                                Scores by criterion from {evaluation.label}, Round {round.round}
                                            </caption>
                                            <thead className="sr-only">
                                                <tr>
                                                    <th scope="col">Criterion</th>
                                                    <th scope="col">Score</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {evaluation.criteria.map((criterion) => (
                                                    <tr key={criterion.name} className="border-b border-border/30 last:border-0">
                                                        <td className="py-1 pr-2 text-muted-foreground">{criterion.name}</td>
                                                        <td className="py-1 text-right font-mono tabular-nums">
                                                            {criterion.score} / {criterion.max}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    )}

                                    <p className="text-sm leading-relaxed whitespace-pre-line">
                                        {evaluation.comments ?? (
                                            <span className="text-muted-foreground italic">No comments provided.</span>
                                        )}
                                    </p>
                                </article>
                            ))}
                        </div>
                    </section>
                ))}
            </CardContent>
        </Card>
    );
}
