import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Menu, X } from 'lucide-react';
import { useState } from 'react';
import { dashboard, login, register } from '@/routes';

export default function PublicLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);

    return (
        <div className="min-h-screen bg-background text-foreground">
            <header className="relative z-20 border-b border-border/60 bg-background/80 backdrop-blur-xl">
                <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-6 lg:px-8">
                    <Link href="/" className="flex items-center gap-3">
                        <img
                            src="/logo.svg"
                            alt="Logo Universiti Teknikal Malaysia Melaka (UTeM)"
                            className="h-11 w-auto max-w-40 object-contain"
                        />
                        <div>
                            <p className="text-base font-semibold tracking-tight">
                                INOTEK
                            </p>
                        </div>
                    </Link>
                    <nav className="hidden items-center gap-8 text-sm text-muted-foreground md:flex">
                        <Link
                            href="/leaderboard"
                            className="transition-colors hover:text-foreground"
                        >
                            Leaderboard
                        </Link>
                        <Link
                            href="/contact"
                            className="transition-colors hover:text-foreground"
                        >
                            Contact
                        </Link>
                    </nav>
                    <div className="hidden items-center gap-3 md:flex">
                        <Link
                            href={auth.user ? dashboard() : login()}
                            className="rounded-lg px-4 py-2 text-sm font-medium transition-colors hover:bg-muted"
                        >
                            {auth.user ? 'Dashboard' : 'Log in'}
                        </Link>
                        {!auth.user && (
                            <Link
                                href={register()}
                                className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90"
                            >
                                Get started{' '}
                                <ArrowUpRight className="ml-1 inline size-4" />
                            </Link>
                        )}
                    </div>
                    <button
                        onClick={() => setOpen(!open)}
                        className="rounded-lg p-2 md:hidden"
                        aria-label="Toggle menu"
                    >
                        {open ? <X /> : <Menu />}
                    </button>
                </div>
                {open && (
                    <div className="border-t border-border/60 px-6 py-4 md:hidden">
                        <div className="flex flex-col gap-4 text-sm">
                            <Link href="/leaderboard">Leaderboard</Link>
                            <Link href="/contact" onClick={() => setOpen(false)}>Contact</Link>
                            <Link href={auth.user ? dashboard() : login()}>
                                {auth.user ? 'Dashboard' : 'Log in'}
                            </Link>
                        </div>
                    </div>
                )}
            </header>
            <main>{children}</main>
            <footer className="border-t border-border/70">
                <div className="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between lg:px-8">
                    <span>
                        © {new Date().getFullYear()} INOTEK. Built for
                        innovation.
                    </span>
                    <span>UTeM Innovation & Technology Competition</span>
                </div>
            </footer>
        </div>
    );
}
