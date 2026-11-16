import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Mail, Search, XCircle } from 'lucide-react';
import React, { useState } from 'react';
import EmailTabs from '@/components/email-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';

interface LogRow {
    id: number;
    event: string | null;
    recipient: string;
    subject: string | null;
    status: 'sent' | 'failed';
    error: string | null;
    created_at: string | null;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    logs: {
        data: LogRow[];
        links: PageLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { status: string; event: string; search: string };
    events: Record<string, string>;
    summary: { sent: number; failed: number };
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/** Laravel's pagination labels contain HTML entities such as &laquo;. */
function pageLabel(label: string): string {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}

export default function Logs({ logs, filters, events, summary }: Props) {
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (changes: Partial<Props['filters']>) => {
        const next = { ...filters, search, ...changes };
        const query = Object.fromEntries(Object.entries(next).filter(([, value]) => value !== ''));

        router.get('/dashboard/email/logs', query, { preserveState: true, preserveScroll: true, replace: true });
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters({ search });
    };

    return (
        <>
            <Head title="Email Logs" />

            <div className="mx-auto max-w-6xl space-y-6">
                <div className="flex flex-col gap-1.5">
                    <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                        <Mail className="h-8 w-8 text-primary" />
                        Email &amp; Notifications
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Every email the mail server accepted, and queued emails that failed after all retries.
                    </p>
                </div>

                <EmailTabs active="logs" />

                <div className="grid gap-3 sm:grid-cols-2">
                    <Card className="border border-border/50 bg-card/60">
                        <CardContent className="flex items-center gap-3 p-4">
                            <CheckCircle2 className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                            <div>
                                <p className="text-xl font-semibold">{summary.sent}</p>
                                <p className="text-xs text-muted-foreground">Sent in the last 30 days</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border border-border/50 bg-card/60">
                        <CardContent className="flex items-center gap-3 p-4">
                            <XCircle className="h-5 w-5 text-destructive" />
                            <div>
                                <p className="text-xl font-semibold">{summary.failed}</p>
                                <p className="text-xs text-muted-foreground">Failed in the last 30 days</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-2 md:flex-row md:items-center">
                    <form onSubmit={handleSearch} className="flex flex-1 gap-2">
                        <Input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search recipient or subject"
                            aria-label="Search recipient or subject"
                            className="flex-1"
                        />
                        <Button type="submit" variant="outline" aria-label="Search">
                            <Search className="h-4 w-4" />
                        </Button>
                    </form>
                    <NativeSelect
                        value={filters.status}
                        onChange={(e) => applyFilters({ status: e.target.value })}
                        aria-label="Filter by status"
                        className="md:w-40"
                    >
                        <NativeSelectOption value="">All statuses</NativeSelectOption>
                        <NativeSelectOption value="sent">Sent</NativeSelectOption>
                        <NativeSelectOption value="failed">Failed</NativeSelectOption>
                    </NativeSelect>
                    <NativeSelect
                        value={filters.event}
                        onChange={(e) => applyFilters({ event: e.target.value })}
                        aria-label="Filter by email type"
                        className="md:w-56"
                    >
                        <NativeSelectOption value="">All email types</NativeSelectOption>
                        {Object.entries(events).map(([key, label]) => (
                            <NativeSelectOption key={key} value={key}>
                                {label}
                            </NativeSelectOption>
                        ))}
                    </NativeSelect>
                </div>

                <Card className="border border-border/50 bg-card/60 shadow-md">
                    <CardContent className="p-0">
                        {logs.data.length === 0 ? (
                            <p className="p-8 text-center text-sm text-muted-foreground">No emails match these filters.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">Time</th>
                                            <th className="px-4 py-2 font-medium">Recipient</th>
                                            <th className="px-4 py-2 font-medium">Type</th>
                                            <th className="px-4 py-2 font-medium">Subject</th>
                                            <th className="px-4 py-2 font-medium">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/60">
                                        {logs.data.map((log) => (
                                            <tr key={log.id} className="align-top">
                                                <td className="px-4 py-2 whitespace-nowrap text-muted-foreground">
                                                    {formatDateTime(log.created_at)}
                                                </td>
                                                <td className="px-4 py-2 break-all">{log.recipient}</td>
                                                <td className="px-4 py-2 whitespace-nowrap">
                                                    {log.event ? (events[log.event] ?? log.event) : '-'}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <span className="line-clamp-2">{log.subject ?? '-'}</span>
                                                    {log.error && (
                                                        <span className="mt-1 block text-xs break-words text-destructive">{log.error}</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {log.status === 'sent' ? (
                                                        <Badge variant="secondary" className="gap-1 font-normal">
                                                            <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                                            Sent
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="destructive" className="gap-1 font-normal">
                                                            <XCircle className="h-3.5 w-3.5" />
                                                            Failed
                                                        </Badge>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {logs.total > 0 && (
                    <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                        <p className="text-xs text-muted-foreground">
                            Showing {logs.from}–{logs.to} of {logs.total}
                        </p>
                        <nav aria-label="Pagination" className="flex flex-wrap gap-1">
                            {logs.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        preserveScroll
                                        aria-current={link.active ? 'page' : undefined}
                                        className={`min-w-8 rounded-md border px-2 py-1 text-center text-xs ${
                                            link.active
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border hover:bg-muted'
                                        }`}
                                    >
                                        {pageLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span
                                        key={index}
                                        className="min-w-8 rounded-md border border-border px-2 py-1 text-center text-xs text-muted-foreground opacity-50"
                                    >
                                        {pageLabel(link.label)}
                                    </span>
                                ),
                            )}
                        </nav>
                    </div>
                )}
            </div>
        </>
    );
}

Logs.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Email & Notifications', href: '/dashboard/email' },
        { title: 'Logs', href: '/dashboard/email/logs' },
    ],
};
