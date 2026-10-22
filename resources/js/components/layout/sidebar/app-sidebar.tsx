'use client';

import { usePage, Link } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';
import * as React from 'react';
import { useEffect } from 'react';
import Logo from '@/components/layout/logo';
import { NavMain } from '@/components/layout/sidebar/nav-main';
import { NavUser } from '@/components/layout/sidebar/nav-user';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
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
import { useIsTablet } from '@/hooks/use-mobile';


export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    const { url: pathname, props: pageProps } = usePage();
    const { setOpen, setOpenMobile, isMobile } = useSidebar();
    const appVersion = (pageProps as any).version || '1.5.0';
    const systemName = (pageProps as any).name || 'INOTEK';
    const isTablet = useIsTablet();

    useEffect(() => {
        if (isMobile) {
setOpenMobile(false);
}
    }, [pathname]);

    useEffect(() => {
        setOpen(!isTablet);
    }, [isTablet]);

    return (
        <Sidebar collapsible="icon" {...props}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton className="h-10 group-data-[collapsible=icon]:px-0! hover:bg-[var(--primary)]/5 hover:text-foreground">
                            <Logo />
                            <span className="font-semibold text-foreground">
                                {systemName}
                            </span>
                            <ChevronsUpDown className="ml-auto group-data-[collapsible=icon]:hidden" />
                        </SidebarMenuButton>
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
