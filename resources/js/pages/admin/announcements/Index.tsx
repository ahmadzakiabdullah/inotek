import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { BellRing, Send, Sparkles, CheckCircle2, AlertTriangle, Info, XCircle } from 'lucide-react';
import { toast } from 'sonner';

const typeIcons: Record<string, React.ReactNode> = {
    info: <Info className="h-5 w-5 text-blue-500" />,
    success: <CheckCircle2 className="h-5 w-5 text-emerald-500" />,
    warning: <AlertTriangle className="h-5 w-5 text-amber-500" />,
    error: <XCircle className="h-5 w-5 text-rose-500" />,
};

const typeBgClasses: Record<string, string> = {
    info: 'bg-blue-500/10 border-blue-500/20 text-blue-900 dark:text-blue-100',
    success: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-900 dark:text-emerald-100',
    warning: 'bg-amber-500/10 border-amber-500/20 text-amber-900 dark:text-amber-100',
    error: 'bg-rose-500/10 border-rose-500/20 text-rose-900 dark:text-rose-100',
};

export default function Index() {
    const { data, setData, post, processing, reset, errors } = useForm({
        title: '',
        message: '',
        target_role: 'all',
        type: 'info',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/announcements', {
            onSuccess: () => {
                toast.success('Announcement broadcasted in real-time!');
                reset('title', 'message');
            },
            onError: () => {
                toast.error('Failed to broadcast announcement.');
            }
        });
    };

    return (
        <>
            <Head title="Broadcast Announcement" />

            <div className="space-y-6 max-w-4xl mx-auto">
                <div className="flex flex-col gap-1.5">
                    <h1 className="text-2xl font-bold tracking-tight lg:text-3xl flex items-center gap-2">
                        <BellRing className="h-8 w-8 text-primary" />
                        Broadcast Announcement
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Compose and dispatch real-time alerts or notifications to targeted user roles instantly.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-5">
                    {/* Form Composer (3 cols) */}
                    <div className="md:col-span-3">
                        <Card className="border border-border/50 bg-card/60 backdrop-blur-sm shadow-md">
                            <CardHeader>
                                <CardTitle className="text-lg font-semibold">Composer</CardTitle>
                                <CardDescription>Draft your broadcast announcement here.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-4">
                                    <div className="space-y-1.5">
                                        <label className="text-sm font-semibold text-foreground">Announcement Title</label>
                                        <Input
                                            value={data.title}
                                            onChange={(e) => setData('title', e.target.value)}
                                            placeholder="e.g. Round 1 Score Validation Deadline"
                                            maxLength={150}
                                            required
                                            className={errors.title ? 'border-destructive' : ''}
                                        />
                                        {errors.title && (
                                            <p className="text-xs text-destructive">{errors.title}</p>
                                        )}
                                    </div>

                                    <div className="space-y-1.5">
                                        <label className="text-sm font-semibold text-foreground">Message</label>
                                        <Textarea
                                            value={data.message}
                                            onChange={(e) => setData('message', e.target.value)}
                                            placeholder="Write your announcement details here..."
                                            maxLength={500}
                                            required
                                            rows={5}
                                            className={errors.message ? 'border-destructive' : ''}
                                        />
                                        {errors.message && (
                                            <p className="text-xs text-destructive">{errors.message}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <label className="text-sm font-semibold text-foreground">Target Role</label>
                                            <Select
                                                value={data.target_role}
                                                onValueChange={(val) => setData('target_role', val)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select Target" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">All Users</SelectItem>
                                                    <SelectItem value="admin">Administrators</SelectItem>
                                                    <SelectItem value="lecturer">Lecturers / Supervisors</SelectItem>
                                                    <SelectItem value="judge">Judges</SelectItem>
                                                    <SelectItem value="user">Students / Participants</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-1.5">
                                            <label className="text-sm font-semibold text-foreground">Severity Level</label>
                                            <Select
                                                value={data.type}
                                                onValueChange={(val) => setData('type', val)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select Severity" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="info">Info (Blue)</SelectItem>
                                                    <SelectItem value="success">Success (Green)</SelectItem>
                                                    <SelectItem value="warning">Warning (Yellow)</SelectItem>
                                                    <SelectItem value="error">Critical Error (Red)</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full shadow-lg transition-all duration-200 hover:shadow-primary/20 mt-2 flex items-center justify-center gap-2"
                                    >
                                        <Send className="h-4 w-4" />
                                        {processing ? 'Broadcasting...' : 'Broadcast to Users'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Preview Panel (2 cols) */}
                    <div className="md:col-span-2">
                        <Card className="border border-border/50 bg-card/40 backdrop-blur-sm h-full flex flex-col">
                            <CardHeader>
                                <CardTitle className="text-lg font-semibold flex items-center gap-2">
                                    <Sparkles className="h-5 w-5 text-primary" />
                                    Live Preview
                                </CardTitle>
                                <CardDescription>How recipients will see the pop-up alert card.</CardDescription>
                            </CardHeader>
                            <CardContent className="flex-1 flex flex-col justify-center p-6">
                                <div className={`border rounded-lg p-5 shadow-sm space-y-3 transition-all duration-300 ${typeBgClasses[data.type] || ''}`}>
                                    <div className="flex items-start gap-3">
                                        <div className="mt-0.5 animate-bounce">
                                            {typeIcons[data.type] || <Info className="h-5 w-5" />}
                                        </div>
                                        <div className="space-y-1">
                                            <h4 className="text-sm font-bold leading-tight">
                                                {data.title || 'Notification Title'}
                                            </h4>
                                            <p className="text-xs opacity-90 leading-relaxed font-normal whitespace-pre-wrap">
                                                {data.message || 'Notification description text will appear here as you type in the composer.'}
                                            </p>
                                        </div>
                                    </div>
                                </div>
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
            title: 'Broadcast Announcement',
            href: '/admin/announcements',
        },
    ],
};
