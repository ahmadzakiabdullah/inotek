'use client';

import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppearance } from '@/hooks/use-appearance';

export function ColorModeSelector() {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <div className="flex flex-col gap-4">
            <Label htmlFor="roundedCorner">Color mode:</Label>
            <ToggleGroup
                value={appearance}
                type="single"
                onValueChange={(value) =>
                    value && updateAppearance(value as any)
                }
                className="w-full gap-4 *:rounded-md *:border *:border-input"
            >
                <ToggleGroupItem
                    variant="outline"
                    value="light"
                    className="flex-1"
                >
                    Light
                </ToggleGroupItem>
                <ToggleGroupItem
                    variant="outline"
                    value="dark"
                    className="flex-1"
                >
                    Dark
                </ToggleGroupItem>
                <ToggleGroupItem
                    variant="outline"
                    value="system"
                    className="flex-1"
                >
                    System
                </ToggleGroupItem>
            </ToggleGroup>
        </div>
    );
}
