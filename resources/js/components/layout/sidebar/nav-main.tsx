'use client';

import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import {
    ActivityIcon,
    ArchiveRestoreIcon,
    BadgeDollarSignIcon,
    BrainCircuitIcon,
    BrainIcon,
    Building2Icon,
    CalendarIcon,
    ChartBarDecreasingIcon,
    ChartPieIcon,
    ChevronRight,
    ClipboardCheckIcon,
    ClipboardMinusIcon,
    ComponentIcon,
    CookieIcon,
    FingerprintIcon,
    FolderDotIcon,
    FolderIcon,
    GaugeIcon,
    GraduationCapIcon,
    ImagesIcon,
    KeyIcon,
    MailIcon,
    MessageSquareIcon,
    ProportionsIcon,
    SettingsIcon,
    ShoppingBagIcon,
    SquareCheckIcon,
    SquareKanbanIcon,
    StickyNoteIcon,
    UserIcon,
    UsersIcon,
    WalletMinimalIcon,
    type LucideIcon,
    GithubIcon,
    RedoDotIcon,
    Paintbrush,
    CreditCardIcon,
    SpeechIcon,
    MessageSquareHeartIcon,
    BookAIcon,
    PuzzleIcon,
    ShieldAlertIcon,
    Trophy,
    BellIcon,
} from 'lucide-react';
import { Link, usePage } from '@inertiajs/react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type NavGroup = {
    title: string;
    items: NavItem;
};

type NavItem = {
    title: string;
    href: string;
    icon?: LucideIcon;
    isComing?: boolean;
    isDataBadge?: string;
    isNew?: boolean;
    newTab?: boolean;
    items?: NavItem;
}[];

export function getNavItems(userRole: string | null | undefined): NavGroup[] {
    let items: NavGroup[] = [
        {
            title: 'Platform',
            items: [
                {
                    title: 'Dashboard',
                    href: userRole === 'judge' ? '/judge/evaluations' : '/dashboard',
                    icon: ChartPieIcon,
                },
                {
                    title: 'Live Leaderboard',
                    href: '/leaderboard',
                    icon: Trophy,
                },
            ],
        },
    ];

    if (userRole === 'admin') {
        items.push({
            title: 'Admin Portal',
            items: [
                {
                    title: 'Competition Setup',
                    href: '/admin/sessions',
                    icon: CalendarIcon,
                    items: [
                        { title: 'Sessions Management', href: '/admin/sessions' },
                        { title: 'Categories Management', href: '/admin/categories' },
                        { title: 'Rubrics Management', href: '/admin/rubrics' },
                    ],
                },
                {
                    title: 'User & Access Admin',
                    href: '/admin/users',
                    icon: UsersIcon,
                    items: [
                        { title: 'Users Management', href: '/admin/users' },
                        { title: 'Roles Management', href: '/admin/roles' },
                    ],
                },
                {
                    title: 'Judging Workspace',
                    href: '/admin/approvals',
                    icon: ClipboardCheckIcon,
                    items: [
                        { title: 'Project Approvals', href: '/admin/approvals' },
                        { title: 'Judge Assignments', href: '/admin/assignments' },
                        { title: 'Round 2 Shortlist', href: '/admin/round2' },
                    ],
                },
                {
                    title: 'System Administration',
                    href: '/admin/settings',
                    icon: SettingsIcon,
                    items: [
                        { title: 'System Settings', href: '/admin/settings' },
                        { title: 'System Audit Logs', href: '/admin/audit-logs' },
                        { title: 'Broadcast Alerts', href: '/admin/announcements' },
                    ],
                },
            ],
        });
    }

    if (userRole === 'judge') {
        items.push({
            title: 'Judge Portal',
            items: [
                {
                    title: 'Evaluation Panel',
                    href: '/judge/evaluations',
                    icon: ClipboardCheckIcon,
                },
            ],
        });
    }

    if (userRole === 'lecturer') {
        items.push({
            title: 'Supervisor Portal',
            items: [
                {
                    title: 'Supervised Projects',
                    href: '/projects',
                    icon: FolderIcon,
                },
                {
                    title: 'Project Approvals',
                    href: '/admin/approvals',
                    icon: SquareCheckIcon,
                },
            ],
        });
    }

    if (userRole === 'user') {
        items.push({
            title: 'Participant Portal',
            items: [
                {
                    title: 'My Project Submission',
                    href: '/projects',
                    icon: FolderIcon,
                },
            ],
        });
    }

    // Add Account & Settings at the bottom
    items.push({
        title: 'Account & Settings',
        items: [
            {
                title: 'Profile',
                href: '/account/profile',
                icon: UserIcon,
            },
            {
                title: 'System Updates',
                href: '/changelog',
                icon: ActivityIcon,
            },
            {
                title: 'Settings',
                href: '/settings/profile',
                icon: SettingsIcon,
                items: [
                    { title: 'Profile Settings', href: '/settings/profile' },
                    {
                        title: 'Security & Passkeys',
                        href: '/settings/security',
                    },
                    { title: 'Appearance', href: '/settings/appearance' },
                ],
            },
        ],
    });

    return items;
}

export const navItems = getNavItems(null);

export function NavMain() {
    const { url: pathname, props } = usePage();
    const { isMobile } = useSidebar();
    const user = (props.auth as any)?.user;
    const userRole = user?.role;

    const filteredItems = React.useMemo(() => {
        return getNavItems(userRole);
    }, [userRole]);

    return (
        <>
            {filteredItems.map((nav) => (
                <SidebarGroup key={nav.title}>
                    <SidebarGroupLabel>{nav.title}</SidebarGroupLabel>
                    <SidebarGroupContent className="flex flex-col gap-2">
                        <SidebarMenu>
                            {nav.items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    {Array.isArray(item.items) &&
                                    item.items.length > 0 ? (
                                        <>
                                            <div className="hidden group-data-[collapsible=icon]:block">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <SidebarMenuButton
                                                            tooltip={item.title}
                                                        >
                                                            {item.icon && (
                                                                <item.icon />
                                                            )}
                                                            <span>
                                                                {item.title}
                                                            </span>
                                                            <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                                        </SidebarMenuButton>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        side={
                                                            isMobile
                                                                ? 'bottom'
                                                                : 'right'
                                                        }
                                                        align={
                                                            isMobile
                                                                ? 'end'
                                                                : 'start'
                                                        }
                                                        className="min-w-48 rounded-lg"
                                                    >
                                                        <DropdownMenuLabel>
                                                            {item.title}
                                                        </DropdownMenuLabel>
                                                        {item.items?.map(
                                                            (item) => (
                                                                <DropdownMenuItem
                                                                    className="hover:bg-[var(--primary)]/10! hover:text-foreground active:bg-[var(--primary)]/10! active:text-foreground"
                                                                    asChild
                                                                    key={
                                                                        item.title
                                                                    }
                                                                >
                                                                    <a
                                                                        href={
                                                                            item.href
                                                                        }
                                                                    >
                                                                        {
                                                                            item.title
                                                                        }
                                                                    </a>
                                                                </DropdownMenuItem>
                                                            ),
                                                        )}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                            <Collapsible
                                                className="group/collapsible block group-data-[collapsible=icon]:hidden"
                                                defaultOpen={
                                                    !!item.items.find(
                                                        (s) =>
                                                            s.href === pathname,
                                                    )
                                                }
                                            >
                                                <CollapsibleTrigger asChild>
                                                    <SidebarMenuButton
                                                        className="hover:bg-[var(--primary)]/10 hover:text-foreground active:bg-[var(--primary)]/10 active:text-foreground"
                                                        tooltip={item.title}
                                                    >
                                                        {item.icon && (
                                                            <item.icon />
                                                        )}
                                                        <span>
                                                            {item.title}
                                                        </span>
                                                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                                    </SidebarMenuButton>
                                                </CollapsibleTrigger>
                                                <CollapsibleContent>
                                                    <SidebarMenuSub>
                                                        {item?.items?.map(
                                                            (subItem, key) => (
                                                                <SidebarMenuSubItem
                                                                    key={key}
                                                                >
                                                                    <SidebarMenuSubButton
                                                                        className="hover:bg-[var(--primary)]/10 hover:text-foreground active:bg-[var(--primary)]/10 active:text-foreground"
                                                                        isActive={
                                                                            pathname ===
                                                                            subItem.href
                                                                        }
                                                                        asChild
                                                                    >
                                                                        <Link
                                                                            href={
                                                                                subItem.href
                                                                            }
                                                                            target={
                                                                                subItem.newTab
                                                                                    ? '_blank'
                                                                                    : ''
                                                                            }
                                                                        >
                                                                            <span>
                                                                                {
                                                                                    subItem.title
                                                                                }
                                                                            </span>
                                                                        </Link>
                                                                    </SidebarMenuSubButton>
                                                                </SidebarMenuSubItem>
                                                            ),
                                                        )}
                                                    </SidebarMenuSub>
                                                </CollapsibleContent>
                                            </Collapsible>
                                        </>
                                    ) : (
                                        <SidebarMenuButton
                                            className="hover:bg-[var(--primary)]/10 hover:text-foreground active:bg-[var(--primary)]/10 active:text-foreground"
                                            isActive={pathname === item.href}
                                            tooltip={item.title}
                                            asChild
                                        >
                                            <Link
                                                href={item.href}
                                                target={
                                                    item.newTab ? '_blank' : ''
                                                }
                                            >
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    )}
                                    {!!item.isComing && (
                                        <SidebarMenuBadge className="opacity-50 peer-hover/menu-button:text-foreground">
                                            Coming
                                        </SidebarMenuBadge>
                                    )}
                                    {!!item.isNew && (
                                        <SidebarMenuBadge className="border border-green-400 text-green-600 peer-hover/menu-button:text-green-600">
                                            New
                                        </SidebarMenuBadge>
                                    )}
                                    {!!item.isDataBadge && (
                                        <SidebarMenuBadge className="peer-hover/menu-button:text-foreground">
                                            {item.isDataBadge}
                                        </SidebarMenuBadge>
                                    )}
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            ))}
        </>
    );
}
