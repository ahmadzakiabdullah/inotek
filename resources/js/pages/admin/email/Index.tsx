import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Eraser,
    ImageIcon,
    Mail,
    MailCheck,
    RotateCcw,
    Send,
    Server,
    ToggleRight,
    Trash2,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import EmailTabs from '@/components/email-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';

interface DeliveryStatus {
    mailer: string;
    host: string | null;
    port: number | string | null;
    from_address: string | null;
    from_name: string | null;
    delivers: boolean;
    queue: string;
    pending_jobs: number | null;
    failed_jobs: number | null;
    oldest_job_minutes: number | null;
    worker_stalled: boolean;
    stale_minutes: number;
}

interface EmailEvent {
    key: string;
    label: string;
    recipient: string;
    description: string;
    enabled: boolean;
}

interface Maintenance {
    last_run_at: string | null;
    last_run_human: string | null;
    deleted: Record<string, number> | null;
    overdue: boolean;
    retention: {
        email_logs: number;
        notifications: number;
        failed_jobs: number;
    };
}

interface Props {
    status: DeliveryStatus;
    maintenance: Maintenance;
    settings: {
        enabled: boolean;
        events: EmailEvent[];
        footer: string;
    };
    branding: {
        logo_url: string;
        default_footer: string;
        max_footer: number;
    };
}

function StatusRow({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-0.5 py-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium break-all text-foreground sm:text-right">
                {children}
            </dd>
        </div>
    );
}

export default function Index({
    status,
    maintenance,
    settings,
    branding,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        enabled: settings.enabled,
        events: Object.fromEntries(
            settings.events.map((event) => [event.key, event.enabled]),
        ) as Record<string, boolean>,
        footer: settings.footer,
    });
    const [sendingTest, setSendingTest] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // The server sends the success message as a toast.
        post('/dashboard/email', {
            preserveScroll: true,
            onError: () => toast.error('Failed to save email settings.'),
        });
    };

    const sendTest = () => {
        router.post(
            '/dashboard/email/test',
            {},
            {
                preserveScroll: true,
                onStart: () => setSendingTest(true),
                onFinish: () => setSendingTest(false),
            },
        );
    };

    const [handlingFailed, setHandlingFailed] = useState(false);

    const handleFailed = (action: 'retry' | 'discard') => {
        if (
            action === 'discard' &&
            !window.confirm(
                `Discard ${status.failed_jobs} failed job(s)? These notifications will not be sent.`,
            )
        ) {
            return;
        }

        router.post(
            `/dashboard/email/failed/${action}`,
            {},
            {
                preserveScroll: true,
                onStart: () => setHandlingFailed(true),
                onFinish: () => setHandlingFailed(false),
            },
        );
    };

    const toggleEvent = (key: string, checked: boolean) => {
        setData('events', { ...data.events, [key]: checked });
    };

    return (
        <>
            <Head title="Email & Notifications" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex flex-col gap-1.5">
                    <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                        <Mail className="h-8 w-8 text-primary" />
                        Email &amp; Notifications
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        In-app notifications are always sent. Choose which of
                        them are also sent by email.
                    </p>
                </div>

                <EmailTabs active="settings" />

                <div className="grid gap-6 md:grid-cols-5">
                    {/* Settings (3 cols) */}
                    <form
                        onSubmit={handleSubmit}
                        className="space-y-6 md:col-span-3"
                    >
                        <Card className="border border-border/50 bg-card/60 shadow-md backdrop-blur-sm">
                            <CardHeader className="flex flex-row items-center gap-3 space-y-0 pb-3">
                                <ToggleRight className="h-5 w-5 text-primary" />
                                <div>
                                    <CardTitle className="text-base font-semibold">
                                        Email Delivery
                                    </CardTitle>
                                    <CardDescription>
                                        The master switch overrides every event
                                        below.
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-start justify-between gap-4">
                                    <div className="space-y-1">
                                        <label
                                            htmlFor="mail_enabled"
                                            className="text-sm font-semibold text-foreground"
                                        >
                                            Send email notifications
                                        </label>
                                        <p className="text-xs text-muted-foreground">
                                            Turn this off to stop all
                                            notification emails at once, for
                                            example if something goes wrong
                                            during the competition. Announcement
                                            emails and judge reminders follow it
                                            too; password reset emails are
                                            always sent.
                                        </p>
                                    </div>
                                    <Switch
                                        id="mail_enabled"
                                        checked={data.enabled}
                                        onCheckedChange={(checked) =>
                                            setData('enabled', checked)
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border border-border/50 bg-card/60 shadow-md backdrop-blur-sm">
                            <CardHeader className="flex flex-row items-center gap-3 space-y-0 pb-3">
                                <MailCheck className="h-5 w-5 text-primary" />
                                <div>
                                    <CardTitle className="text-base font-semibold">
                                        Email Events
                                    </CardTitle>
                                    <CardDescription>
                                        Which notifications are also sent by
                                        email.
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="divide-y divide-border/60">
                                {settings.events.map((event) => (
                                    <div
                                        key={event.key}
                                        className="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                    >
                                        <div className="space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <label
                                                    htmlFor={`event_${event.key}`}
                                                    className="text-sm font-semibold text-foreground"
                                                >
                                                    {event.label}
                                                </label>
                                                <Badge
                                                    variant="secondary"
                                                    className="text-[11px] font-normal"
                                                >
                                                    To: {event.recipient}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {event.description}
                                            </p>
                                        </div>
                                        <Switch
                                            id={`event_${event.key}`}
                                            checked={
                                                data.events[event.key] ?? false
                                            }
                                            disabled={!data.enabled}
                                            onCheckedChange={(checked) =>
                                                toggleEvent(event.key, checked)
                                            }
                                            aria-label={`Email for ${event.label}`}
                                        />
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card className="border border-border/50 bg-card/60 shadow-md backdrop-blur-sm">
                            <CardHeader className="flex flex-row items-center gap-3 space-y-0 pb-3">
                                <ImageIcon className="h-5 w-5 text-primary" />
                                <div>
                                    <CardTitle className="text-base font-semibold">
                                        Email Branding
                                    </CardTitle>
                                    <CardDescription>
                                        The logo at the top and the line at the
                                        bottom of every email.
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1.5">
                                    <span className="text-sm font-semibold text-foreground">
                                        Logo
                                    </span>
                                    <div className="flex items-center justify-center rounded-md border border-border/60 bg-white p-3">
                                        <img
                                            src={branding.logo_url}
                                            alt="Email logo"
                                            className="h-14 w-auto"
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        The UTeM logo, as a PNG because most
                                        email clients do not show SVG images. To
                                        change it, replace{' '}
                                        <code>
                                            public/images/email-logo.png
                                        </code>{' '}
                                        on the server (about 400 × 230 pixels).
                                    </p>
                                </div>

                                <div className="space-y-1.5">
                                    <label
                                        htmlFor="mail_footer"
                                        className="text-sm font-semibold text-foreground"
                                    >
                                        Footer
                                    </label>
                                    <Input
                                        id="mail_footer"
                                        value={data.footer}
                                        maxLength={branding.max_footer}
                                        onChange={(e) =>
                                            setData('footer', e.target.value)
                                        }
                                        aria-invalid={
                                            errors.footer ? true : undefined
                                        }
                                        className={
                                            errors.footer
                                                ? 'border-destructive'
                                                : ''
                                        }
                                    />
                                    {errors.footer && (
                                        <p className="text-xs text-destructive">
                                            {errors.footer}
                                        </p>
                                    )}
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-xs text-muted-foreground">
                                            Placeholders:{' '}
                                            <code>{'{year}'}</code>,{' '}
                                            <code>{'{system_name}'}</code>.
                                            Leave empty for the default.
                                        </p>
                                        {data.footer !==
                                            branding.default_footer && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setData(
                                                        'footer',
                                                        branding.default_footer,
                                                    )
                                                }
                                                className="text-xs text-primary underline"
                                            >
                                                Use default
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Button
                            type="submit"
                            disabled={processing}
                            className="flex w-full items-center justify-center gap-2 shadow-lg transition-all duration-200 hover:shadow-primary/20"
                        >
                            {processing ? 'Saving...' : 'Save Email Settings'}
                        </Button>
                    </form>

                    {/* Status (2 cols) */}
                    <div className="space-y-6 md:col-span-2">
                        <Card className="border border-border/50 bg-card/40 backdrop-blur-sm">
                            <CardHeader className="flex flex-row items-center gap-3 space-y-0 pb-3">
                                <Server className="h-5 w-5 text-primary" />
                                <div>
                                    <CardTitle className="text-base font-semibold">
                                        Delivery Status
                                    </CardTitle>
                                    <CardDescription>
                                        Server settings, read-only.
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {!status.delivers && (
                                    <div className="flex gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-900 dark:text-amber-100">
                                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                        <span>
                                            The mailer is{' '}
                                            <strong>{status.mailer}</strong>, so
                                            emails are not delivered. Set
                                            <code className="mx-1">
                                                MAIL_MAILER=smtp
                                            </code>{' '}
                                            in the server&apos;s{' '}
                                            <code>.env</code>.
                                        </span>
                                    </div>
                                )}
                                {status.worker_stalled && (
                                    <div className="flex gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-900 dark:text-amber-100">
                                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                        <span>
                                            The oldest queued notification has
                                            waited {status.oldest_job_minutes}{' '}
                                            minutes. The queue worker (scheduled
                                            task &ldquo;INOTEK Queue
                                            Worker&rdquo;) may not be running.
                                        </span>
                                    </div>
                                )}

                                <dl className="divide-y divide-border/60">
                                    <StatusRow label="Mailer">
                                        {status.mailer}
                                    </StatusRow>
                                    {status.host && (
                                        <StatusRow label="SMTP server">
                                            {status.host}:{status.port}
                                        </StatusRow>
                                    )}
                                    <StatusRow label="Sender">
                                        {status.from_name
                                            ? `${status.from_name} <${status.from_address}>`
                                            : status.from_address}
                                    </StatusRow>
                                    <StatusRow label="Queue">
                                        {status.queue}
                                    </StatusRow>
                                    {status.pending_jobs !== null && (
                                        <StatusRow label="Waiting in queue">
                                            {status.pending_jobs === 0 ? (
                                                <span className="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400">
                                                    <CheckCircle2 className="h-4 w-4" />{' '}
                                                    None
                                                </span>
                                            ) : (
                                                status.pending_jobs
                                            )}
                                        </StatusRow>
                                    )}
                                    {status.failed_jobs !== null && (
                                        <StatusRow label="Failed jobs">
                                            <span
                                                className={
                                                    status.failed_jobs > 0
                                                        ? 'text-destructive'
                                                        : ''
                                                }
                                            >
                                                {status.failed_jobs}
                                            </span>
                                        </StatusRow>
                                    )}
                                </dl>

                                {status.failed_jobs !== null &&
                                    status.failed_jobs > 0 && (
                                        <div className="space-y-2">
                                            <p className="text-xs text-muted-foreground">
                                                Failed jobs are notifications
                                                that could not be delivered
                                                after 3 tries. See the reason in
                                                the Logs tab.
                                            </p>
                                            <div className="flex gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={handlingFailed}
                                                    onClick={() =>
                                                        handleFailed('retry')
                                                    }
                                                    className="flex flex-1 items-center justify-center gap-1.5"
                                                >
                                                    <RotateCcw className="h-4 w-4" />{' '}
                                                    Retry all
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={handlingFailed}
                                                    onClick={() =>
                                                        handleFailed('discard')
                                                    }
                                                    className="flex flex-1 items-center justify-center gap-1.5 text-destructive hover:text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4" />{' '}
                                                    Discard
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                            </CardContent>
                        </Card>

                        <Card className="border border-border/50 bg-card/40 backdrop-blur-sm">
                            <CardHeader className="flex flex-row items-center gap-3 space-y-0 pb-3">
                                <Eraser className="h-5 w-5 text-primary" />
                                <div>
                                    <CardTitle className="text-base font-semibold">
                                        Automatic Cleanup
                                    </CardTitle>
                                    <CardDescription>
                                        Runs every day at 03:00.
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {(maintenance.overdue ||
                                    !maintenance.last_run_at) && (
                                    <div className="flex gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-900 dark:text-amber-100">
                                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                                        <span>
                                            {maintenance.last_run_at
                                                ? 'The cleanup has not run for more than two days.'
                                                : 'The cleanup has not run yet.'}{' '}
                                            It needs the scheduled task
                                            &ldquo;INOTEK Scheduler&rdquo; on
                                            the server.
                                        </span>
                                    </div>
                                )}
                                <ul className="space-y-1 text-xs text-muted-foreground">
                                    <li>
                                        Email log older than{' '}
                                        {maintenance.retention.email_logs} days
                                    </li>
                                    <li>
                                        Read notifications older than{' '}
                                        {maintenance.retention.notifications}{' '}
                                        days
                                    </li>
                                    <li>
                                        Failed jobs older than{' '}
                                        {maintenance.retention.failed_jobs} days
                                    </li>
                                </ul>
                                <p className="text-xs text-muted-foreground">
                                    Audit logs, projects and scores are never
                                    deleted.
                                </p>
                                {maintenance.last_run_at && (
                                    <dl className="divide-y divide-border/60">
                                        <StatusRow label="Last run">
                                            <span
                                                title={new Date(
                                                    maintenance.last_run_at,
                                                ).toLocaleString()}
                                            >
                                                {maintenance.last_run_human}
                                            </span>
                                        </StatusRow>
                                        {maintenance.deleted && (
                                            <StatusRow label="Deleted">
                                                {Object.values(
                                                    maintenance.deleted,
                                                ).reduce(
                                                    (sum, n) => sum + n,
                                                    0,
                                                )}{' '}
                                                rows
                                            </StatusRow>
                                        )}
                                    </dl>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="border border-border/50 bg-card/40 backdrop-blur-sm">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base font-semibold">
                                    Test Email
                                </CardTitle>
                                <CardDescription>
                                    Sends one email to your own address now,
                                    even when email notifications are turned
                                    off.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={sendTest}
                                    disabled={sendingTest || !status.delivers}
                                    className="flex w-full items-center justify-center gap-2"
                                >
                                    <Send className="h-4 w-4" />
                                    {sendingTest
                                        ? 'Sending...'
                                        : 'Send Test Email'}
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
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
            title: 'Email & Notifications',
            href: '/dashboard/email',
        },
    ],
};
