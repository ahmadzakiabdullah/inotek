import { Award, ChevronDown, FileText, History } from 'lucide-react';
import { useState } from 'react';
import { AwardBadge } from '@/components/leaderboard/award-badge';
import JudgesFeedback from '@/components/participant/judges-feedback';
import type { FeedbackRound } from '@/components/participant/judges-feedback';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export interface PastProject {
    id: number;
    title: string;
    pcode: string | null;
    session_name: string | null;
    category: { code: string; name: string } | null;
    results_published: boolean;
    award_level: string | null;
    feedback: FeedbackRound[];
}

/**
 * A participant's approved projects from earlier sessions, so their
 * certificates and judges' feedback stay reachable after a new session starts.
 */
export default function PastParticipations({
    projects,
}: {
    projects: PastProject[];
}) {
    if (projects.length === 0) {
        return null;
    }

    return (
        <Card className="border border-border/50 bg-card/40">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-lg">
                    <History className="h-5 w-5 text-primary" />
                    Previous participations
                </CardTitle>
                <CardDescription>
                    Your projects from earlier competition sessions, with their
                    certificates and the judges' feedback.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {projects.map((project) => (
                    <PastProjectItem key={project.id} project={project} />
                ))}
            </CardContent>
        </Card>
    );
}

function PastProjectItem({ project }: { project: PastProject }) {
    const [showFeedback, setShowFeedback] = useState(false);
    const hasFeedback = project.feedback.length > 0;

    return (
        <div className="rounded-lg border bg-background p-4">
            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        {project.session_name && (
                            <Badge variant="secondary">
                                {project.session_name}
                            </Badge>
                        )}
                        <AwardBadge award={project.award_level} />
                    </div>
                    <div className="leading-snug font-semibold">
                        {project.title}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        <span className="font-mono font-semibold">
                            {project.pcode ?? 'No code'}
                        </span>
                        {project.category && (
                            <>
                                {' '}
                                · {project.category.code} -{' '}
                                {project.category.name}
                            </>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap gap-2 md:justify-end">
                    <Button asChild variant="outline" size="sm">
                        <a
                            href={`/dashboard/projects/${project.id}/certificate/participation`}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Participation certificate
                        </a>
                    </Button>
                    {project.award_level && (
                        <Button
                            asChild
                            size="sm"
                            className="border-amber-600 bg-amber-600 text-white hover:bg-amber-700"
                        >
                            <a
                                href={`/dashboard/projects/${project.id}/certificate/achievement`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Award className="mr-2 h-4 w-4" />
                                Achievement certificate
                            </a>
                        </Button>
                    )}
                    {hasFeedback && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setShowFeedback((open) => !open)}
                            aria-expanded={showFeedback}
                        >
                            Judges' feedback
                            <ChevronDown
                                className={`ml-1 h-4 w-4 transition-transform ${showFeedback ? 'rotate-180' : ''}`}
                            />
                        </Button>
                    )}
                </div>
            </div>

            {!project.results_published && (
                <p className="mt-3 text-xs text-muted-foreground">
                    The results of this session have not been published yet. The
                    award and judges' feedback will appear here once they are.
                </p>
            )}

            {showFeedback && hasFeedback && (
                <div className="mt-4">
                    <JudgesFeedback rounds={project.feedback} />
                </div>
            )}
        </div>
    );
}
