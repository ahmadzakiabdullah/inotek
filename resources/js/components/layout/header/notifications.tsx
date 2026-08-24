import { usePage, router } from '@inertiajs/react';
import { BellIcon, ClockIcon } from 'lucide-react';
import { useEffect } from 'react';
import { toast } from 'sonner';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useIsMobile } from '@/hooks/use-mobile';

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

const typeColors: Record<string, string> = {
    info: 'bg-blue-500/10 text-blue-500',
    success: 'bg-emerald-500/10 text-emerald-500',
    warning: 'bg-amber-500/10 text-amber-500',
    error: 'bg-rose-500/10 text-rose-500',
};

const playNotificationSound = () => {
    try {
        const AudioContext = window.AudioContext || (window as any).webkitAudioContext;

        if (!AudioContext) {
return;
}

        const ctx = new AudioContext();
        
        const playNote = (freq: number, startTime: number, duration: number) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, startTime);
            
            gain.gain.setValueAtTime(0, startTime);
            gain.gain.linearRampToValueAtTime(0.12, startTime + 0.04);
            gain.gain.exponentialRampToValueAtTime(0.0001, startTime + duration);
            
            osc.connect(gain);
            gain.connect(ctx.destination);
            
            osc.start(startTime);
            osc.stop(startTime + duration);
        };
        
        const now = ctx.currentTime;
        playNote(523.25, now, 0.3); // C5
        playNote(659.25, now + 0.08, 0.3); // E5
        playNote(783.99, now + 0.16, 0.4); // G5
    } catch (e) {
        // Ignore errors if autoplay is blocked
    }
};

const Notifications = () => {
    const isMobile = useIsMobile();
    const { auth } = usePage().props as any;
    const user = auth?.user;
    const list = (auth?.notifications || []) as Notification[];

    useEffect(() => {
        if (!user || !(window as any).Echo) {
return;
}

        const echo = (window as any).Echo;
        const channel = echo.private(`App.Models.User.${user.id}`);
        
        channel.notification((notification: any) => {
            // Reload the notifications list dynamically
            router.reload({ only: ['auth'] });

            // Play clean synth chime sound
            playNotificationSound();

            // Display toast arpeggio message using sonner
            const payload = notification.data || notification;
            const title = payload.title || 'New Notification';
            const desc = payload.message || payload.desc || '';
            const type = payload.type || 'info';

            if (type === 'success') {
                toast.success(title, { description: desc });
            } else if (type === 'warning') {
                toast.warning(title, { description: desc });
            } else if (type === 'error') {
                toast.error(title, { description: desc });
            } else {
                toast.info(title, { description: desc });
            }
        });

        return () => {
            if (echo) {
                echo.leave(`App.Models.User.${user.id}`);
            }
        };
    }, [user?.id]);

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
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button size="icon-sm" variant="ghost" className="relative">
                    <BellIcon className="h-5 w-5" />
                    {list.length > 0 && (
                        <span className="absolute end-0.5 top-0.5 block size-1.5 shrink-0 rounded-full bg-destructive"></span>
                    )}
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align={isMobile ? 'center' : 'end'}
                className="ms-4 w-80 p-0"
            >
                <DropdownMenuLabel className="sticky top-0 z-10 bg-background p-0 dark:bg-muted">
                    <div className="flex justify-between items-center border-b px-6 py-4">
                        <div className="font-medium text-sm">Notifications</div>
                        {list.length > 0 && (
                            <Button
                                variant="link"
                                className="h-auto p-0 text-xs text-primary hover:underline"
                                size="icon-sm"
                                onClick={handleMarkAllAsRead}
                            >
                                Mark all as read
                            </Button>
                        )}
                    </div>
                </DropdownMenuLabel>

                <ScrollArea className="h-[350px]">
                    {list.length > 0 ? (
                        list.map((item: Notification, key) => (
                            <DropdownMenuItem
                                key={item.id || key}
                                onClick={() => handleNotificationClick(item)}
                                className="group flex cursor-pointer items-start gap-3 rounded-none border-b px-4 py-3 hover:bg-muted/40 transition-colors"
                            >
                                <div className="flex flex-1 items-start gap-3">
                                    <div className="flex-none">
                                        <Avatar className="size-8">
                                            {item.avatar ? (
                                                <AvatarImage
                                                    src={item.avatar.startsWith('http') ? item.avatar : `/images/avatars/${item.avatar}`}
                                                />
                                            ) : null}
                                            <AvatarFallback className={`font-bold text-xs ${typeColors[item.type] || 'bg-primary/10 text-primary'}`}>
                                                {item.title ? item.title.charAt(0) : 'N'}
                                            </AvatarFallback>
                                        </Avatar>
                                    </div>
                                    <div className="flex flex-1 flex-col gap-1">
                                        <div className="dark:group-hover:text-default-800 text-sm font-semibold leading-tight">
                                            {item.title}
                                        </div>
                                        <div className="dark:group-hover:text-default-700 text-xs text-muted-foreground leading-normal">
                                            {item.desc}
                                        </div>
                                        <div className="dark:group-hover:text-default-500 flex items-center gap-1 text-[10px] text-muted-foreground mt-1">
                                            <ClockIcon className="size-3" />
                                            {item.date}
                                        </div>
                                    </div>
                                </div>
                                {item.unread_message && (
                                    <div className="flex-none pt-1">
                                        <span className="block size-2 rounded-full bg-destructive" />
                                    </div>
                                )}
                            </DropdownMenuItem>
                        ))
                    ) : (
                        <div className="py-12 text-center text-sm text-muted-foreground italic">
                            No unread notifications
                        </div>
                    )}
                </ScrollArea>
            </DropdownMenuContent>
        </DropdownMenu>
    );
};

export default Notifications;
