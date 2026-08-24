import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function generateAvatarFallback(string: string) {
    const names = string.split(' ').filter((name: string) => name);
    const mapped = names.map((name: string) => name.charAt(0).toUpperCase());

    return mapped.join('');
}

export const getInitials = (fullName: string) => {
    const nameParts = fullName.split(' ');
    const firstNameInitial = nameParts[0]?.charAt(0).toUpperCase() || '';
    const lastNameInitial = nameParts[1]?.charAt(0).toUpperCase() || '';

    return `${firstNameInitial}${lastNameInitial}`;
};
