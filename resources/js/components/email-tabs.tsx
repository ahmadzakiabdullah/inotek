import { Link } from '@inertiajs/react';
import { FileText, ScrollText, Settings2 } from 'lucide-react';
import { cn } from '@/lib/utils';

const tabs = [
    { key: 'settings', label: 'Settings', href: '/dashboard/email', icon: Settings2 },
    { key: 'templates', label: 'Templates', href: '/dashboard/email/templates', icon: FileText },
    { key: 'logs', label: 'Logs', href: '/dashboard/email/logs', icon: ScrollText },
] as const;

export type EmailTab = (typeof tabs)[number]['key'];

/**
 * Navigation between the three Email & Notifications pages.
 */
export default function EmailTabs({ active }: { active: EmailTab }) {
    return (
        <nav aria-label="Email & Notifications" className="flex gap-1 overflow-x-auto border-b border-border">
            {tabs.map((tab) => {
                const Icon = tab.icon;
                const isActive = tab.key === active;

                return (
                    <Link
                        key={tab.key}
                        href={tab.href}
                        aria-current={isActive ? 'page' : undefined}
                        className={cn(
                            '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                            isActive
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <Icon className="h-4 w-4" />
                        {tab.label}
                    </Link>
                );
            })}
        </nav>
    );
}
