import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Mail, PencilLine } from 'lucide-react';
import EmailTabs from '@/components/email-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

interface TemplateRow {
    key: string;
    slug: string;
    label: string;
    recipient: string;
    subject: string;
    customized: boolean;
    enabled: boolean;
}

interface Props {
    templates: TemplateRow[];
    emailEnabled: boolean;
}

export default function Templates({ templates, emailEnabled }: Props) {
    return (
        <>
            <Head title="Email Templates" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex flex-col gap-1.5">
                    <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                        <Mail className="h-8 w-8 text-primary" />
                        Email &amp; Notifications
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Edit the subject and message of each notification email. The layout, greeting and button are the same
                        for every email.
                    </p>
                </div>

                <EmailTabs active="templates" />

                {!emailEnabled && (
                    <div className="flex gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-900 dark:text-amber-100">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            Email notifications are turned off, so these templates are not used until you turn them on under{' '}
                            <Link href="/dashboard/email" className="underline">
                                Settings
                            </Link>
                            .
                        </span>
                    </div>
                )}

                <Card className="border border-border/50 bg-card/60 shadow-md backdrop-blur-sm">
                    <CardContent className="divide-y divide-border/60 p-0">
                        {templates.map((template) => (
                            <div key={template.key} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-semibold text-foreground">{template.label}</span>
                                        <Badge variant="secondary" className="text-[11px] font-normal">
                                            To: {template.recipient}
                                        </Badge>
                                        {template.customized ? (
                                            <Badge className="text-[11px] font-normal">Edited</Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-[11px] font-normal">
                                                Default
                                            </Badge>
                                        )}
                                        {!template.enabled && (
                                            <Badge variant="outline" className="text-[11px] font-normal text-muted-foreground">
                                                Email off
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="truncate text-xs text-muted-foreground" title={template.subject}>
                                        Subject: {template.subject}
                                    </p>
                                </div>
                                <Button asChild variant="outline" size="sm" className="shrink-0">
                                    <Link href={`/dashboard/email/templates/${template.slug}`}>
                                        <PencilLine className="h-4 w-4" />
                                        Edit &amp; preview
                                    </Link>
                                </Button>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <p className="text-xs text-muted-foreground">
                    Announcements and the test email use the text written when they are sent, so they have no template.
                </p>
            </div>
        </>
    );
}

Templates.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Email & Notifications', href: '/dashboard/email' },
        { title: 'Templates', href: '/dashboard/email/templates' },
    ],
};
