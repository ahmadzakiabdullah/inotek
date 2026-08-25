import { usePage } from '@inertiajs/react';
import { Mail, ShieldCheck, UserRound } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export function ProfileCard() {
    const { auth } = usePage().props as any;
    const user = auth?.user;

    const name = user?.name || 'Anshan Haso';
    const username = user?.username;
    const email = user?.email || '—';
    const role = user?.role || 'user';
    const avatar = user?.avatar || `/images/avatars/10.png`;

    const initials =
        name
            .split(' ')
            .filter(Boolean)
            .map((n: string) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2) || 'AH';

    return (
        <Card>
            <CardHeader className="border-b bg-muted/30">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <Avatar className="size-20 border-4 border-background shadow-sm">
                            <AvatarImage src={avatar} alt={name} />
                            <AvatarFallback>{initials}</AvatarFallback>
                    </Avatar>
                    <div className="space-y-1">
                        <CardTitle>{name}</CardTitle>
                        <CardDescription>
                            {username ? `@${username}` : 'INOTEK participant'}
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="grid gap-4 p-6 sm:grid-cols-2">
                <div className="flex items-center gap-3 rounded-lg border p-4">
                    <Mail className="size-4 text-muted-foreground" />
                    <div className="min-w-0">
                        <p className="text-xs text-muted-foreground">Email</p>
                        <p className="truncate text-sm font-medium">{email}</p>
                    </div>
                </div>
                <div className="flex items-center gap-3 rounded-lg border p-4">
                    <ShieldCheck className="size-4 text-muted-foreground" />
                    <div>
                        <p className="text-xs text-muted-foreground">Role</p>
                        <Badge variant="secondary" className="mt-1 capitalize">{role}</Badge>
                    </div>
                </div>
                <div className="flex items-center gap-3 rounded-lg border p-4 sm:col-span-2">
                    <UserRound className="size-4 text-muted-foreground" />
                    <div>
                        <p className="text-xs text-muted-foreground">Account status</p>
                        <p className="text-sm font-medium">Active account</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
