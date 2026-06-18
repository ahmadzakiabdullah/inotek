import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle, AlertTriangle, ShieldCheck, User, Users, Folder, Calendar, Award } from 'lucide-react';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface TeamMember {
    name: string;
}

interface Project {
    id: number;
    title: string;
    pcode: string | null;
    status: number;
    award_level: string | null;
    certificate_hash: string;
    user: {
        name: string;
    };
    category: {
        name: string;
        code: string;
    };
    session: {
        name: string;
    };
    team_members?: TeamMember[];
}

interface Props {
    project: Project | null;
    verified: boolean;
    hash: string;
}

export default function CertificateVerify({ project, verified, hash }: Props) {
    return (
        <>
            <Head title="Certificate Verification" />

            <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-background to-muted/30 p-6">
                <div className="w-full max-w-md">
                    {/* Brand header */}
                    <div className="mb-6 text-center">
                        <h2 className="text-xl font-bold tracking-tight text-foreground flex items-center justify-center gap-2">
                            <ShieldCheck className="h-6 w-6 text-primary" />
                            INOTEK Innovation Registry
                        </h2>
                        <p className="text-xs text-muted-foreground mt-1">
                            Official Digital Certificate Verification System
                        </p>
                    </div>

                    {verified && project ? (
                        /* SUCCESS CARD */
                        <Card className="border-emerald-500/20 bg-emerald-500/[0.02] shadow-xl shadow-emerald-500/[0.02] overflow-hidden">
                            <div className="h-2 bg-emerald-500" />
                            <CardHeader className="text-center pb-2">
                                <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500">
                                    <CheckCircle className="h-8 w-8" />
                                </div>
                                <CardTitle className="text-xl font-bold text-foreground">
                                    Certificate Verified
                                </CardTitle>
                                <CardDescription className="text-xs text-emerald-500/80 font-medium">
                                    This document is authentic and officially registered
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-2">
                                {/* Details list */}
                                <div className="rounded-lg border border-border/40 bg-background/50 p-4 space-y-3.5 text-sm">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-1.5 text-xs text-muted-foreground font-medium">
                                            <User className="h-3.5 w-3.5" /> Presenter
                                        </div>
                                        <div className="font-semibold text-foreground pl-5">
                                            {project.user.name}
                                        </div>
                                    </div>

                                    {project.team_members && project.team_members.length > 0 && (
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground font-medium">
                                                <Users className="h-3.5 w-3.5" /> Team Members
                                            </div>
                                            <div className="text-xs text-foreground/90 pl-5 leading-relaxed">
                                                {project.team_members.map((m) => m.name).join(', ')}
                                            </div>
                                        </div>
                                    )}

                                    <div className="space-y-1">
                                        <div className="flex items-center gap-1.5 text-xs text-muted-foreground font-medium">
                                            <Folder className="h-3.5 w-3.5" /> Project
                                        </div>
                                        <div className="font-medium text-foreground pl-5 italic leading-relaxed">
                                            "{project.title}"
                                            {project.pcode && (
                                                <Badge variant="outline" className="ml-2 font-mono text-[10px] py-0 border-primary/20 bg-primary/5 text-primary">
                                                    {project.pcode}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground font-medium">
                                                <Calendar className="h-3.5 w-3.5" /> Session
                                            </div>
                                            <div className="text-xs font-medium text-foreground pl-5">
                                                {project.session.name}
                                            </div>
                                        </div>
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground font-medium">
                                                <Award className="h-3.5 w-3.5" /> Award Status
                                            </div>
                                            <div className="text-xs font-semibold pl-5">
                                                {project.award_level ? (
                                                    <span className="text-amber-500 font-bold uppercase tracking-wider">
                                                        {project.award_level} Award
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground italic">
                                                        Participant
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="text-[10px] text-muted-foreground font-mono bg-muted/30 p-2.5 rounded border border-border/20 break-all">
                                    <span className="font-semibold block text-[9px] uppercase text-muted-foreground/60 mb-0.5">Signature Hash</span>
                                    {hash}
                                </div>
                            </CardContent>
                            <CardFooter className="justify-center border-t border-border/20 pt-4 bg-muted/10">
                                <Button asChild variant="outline" size="sm" className="h-8">
                                    <Link href="/">Return to Home</Link>
                                </Button>
                            </CardFooter>
                        </Card>
                    ) : (
                        /* ERROR CARD */
                        <Card className="border-red-500/20 bg-red-500/[0.02] shadow-xl shadow-red-500/[0.02] overflow-hidden">
                            <div className="h-2 bg-red-500" />
                            <CardHeader className="text-center pb-2">
                                <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-red-500/10 text-red-500">
                                    <AlertTriangle className="h-8 w-8" />
                                </div>
                                <CardTitle className="text-xl font-bold text-foreground">
                                    Verification Failed
                                </CardTitle>
                                <CardDescription className="text-xs text-red-500/80 font-medium">
                                    This certificate identifier could not be validated
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-2 text-center">
                                <p className="text-sm text-muted-foreground leading-relaxed px-2">
                                    The signature hash provided does not match any registered, approved project certificates in our database. It may be mistyped, modified, or has been revoked.
                                </p>
                                <div className="text-[10px] text-muted-foreground font-mono bg-muted/40 p-2.5 rounded border border-border/20 break-all text-left">
                                    <span className="font-semibold block text-[9px] uppercase text-muted-foreground/60 mb-0.5">Attempted Hash</span>
                                    {hash}
                                </div>
                            </CardContent>
                            <CardFooter className="justify-center border-t border-border/20 pt-4 bg-muted/10">
                                <Button asChild variant="outline" size="sm" className="h-8">
                                    <Link href="/">Return to Home</Link>
                                </Button>
                            </CardFooter>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}
