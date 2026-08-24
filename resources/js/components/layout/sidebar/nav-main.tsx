'use client';


import { Link, usePage } from '@inertiajs/react';
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
    BellIcon
} from 'lucide-react';
import type {LucideIcon} from 'lucide-react';
import * as React from 'react';
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
    const items: NavGroup[] = [
        {
            title: 'Platform',
            items: [
                {
                    title: 'Dashboard',
                    href:
                        userRole === 'judge'
                            ? '/dashboard/judge/evaluations'
                            : '/dashboard',
                    icon: ChartPieIcon,
                },
                {
                    title: 'Live Leaderboard',
                    href: '/dashboard/leaderboard',
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
                    href: '/dashboard/sessions',
                    icon: CalendarIcon,
                    items: [
                        {
                            title: 'Sessions Management',
                            href: '/dashboard/sessions',
                        },
                        {
                            title: 'Categories Management',
                            href: '/dashboard/categories',
                        },
                        {
                            title: 'Rubrics Management',
                            href: '/dashboard/rubrics',
                        },
                    ],
                },
                {
                    title: 'User & Access Admin',
                    href: '/dashboard/users',
                    icon: UsersIcon,
                    items: [
                        {
                            title: 'Users Management',
                            href: '/dashboard/users',
                        },
                        {
                            title: 'Roles Management',
                            href: '/dashboard/roles',
                        },
                    ],
                },
                {
                    title: 'Judging Workspace',
                    href: '/dashboard/approvals',
                    icon: ClipboardCheckIcon,
                    items: [
                        {
                            title: 'Project Approvals',
                            href: '/dashboard/approvals',
                        },
                        {
                            title: 'Judge Assignments',
                            href: '/dashboard/assignments',
                        },
                        {
                            title: 'Round 2 Shortlist',
                            href: '/dashboard/round2',
                        },
                    ],
                },
                {
                    title: 'System Administration',
                    href: '/dashboard/admin-settings',
                    icon: SettingsIcon,
                    items: [
                        {
                            title: 'System Settings',
                            href: '/dashboard/admin-settings',
                        },
                        {
                            title: 'System Audit Logs',
                            href: '/dashboard/audit-logs',
                        },
                        {
                            title: 'Broadcast Alerts',
                            href: '/dashboard/announcements',
                        },
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
                    href: '/dashboard/judge/evaluations',
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
                    href: '/dashboard/projects',
                    icon: FolderIcon,
                },
                {
                    title: 'Project Approvals',
                    href: '/dashboard/approvals',
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
                    href: '/dashboard/projects',
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
                href: '/dashboard/profile',
                icon: UserIcon,
            },
            {
                title: 'System Updates',
                href: '/dashboard/changelog',
                icon: ActivityIcon,
            },
            {
                title: 'Settings',
                href: '/dashboard/settings/profile',
                icon: SettingsIcon,
                items: [
                    {
                        title: 'Profile Settings',
                        href: '/dashboard/settings/profile',
                    },
                    {
                        title: 'Security & Passkeys',
                        href: '/dashboard/settings/security',
                    },
                    {
                        title: 'Appearance',
                        href: '/dashboard/settings/appearance',
                    },
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
