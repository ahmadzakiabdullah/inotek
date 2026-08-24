import { usePage } from '@inertiajs/react';
import { Link2Icon, Mail, MapPin, PhoneCall } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

export function ProfileCard() {
    const { auth } = usePage().props as any;
    const user = auth?.user;

    const name = user?.name || 'Anshan Haso';
    const username = user?.username || 'anshan';
    const email = user?.email || 'hello@tobybelhome.com';
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
        <Card className="relative">
            <CardContent className="pt-6">
                <div className="space-y-12">
                    <div className="flex flex-col items-center space-y-4">
                        <Avatar className="size-20">
                            <AvatarImage src={avatar} alt={name} />
                            <AvatarFallback>{initials}</AvatarFallback>
                        </Avatar>
                        <div className="text-center">
                            <h5 className="flex items-center justify-center gap-2 text-xl font-semibold">
                                {name} <Badge variant="info">Pro</Badge>
                            </h5>
                            <div className="text-sm text-muted-foreground">
                                @{username}
                            </div>
                        </div>
                    </div>
                    <div className="grid grid-cols-3 divide-x rounded-md border bg-muted text-center *:py-3">
                        <div>
                            <h5 className="text-lg font-semibold">184</h5>
                            <div className="text-sm text-muted-foreground">
                                Post
                            </div>
                        </div>
                        <div>
                            <h5 className="text-lg font-semibold">32</h5>
                            <div className="text-sm text-muted-foreground">
                                Projects
                            </div>
                        </div>
                        <div>
                            <h5 className="text-lg font-semibold">4.5K</h5>
                            <div className="text-sm text-muted-foreground">
                                Members
                            </div>
                        </div>
                    </div>
                    <div className="flex flex-col gap-y-4">
                        <div className="flex items-center gap-3 text-sm">
                            <Mail className="size-4 text-muted-foreground" />{' '}
                            {email}
                        </div>
                        <div className="flex items-center gap-3 text-sm">
                            <PhoneCall className="size-4 text-muted-foreground" />{' '}
                            (+1-876) 8654 239 581
                        </div>
                        <div className="flex items-center gap-3 text-sm">
                            <MapPin className="size-4 text-muted-foreground" />
                            Canada
                        </div>
                        <div className="flex items-center gap-3 text-sm">
                            <Link2Icon className="size-4 text-muted-foreground" />
                            <a
                                href="https://shadcnuikit.com"
                                className="hover:text-primary hover:underline"
                                target="_blank"
                                rel="noreferrer"
                            >
                                https://shadcnuikit.com
                            </a>
                        </div>
                        <div className="flex items-center gap-3 text-sm">
                            <Link2Icon className="size-4 text-muted-foreground" />
                            <a
                                href="https://bundui.io/"
                                className="hover:text-primary hover:underline"
                                target="_blank"
                                rel="noreferrer"
                            >
                                https://bundui.io/
                            </a>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
