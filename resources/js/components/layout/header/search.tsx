'use client';

import React, { useEffect, useState } from 'react';
import { CommandIcon, SearchIcon } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { VisuallyHidden } from '@radix-ui/react-visually-hidden';
import { router, usePage } from '@inertiajs/react';

import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import { Button } from '@/components/ui/button';
import { DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { getNavItems } from '@/components/layout/sidebar/nav-main';

export default function Search() {
    const [open, setOpen] = useState(false);
    const { props } = usePage();
    const userRole = (props.auth as any)?.user?.role;

    useEffect(() => {
        const down = (e: KeyboardEvent) => {
            if (e.key === 'k' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                setOpen((open) => !open);
            }
        };
        document.addEventListener('keydown', down);
        return () => document.removeEventListener('keydown', down);
    }, []);

    const items = React.useMemo(() => {
        const rawGroups = getNavItems(userRole);
        return rawGroups.map(group => {
            const flattenedItems: any[] = [];
            group.items.forEach(item => {
                if (Array.isArray(item.items) && item.items.length > 0) {
                    item.items.forEach(sub => {
                        flattenedItems.push({
                            title: `${item.title} > ${sub.title}`,
                            href: sub.href,
                            icon: item.icon,
                        });
                    });
                } else {
                    flattenedItems.push(item);
                }
            });
            return {
                title: group.title,
                items: flattenedItems,
            };
        });
    }, [userRole]);

    return (
        <div className="lg:flex-1">
            <div className="relative hidden max-w-sm flex-1 lg:block">
                <SearchIcon className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    className="h-9 w-full cursor-pointer rounded-md border pr-4 pl-10 text-sm shadow-xs"
                    placeholder="Search..."
                    type="search"
                    onFocus={() => setOpen(true)}
                />
                <div className="absolute top-1/2 right-2 hidden -translate-y-1/2 items-center gap-0.5 rounded-sm bg-zinc-200 p-1 font-mono text-xs font-medium sm:flex dark:bg-neutral-700">
                    <CommandIcon className="size-3" />
                    <span>k</span>
                </div>
            </div>
            <div className="block lg:hidden">
                <Button
                    size="icon"
                    variant="ghost"
                    onClick={() => setOpen(true)}
                >
                    <SearchIcon />
                </Button>
            </div>
            <CommandDialog open={open} onOpenChange={setOpen}>
                <VisuallyHidden>
                    <DialogHeader>
                        <DialogTitle></DialogTitle>
                    </DialogHeader>
                </VisuallyHidden>
                <CommandInput placeholder="Type a command or search..." />
                <CommandList>
                    <CommandEmpty>No results found.</CommandEmpty>
                    {items.map((route) => (
                        <React.Fragment key={route.title}>
                            <CommandGroup heading={route.title}>
                                {route.items.map((item, key) => (
                                    <CommandItem
                                        key={key}
                                        onSelect={() => {
                                            setOpen(false);
                                            router.visit(item.href);
                                        }}
                                    >
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                            <CommandSeparator />
                        </React.Fragment>
                    ))}
                </CommandList>
            </CommandDialog>
        </div>
    );
}
