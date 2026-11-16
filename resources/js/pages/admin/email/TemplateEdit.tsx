import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Braces, Eye, Mail, RotateCcw, Save } from 'lucide-react';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import EmailTabs from '@/components/email-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

interface Placeholder {
    key: string;
    description: string;
    sample: string;
}

interface Props {
    event: {
        key: string;
        slug: string;
        label: string;
        recipient: string;
        description: string;
        button: string;
        enabled: boolean;
    };
    template: { subject: string; body: string; customized: boolean };
    defaults: { subject: string; body: string };
    placeholders: Placeholder[];
    limits: { subject: number; body: number };
}

type Field = 'subject' | 'body';

const PREVIEW_DELAY_MS = 400;

export default function TemplateEdit({ event, template, defaults, placeholders, limits }: Props) {
    const form = useForm({ subject: template.subject, body: template.body });
    const subjectRef = useRef<HTMLInputElement>(null);
    const bodyRef = useRef<HTMLTextAreaElement>(null);
    const [lastField, setLastField] = useState<Field>('body');
    const [previewHtml, setPreviewHtml] = useState('');
    const [previewFailed, setPreviewFailed] = useState(false);

    const baseUrl = `/dashboard/email/templates/${event.slug}`;

    // Refresh the preview shortly after the admin stops typing. POST, because
    // IIS rejects long query strings.
    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(`${baseUrl}/preview`, {
                method: 'POST',
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ subject: form.data.subject, body: form.data.body }),
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    return response.text();
                })
                .then((html) => {
                    setPreviewHtml(html);
                    setPreviewFailed(false);
                })
                .catch((error: unknown) => {
                    if (!(error instanceof DOMException && error.name === 'AbortError')) {
                        setPreviewFailed(true);
                    }
                });
        }, PREVIEW_DELAY_MS);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [baseUrl, form.data.subject, form.data.body]);

    const sampleSubject = useMemo(() => {
        return placeholders.reduce((text, p) => text.split(`{${p.key}}`).join(p.sample), form.data.subject);
    }, [form.data.subject, placeholders]);

    const insertPlaceholder = (key: string) => {
        const token = `{${key}}`;
        const element = lastField === 'subject' ? subjectRef.current : bodyRef.current;
        const current = form.data[lastField];
        const start = element?.selectionStart ?? current.length;
        const end = element?.selectionEnd ?? current.length;

        form.setData(lastField, current.slice(0, start) + token + current.slice(end));

        window.requestAnimationFrame(() => {
            element?.focus();
            element?.setSelectionRange(start + token.length, start + token.length);
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // The server sends the success message as a toast.
        form.post(baseUrl, {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
            onError: () => toast.error('The template was not saved. Check the highlighted fields.'),
        });
    };

    const handleReset = () => {
        if (!window.confirm('Replace this template with the default subject and message?')) {
            return;
        }

        router.post(`${baseUrl}/reset`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                form.setData({ subject: defaults.subject, body: defaults.body });
                form.setDefaults({ subject: defaults.subject, body: defaults.body });
                form.clearErrors();
            },
        });
    };

    return (
        <>
            <Head title={`Email Template: ${event.label}`} />

            <div className="mx-auto max-w-6xl space-y-6">
                <div className="flex flex-col gap-1.5">
                    <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                        <Mail className="h-8 w-8 text-primary" />
                        Email &amp; Notifications
                    </h1>
                </div>

                <EmailTabs active="templates" />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <Link
                            href="/dashboard/email/templates"
                            className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="h-3.5 w-3.5" /> All templates
                        </Link>
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-lg font-semibold">{event.label}</h2>
                            <Badge variant="secondary" className="text-[11px] font-normal">
                                To: {event.recipient}
                            </Badge>
                            {!event.enabled && (
                                <Badge variant="outline" className="text-[11px] font-normal text-muted-foreground">
                                    Email off for this event
                                </Badge>
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground">{event.description}</p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Editor */}
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <Card className="border border-border/50 bg-card/60 shadow-md backdrop-blur-sm">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base font-semibold">Content</CardTitle>
                                <CardDescription>
                                    Leave a blank line between paragraphs. &ldquo;Hello {'{name}'},&rdquo;, the &ldquo;Open&rdquo; button
                                    (to {event.button}) and the signature are added automatically.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1.5">
                                    <label htmlFor="subject" className="text-sm font-semibold text-foreground">
                                        Subject
                                    </label>
                                    <Input
                                        id="subject"
                                        ref={subjectRef}
                                        value={form.data.subject}
                                        maxLength={limits.subject}
                                        onFocus={() => setLastField('subject')}
                                        onChange={(e) => form.setData('subject', e.target.value)}
                                        aria-invalid={form.errors.subject ? true : undefined}
                                        className={form.errors.subject ? 'border-destructive' : ''}
                                    />
                                    {form.errors.subject && <p className="text-xs text-destructive">{form.errors.subject}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <label htmlFor="body" className="text-sm font-semibold text-foreground">
                                        Message
                                    </label>
                                    <Textarea
                                        id="body"
                                        ref={bodyRef}
                                        rows={10}
                                        value={form.data.body}
                                        maxLength={limits.body}
                                        onFocus={() => setLastField('body')}
                                        onChange={(e) => form.setData('body', e.target.value)}
                                        aria-invalid={form.errors.body ? true : undefined}
                                        className={`font-mono text-sm ${form.errors.body ? 'border-destructive' : ''}`}
                                    />
                                    <div className="flex justify-between gap-2">
                                        {form.errors.body ? <p className="text-xs text-destructive">{form.errors.body}</p> : <span />}
                                        <span className="text-xs text-muted-foreground">
                                            {form.data.body.length} / {limits.body}
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border border-border/50 bg-card/60 shadow-md backdrop-blur-sm">
                            <CardHeader className="flex flex-row items-center gap-3 space-y-0 pb-3">
                                <Braces className="h-5 w-5 text-primary" />
                                <div>
                                    <CardTitle className="text-base font-semibold">Placeholders</CardTitle>
                                    <CardDescription>Click to insert into the {lastField === 'subject' ? 'subject' : 'message'}.</CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="divide-y divide-border/60">
                                {placeholders.map((p) => (
                                    <div key={p.key} className="flex items-start justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                        <div className="min-w-0">
                                            <button
                                                type="button"
                                                onClick={() => insertPlaceholder(p.key)}
                                                className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground hover:bg-primary/10"
                                            >
                                                {`{${p.key}}`}
                                            </button>
                                            <p className="mt-1 text-xs text-muted-foreground">{p.description}</p>
                                        </div>
                                        <span className="shrink-0 text-right text-xs text-muted-foreground">e.g. {p.sample}</span>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Button type="submit" disabled={form.processing || !form.isDirty} className="flex-1">
                                <Save className="h-4 w-4" />
                                {form.processing ? 'Saving...' : 'Save Template'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleReset}
                                disabled={form.processing || !template.customized}
                                title={template.customized ? undefined : 'This template already uses the default text.'}
                            >
                                <RotateCcw className="h-4 w-4" />
                                Reset to Default
                            </Button>
                        </div>
                        {form.isDirty && <p className="text-xs text-amber-700 dark:text-amber-400">You have unsaved changes.</p>}
                    </form>

                    {/* Preview */}
                    <Card className="border border-border/50 bg-card/40 backdrop-blur-sm lg:sticky lg:top-4 lg:self-start">
                        <CardHeader className="flex flex-row items-center gap-3 space-y-0 pb-3">
                            <Eye className="h-5 w-5 text-primary" />
                            <div className="min-w-0">
                                <CardTitle className="text-base font-semibold">Preview</CardTitle>
                                <CardDescription>With sample values. Not sent to anyone.</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="rounded-md border border-border/60 bg-muted/40 px-3 py-2 text-sm">
                                <span className="text-muted-foreground">Subject: </span>
                                <span className="font-medium break-words">{sampleSubject}</span>
                            </div>
                            {previewFailed && (
                                <p className="text-xs text-destructive">The preview could not be loaded. Reload the page and try again.</p>
                            )}
                            <iframe
                                title="Email preview"
                                srcDoc={previewHtml}
                                sandbox=""
                                className="h-[560px] w-full rounded-md border border-border/60 bg-white"
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

/**
 * Laravel's CSRF token from the XSRF-TOKEN cookie.
 */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

TemplateEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Email & Notifications', href: '/dashboard/email' },
        { title: 'Templates', href: '/dashboard/email/templates' },
    ],
};
