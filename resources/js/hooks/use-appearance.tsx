import { useSyncExternalStore } from 'react';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'system';

/**
 * Public portal and sign-in pages are always shown in light mode: they have
 * no theme switch, so visitors whose device prefers dark mode could not
 * change it. Keep in sync with HandleAppearance::LIGHT_ONLY_PATHS on the server.
 */
const LIGHT_ONLY_PATHS = [
    /^\/$/,
    /^\/leaderboard\/?$/,
    /^\/contact\/?$/,
    /^\/verify\//,
    /^\/(login|register|forgot-password|two-factor-challenge|email\/verify|user\/confirm-password)\/?$/,
    /^\/reset-password\//,
];
let forceLight = false;

export const isLightOnlyPath = (path: string): boolean =>
    LIGHT_ONLY_PATHS.some((pattern) => pattern.test(path));

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredAppearance = (): Appearance => {
    if (typeof window === 'undefined') {
        return 'system';
    }

    return (localStorage.getItem('appearance') as Appearance) || 'system';
};

const isDarkMode = (appearance: Appearance): boolean => {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
};

const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = !forceLight && isDarkMode(appearance);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const handleSystemThemeChange = (): void => applyTheme(currentAppearance);

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (!localStorage.getItem('appearance')) {
        localStorage.setItem('appearance', 'system');
        setCookie('appearance', 'system');
    }

    currentAppearance = getStoredAppearance();
    forceLight = isLightOnlyPath(window.location.pathname);
    applyTheme(currentAppearance);

    // Set up system theme change listener
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

/**
 * Re-apply the theme after an Inertia visit, so moving between the public
 * portal and the dashboard switches between forced light mode and the
 * user's own choice.
 */
export function syncThemeWithPath(path: string): void {
    const next = isLightOnlyPath(path);

    if (next === forceLight) {
        return;
    }

    forceLight = next;
    applyTheme(currentAppearance);
    notify();
}

export function useAppearance(): UseAppearanceReturn {
    const appearance: Appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => 'system',
    );

    const forcedLight = useSyncExternalStore(
        subscribe,
        () => forceLight,
        () => false,
    );

    const resolvedAppearance: ResolvedAppearance =
        !forcedLight && isDarkMode(appearance) ? 'dark' : 'light';

    const updateAppearance = (mode: Appearance): void => {
        currentAppearance = mode;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', mode);

        // Store in cookie for SSR...
        setCookie('appearance', mode);

        applyTheme(mode);
        notify();
    };

    return { appearance, resolvedAppearance, updateAppearance } as const;
}
