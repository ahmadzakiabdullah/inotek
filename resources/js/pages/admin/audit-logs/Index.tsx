import React, { useState, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Search, ShieldAlert, Monitor, Globe, Clock, User } from 'lucide-react';

interface AuditUser {
    id: number;
    name: string;
    email: string;
}

interface AuditLog {
    id: number;
    user_id: number | null;
    action: string;
    description: string;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
    user?: AuditUser | null;
}

interface Props {
    logs: AuditLog[];
}

export default function AuditLogsIndex({ logs }: Props) {
    const [searchTerm, setSearchTerm] = useState('');
    const [actionFilter, setActionFilter] = useState('ALL');

    // Unique actions list for filter dropdown
    const actionTypes = useMemo(() => {
        const set = new Set(logs.map((log) => log.action));
        return ['ALL', ...Array.from(set)];
    }, [logs]);

    // Filter logs
    const filteredLogs = useMemo(() => {
        return logs.filter((log) => {
            const matchesSearch =
                log.action.toLowerCase().includes(searchTerm.toLowerCase()) ||
                log.description.toLowerCase().includes(searchTerm.toLowerCase()) ||
                (log.user?.name && log.user.name.toLowerCase().includes(searchTerm.toLowerCase())) ||
                (log.ip_address && log.ip_address.includes(searchTerm)) ||
                (log.user?.email && log.user.email.toLowerCase().includes(searchTerm.toLowerCase()));

            const matchesAction = actionFilter === 'ALL' || log.action === actionFilter;

            return matchesSearch && matchesAction;
        });
    }, [logs, searchTerm, actionFilter]);

    // Helper to format timestamps
    const formatDateTime = (dateStr: string) => {
        try {
            const d = new Date(dateStr);
            return d.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        } catch {
            return dateStr;
        }
    };

    // Helper for action badge colors
    const getActionBadgeClass = (action: string) => {
        if (action.includes('DELETE') || action.includes('REJECT') || action.includes('REMOVE')) {
            return 'border-red-500/20 bg-red-500/10 text-red-500 hover:bg-red-500/15';
        }
        if (action.includes('CREATE') || action.includes('APPROVE') || action.includes('ASSIGN')) {
            return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/15';
        }
        if (action.includes('LOCK')) {
            return 'border-amber-500/20 bg-amber-500/10 text-amber-500 hover:bg-amber-500/15';
        }
        return 'border-blue-500/20 bg-blue-500/10 text-blue-500 hover:bg-blue-500/15';
    };

    return (
        <>
            <Head title="System Audit Logs" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-2">
                    <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight lg:text-3xl">
                        <ShieldAlert className="h-8 w-8 text-primary" />
                        System Audit Logs
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Monitor system security, administrative modifications, and event logging records.
                    </p>
                </div>

                {/* Filters */}
                <Card className="border border-border/50 bg-card/40 backdrop-blur-sm">
                    <CardHeader className="p-4 pb-2">
                        <CardTitle className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">
                            Filter Logs
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-4 pt-0">
                        <div className="flex flex-col gap-4 sm:flex-row">
                            <div className="relative flex-1">
                                <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search by action, description, username or IP..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="pl-9 border-border/50 bg-background/50"
                                />
                            </div>
                            <div className="w-full sm:w-64">
                                <Select value={actionFilter} onValueChange={setActionFilter}>
                                    <SelectTrigger className="bg-background/50 border-border/50">
                                        <SelectValue placeholder="All Actions" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {actionTypes.map((type) => (
                                            <SelectItem key={type} value={type}>
                                                {type === 'ALL' ? 'All Actions' : type.replace(/_/g, ' ')}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Logs Table */}
                <Card className="overflow-hidden border border-border/50 bg-card/40 backdrop-blur-sm">
                    <CardHeader className="border-b border-border/40 p-4">
                        <CardTitle className="text-lg font-semibold flex items-center justify-between">
                            <span>Event Audit History</span>
                            <Badge variant="secondary" className="font-mono">
                                {filteredLogs.length} events
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="border-b border-border/40 bg-muted/20 hover:bg-transparent">
                                        <TableHead className="w-[200px] py-3 pl-6 font-semibold">
                                            <span className="flex items-center gap-1.5"><Clock className="h-4 w-4 text-muted-foreground" /> Timestamp</span>
                                        </TableHead>
                                        <TableHead className="w-[180px] font-semibold">
                                            <span className="flex items-center gap-1.5"><User className="h-4 w-4 text-muted-foreground" /> Initiator</span>
                                        </TableHead>
                                        <TableHead className="w-[180px] font-semibold">Action</TableHead>
                                        <TableHead className="font-semibold">Description</TableHead>
                                        <TableHead className="w-[180px] pr-6 font-semibold">Metadata</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredLogs.length > 0 ? (
                                        filteredLogs.map((log) => (
                                            <TableRow
                                                key={log.id}
                                                className="border-b border-border/30 transition-colors hover:bg-muted/10"
                                            >
                                                <TableCell className="py-4 pl-6 align-top text-xs font-mono text-muted-foreground">
                                                    {formatDateTime(log.created_at)}
                                                </TableCell>
                                                <TableCell className="py-4 align-top">
                                                    {log.user ? (
                                                        <div className="space-y-0.5">
                                                            <div className="text-xs font-semibold text-foreground">
                                                                {log.user.name}
                                                            </div>
                                                            <div className="text-[10px] text-muted-foreground truncate max-w-[170px]">
                                                                {log.user.email}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground/60 italic font-medium">
                                                            System / Automated
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="py-4 align-top">
                                                    <Badge
                                                        variant="outline"
                                                        className={`font-mono text-[10px] py-0 px-1.5 border uppercase tracking-wider rounded ${getActionBadgeClass(log.action)}`}
                                                    >
                                                        {log.action}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="py-4 align-top text-xs leading-relaxed text-foreground break-words whitespace-normal max-w-md">
                                                    {log.description}
                                                </TableCell>
                                                <TableCell className="py-4 pr-6 align-top">
                                                    <div className="space-y-1 text-[10px] text-muted-foreground font-mono">
                                                        {log.ip_address && (
                                                            <div className="flex items-center gap-1">
                                                                <Globe className="h-3 w-3 opacity-60" />
                                                                <span>IP: {log.ip_address}</span>
                                                            </div>
                                                        )}
                                                        {log.user_agent && (
                                                            <div className="flex items-start gap-1 max-w-[170px]">
                                                                <Monitor className="h-3 w-3 opacity-60 mt-0.5 shrink-0" />
                                                                <span className="truncate" title={log.user_agent}>
                                                                    {log.user_agent.split(' ')[0] || 'Unknown Device'}
                                                                </span>
                                                            </div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    ) : (
                                        <TableRow>
                                            <TableCell colSpan={5} className="py-8 text-center text-sm text-muted-foreground italic">
                                                No audit logs match your search.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AuditLogsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'System Audit Logs',
            href: '/admin/audit-logs',
        },
    ],
};
