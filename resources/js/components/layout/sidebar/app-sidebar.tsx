'use client';

import * as React from 'react';
import { useEffect } from 'react';
import { ChevronsUpDown, ShoppingBagIcon, UserCircle2Icon } from 'lucide-react';
import { PlusIcon } from '@radix-ui/react-icons';
import { usePage, Link } from '@inertiajs/react';
import { useIsTablet } from '@/hooks/use-mobile';

import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { NavMain } from '@/components/layout/sidebar/nav-main';
import { NavUser } from '@/components/layout/sidebar/nav-user';
import { ScrollArea } from '@/components/ui/scroll-area';
import Logo from '@/components/layout/logo';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    const { url: pathname, props: pageProps } = usePage();
    const { setOpen, setOpenMobile, isMobile } = useSidebar();
    const appVersion = (pageProps as any).version || '1.5.0';
    const systemName = (pageProps as any).name || 'INOTEK';
    const isTablet = useIsTablet();

    useEffect(() => {
        if (isMobile) setOpenMobile(false);
    }, [pathname]);

    useEffect(() => {
        setOpen(!isTablet);
    }, [isTablet]);

    return (
        <Sidebar collapsible="icon" {...props}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <SidebarMenuButton className="h-10 group-data-[collapsible=icon]:px-0! hover:bg-[var(--primary)]/5 hover:text-foreground">
                                    <Logo />
                                    <span className="font-semibold text-foreground">
                                        {systemName}
                                    </span>
                                    <ChevronsUpDown className="ml-auto group-data-[collapsible=icon]:hidden" />
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                className="mt-4 w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                                side={isMobile ? 'bottom' : 'right'}
                                align="end"
                                sideOffset={4}
                            >
                                <DropdownMenuLabel>Projects</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem className="flex items-center gap-3">
                                    <div className="flex size-8 items-center justify-center rounded-md border">
                                        <ShoppingBagIcon className="size-4 text-muted-foreground" />
                                    </div>
                                    <div className="flex flex-col">
                                        <span className="text-sm font-medium">
                                            E-commerce
                                        </span>
                                        <span className="text-xs text-green-700">
                                            Active
                                        </span>
                                    </div>
                                </DropdownMenuItem>
                                <DropdownMenuItem className="flex items-center gap-3">
                                    <div className="flex size-8 items-center justify-center rounded-md border">
                                        <UserCircle2Icon className="size-4 text-muted-foreground" />
                                    </div>
                                    <div className="flex flex-col">
                                        <span className="text-sm font-medium">
                                            Blog Platform
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            Inactive
                                        </span>
                                    </div>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <Button className="w-full">
                                    <PlusIcon />
                                    New Project
                                </Button>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <ScrollArea className="h-full">
                    <NavMain />
                </ScrollArea>
            </SidebarContent>
            <SidebarFooter>
                <Card className="gap-4 overflow-hidden py-4 group-data-[collapsible=icon]:hidden">
                    <CardHeader className="px-3">
                        <CardTitle>Download</CardTitle>
                        <CardDescription>
                            Unlock lifetime access to all dashboards, templates
                            and components.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-3">
                        <Button className="w-full" asChild>
                            <Link
                                href="https://shadcnuikit.com/pricing"
                                target="_blank"
                            >
                                Get Shadcn UI Kit
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
                <div className="px-4 py-1 text-[10px] font-mono text-muted-foreground/40 group-data-[collapsible=icon]:hidden border-t border-border/10">
                    INOTEK v{appVersion}
                </div>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
