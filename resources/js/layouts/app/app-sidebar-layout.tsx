import React from 'react';
import { SiteHeader } from '@/components/layout/header';
import { AppSidebar } from '@/components/layout/sidebar/app-sidebar';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <SidebarProvider
            defaultOpen={true}
            style={
                {
                    '--sidebar-width': 'calc(var(--spacing) * 64)',
                    '--header-height': 'calc(var(--spacing) * 14)',
                    '--content-padding': 'calc(var(--spacing) * 4)',
                    '--content-margin': 'calc(var(--spacing) * 1.5)',
                    '--content-full-height':
                        'calc(100vh - var(--header-height) - (var(--content-padding) * 2) - (var(--content-margin) * 2))',
                } as React.CSSProperties
            }
        >
            <AppSidebar variant="inset" />
            <SidebarInset className="flex min-h-screen flex-1 flex-col">
                <SiteHeader />
                <div className="flex flex-1 flex-col bg-muted/40">
                    <div className="@container/main p-(--content-padding) xl:group-data-[theme-content-layout=centered]/layout:container xl:group-data-[theme-content-layout=centered]/layout:mx-auto">
                        {children}
                    </div>
                </div>
            </SidebarInset>
        </SidebarProvider>
    );
}
