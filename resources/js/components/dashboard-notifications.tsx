import { usePage, router } from '@inertiajs/react';
import { BellRing, CheckCircle2, AlertTriangle, Info, XCircle } from 'lucide-react';
import React from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Notification {
    id: string;
    title: string;
    desc: string;
    action_url: string;
    type: string;
    avatar: string | null;
    date: string;
    unread_message: boolean;
}

const typeIcons: Record<string, React.ReactNode> = {
    info: <Info className="h-4 w-4 text-blue-500" />,
    success: <CheckCircle2 className="h-4 w-4 text-emerald-500" />,
    warning: <AlertTriangle className="h-4 w-4 text-amber-500" />,
    error: <XCircle className="h-4 w-4 text-rose-500" />,
};

const typeBorderColors: Record<string, string> = {
    info: 'border-blue-500/10 bg-blue-500/[0.02]',
    success: 'border-emerald-500/10 bg-emerald-500/[0.02]',
    warning: 'border-amber-500/10 bg-amber-500/[0.02]',
    error: 'border-rose-500/10 bg-rose-500/[0.02]',
};

export default function DashboardNotifications() {
    const { auth } = usePage().props as any;
    const list = (auth?.notifications || []) as Notification[];

    if (list.length === 0) {
return null;
} // Hide the widget if no unread notifications

    const handleNotificationClick = (item: Notification) => {
        router.post(`/notifications/${item.id}/read`, {}, {
            onSuccess: () => {
                if (item.action_url && item.action_url !== '#') {
                    router.visit(item.action_url);
                }
            }
        });
    };

    const handleMarkAllAsRead = (e: React.MouseEvent) => {
        e.preventDefault();
        router.post('/notifications/read-all');
    };

    return (
        <Card className="border border-border/50 bg-card/40 backdrop-blur-sm overflow-hidden shadow-md">
            <CardHeader className="border-b border-border/40 p-4 flex flex-row items-center justify-between">
                <div className="flex items-center gap-2">
                    <BellRing className="h-5 w-5 text-primary animate-pulse" />
                    <div>
                        <CardTitle className="text-base font-bold text-foreground">Announcements & Alerts</CardTitle>
                        <CardDescription className="text-xs text-muted-foreground mt-0.5">
                            You have {list.length} unread system notifications.
                        </CardDescription>
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleMarkAllAsRead}
                    className="h-8 text-xs font-semibold text-primary hover:underline hover:bg-primary/5"
                >
                    Mark all as read
                </Button>
            </CardHeader>
            <CardContent className="p-0">
                <div className="divide-y divide-border/30">
                    {list.map((item) => (
                        <div
                            key={item.id}
                            onClick={() => handleNotificationClick(item)}
                            className={`flex items-start gap-3 p-4 cursor-pointer hover:bg-muted/30 transition-colors border-l-2 border-l-transparent hover:border-l-primary ${typeBorderColors[item.type] || ''}`}
                        >
                            <div className="mt-0.5 shrink-0">
                                {typeIcons[item.type] || <Info className="h-4 w-4 text-muted-foreground" />}
                            </div>
                            <div className="flex-1 space-y-1">
                                <div className="flex justify-between items-start">
                                    <h4 className="text-sm font-semibold text-foreground leading-none">
                                        {item.title}
                                    </h4>
                                    <span className="text-[10px] text-muted-foreground font-mono">
                                        {item.date}
                                    </span>
                                </div>
                                <p className="text-xs text-muted-foreground leading-relaxed">
                                    {item.desc}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
