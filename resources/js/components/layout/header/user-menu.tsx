import {
    BadgeCheck,
    Bell,
    ChevronRightIcon,
    CreditCard,
    LogOut,
    Sparkles,
} from 'lucide-react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Link, usePage, router } from '@inertiajs/react';
import { Progress } from '@/components/ui/progress';

export default function UserMenu() {
    const { auth } = usePage().props as any;
    const user = auth?.user;

    const name = user?.name || 'Toby Belhome';
    const email = user?.email || 'hello@tobybelhome.com';
    const avatar = user?.avatar || '/images/avatars/01.png';

    const initials =
        name
            .split(' ')
            .filter(Boolean)
            .map((n: string) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2) || 'US';

    const handleLogout = () => {
        router.post('/logout');
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Avatar className="cursor-pointer">
                    <AvatarImage src={avatar} alt={name} />
                    <AvatarFallback className="rounded-lg">
                        {initials}
                    </AvatarFallback>
                </Avatar>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="w-(--radix-dropdown-menu-trigger-width) min-w-60"
                align="end"
            >
                <DropdownMenuLabel className="p-0">
                    <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                        <Avatar>
                            <AvatarImage src={avatar} alt={name} />
                            <AvatarFallback className="rounded-lg">
                                {initials}
                            </AvatarFallback>
                        </Avatar>
                        <div className="grid flex-1 text-left text-sm leading-tight">
                            <span className="truncate font-semibold">
                                {name}
                            </span>
                            <span className="truncate text-xs text-muted-foreground">
                                {email}
                            </span>
                        </div>
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild>
                        <a
                            href="https://shadcnuikit.com/pricing"
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Sparkles /> Upgrade to Pro
                        </a>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild>
                        <Link href="/account/profile">
                            <BadgeCheck />
                            Account
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem>
                        <CreditCard />
                        Billing
                    </DropdownMenuItem>
                    <DropdownMenuItem>
                        <Bell />
                        Notifications
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    onClick={handleLogout}
                    className="cursor-pointer text-destructive focus:text-destructive"
                >
                    <LogOut />
                    Log out
                </DropdownMenuItem>
                <div className="mt-1.5 rounded-md border bg-muted">
                    <div className="space-y-3 p-3">
                        <div className="flex items-center justify-between">
                            <h4 className="text-sm font-medium">Credits</h4>
                            <div className="flex cursor-pointer items-center text-sm text-muted-foreground">
                                <span>5 left</span>
                                <ChevronRightIcon className="ml-1 h-4 w-4" />
                            </div>
                        </div>
                        <Progress value={40} indicatorColor="bg-primary" />
                        <div className="flex items-center text-sm text-muted-foreground">
                            Daily credits used first
                        </div>
                    </div>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
