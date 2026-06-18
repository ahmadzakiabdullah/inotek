'use client';

import { MoonIcon, SunIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';

export default function ThemeSwitch() {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    return (
        <Button
            size="icon-sm"
            variant="ghost"
            className="relative"
            onClick={() =>
                updateAppearance(
                    resolvedAppearance === 'light' ? 'dark' : 'light',
                )
            }
        >
            {resolvedAppearance === 'light' ? (
                <MoonIcon className="size-4" />
            ) : (
                <SunIcon className="size-4" />
            )}
            <span className="sr-only">Toggle theme</span>
        </Button>
    );
}
