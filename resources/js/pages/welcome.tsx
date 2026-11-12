import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    BarChart3,
    ChevronRight,
    Menu,
    Sparkles,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { dashboard, login, register } from '@/routes';

interface Showcase {
    submissions: number;
    categories: number;
    categoryList: { code: string; name: string }[];
    progress: number;
    rankings: { name: string }[];
}

interface RegistrationInfo {
    isOpen: boolean;
    mode: 'scheduled' | 'open' | 'closed' | null;
    sessionHasStarted: boolean;
    opensAt: string | null;
    closesAt: string | null;
}

export default function Welcome() {
    const { auth, showcase, activeSession, registration } = usePage().props as unknown as {
        auth: { user?: unknown };
        showcase: Showcase;
        activeSession: string | null;
        registration: RegistrationInfo;
    };
    const [open, setOpen] = useState(false);

    return (
        <>
            <Head title="Innovation and Technology Competition" />
            <div className="min-h-screen overflow-hidden bg-background text-foreground">
                <header className="relative z-20 border-b border-border/60 bg-background/80 backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-6 lg:px-8">
                        <a href="/" className="flex items-center gap-3">
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
                        </a>
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
                                <Link href="/leaderboard" onClick={() => setOpen(false)}>Leaderboard</Link>
                                <Link href="/contact" onClick={() => setOpen(false)}>Contact</Link>
                                <Link href={auth.user ? dashboard() : login()}>
                                    {auth.user ? 'Dashboard' : 'Log in'}
                                </Link>
                            </div>
                        </div>
                    )}
                </header>
                <main>
                    <section className="relative mx-auto max-w-7xl px-6 pt-20 pb-24 lg:px-8 lg:pt-28 lg:pb-32">
                        <div className="pointer-events-none absolute -top-24 -right-40 size-[34rem] rounded-full bg-primary/[0.06] blur-3xl" />
                        <div className="relative grid items-center gap-16 lg:grid-cols-[1.05fr_.95fr]">
                            <div>
                                <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-border bg-muted/50 px-3 py-1.5 text-xs font-medium text-muted-foreground">
                                    <Sparkles className="size-3.5 text-primary" />{' '}
                                    Built for meaningful ideas
                                </div>
                                <h1 className="max-w-3xl text-5xl font-bold tracking-[-0.045em] sm:text-6xl lg:text-7xl">
                                    INOTEK
                                </h1>
                                <p className="mt-7 max-w-xl text-lg leading-8 text-muted-foreground">
                                    <a href="https://ftkek.utem.edu.my/" className="underline decoration-border underline-offset-4 transition-colors hover:text-foreground">
                                        Faculty of Electronics and Computer Technology and Engineering
                                    </a>
                                </p>
                                <div className="mt-9 flex flex-wrap items-center gap-4">
                                    <Link
                                        href={auth.user ? dashboard() : login()}
                                        className="rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground shadow-lg shadow-primary/10 transition hover:-translate-y-0.5"
                                    >
                                        {auth.user ? 'Open dashboard' : 'Log in'}{' '}
                                        <ChevronRight className="ml-1 inline size-4" />
                                    </Link>
                                    <Link
                                        href="/leaderboard"
                                        className="rounded-xl border border-border px-5 py-3 text-sm font-semibold transition hover:bg-muted"
                                    >
                                        View leaderboard
                                    </Link>
                                </div>
                                <div className="mt-12 flex items-center gap-8 text-sm text-muted-foreground">
                                    <span>
                                        <strong className="text-foreground">
                                            01
                                        </strong>{' '}
                                        Submit
                                    </span>
                                    <span>
                                        <strong className="text-foreground">
                                            02
                                        </strong>{' '}
                                        Evaluate
                                    </span>
                                    <span>
                                        <strong className="text-foreground">
                                            03
                                        </strong>{' '}
                                        Celebrate
                                    </span>
                                </div>
                            </div>
                            <div className="relative">
                                <div className="absolute -inset-5 rounded-[2rem] bg-gradient-to-br from-primary/[0.12] via-transparent to-muted blur-2xl" />
                                <div className="relative rounded-3xl border border-border bg-card p-5 shadow-2xl shadow-primary/5 sm:p-7">
                                    <div className="flex items-center justify-between border-b border-border pb-5">
                                        <div>
                                            <p className="text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase">
                                                Live overview
                                            </p>
                                            <h2 className="mt-1 text-xl font-semibold">
                                                Innovation showcase
                                            </h2>
                                        </div>
                                        <div className="rounded-xl bg-primary/10 p-3 text-primary">
                                            <BarChart3 className="size-5" />
                                        </div>
                                    </div>
                                    <div className="mt-6 grid grid-cols-2 gap-3">
                                        <Stat label="Submissions" value={showcase.submissions.toString()} />
                                        <Stat label="Categories" value={showcase.categories.toString()} />
                                    </div>
                                    <div className="mt-5 rounded-2xl border border-border/70 p-4">
                                        <div className="mb-3">
                                            <p id="categories" className="text-sm font-semibold">Competition categories</p>
                                            {activeSession && <p className="mt-1 text-xs text-muted-foreground">{activeSession}</p>}
                                        </div>
                                        {showcase.categoryList.length > 0 ? (
                                            <ul className="grid gap-2 sm:grid-cols-2">
                                                {showcase.categoryList.map((category) => (
                                                    <li key={category.code} className="rounded-xl bg-muted/60 px-3 py-2 text-sm">
                                                        <span className="mr-2 font-semibold text-primary">{category.code}</span>
                                                        <span>{category.name}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : <p className="text-sm text-muted-foreground">No categories listed for the active session.</p>}
                                    </div>
                                    <div className="mt-4 rounded-2xl border border-border/70 px-4 py-3 text-sm">
                                        <div className="flex items-center gap-2 font-semibold">
                                            <span className={`size-2 rounded-full ${registration.isOpen ? 'bg-emerald-500' : 'bg-muted-foreground/50'}`} />
                                            {registration.isOpen ? 'Registration is open' : 'Registration is closed'}
                                        </div>
                                        {registration.mode === 'open' && <p className="mt-1 text-xs text-muted-foreground">Registration is open for this session.</p>}
                                        {registration.mode === 'closed' && <p className="mt-1 text-xs text-muted-foreground">Registration is currently closed for this session.</p>}
                                        {registration.mode === 'scheduled' && registration.opensAt && !registration.sessionHasStarted && <p className="mt-1 text-xs text-muted-foreground">The session has not started yet. Registration opens on {formatDate(registration.opensAt)}.</p>}
                                        {registration.mode === 'scheduled' && registration.opensAt && registration.sessionHasStarted && !registration.isOpen && <p className="mt-1 text-xs text-muted-foreground">The registration window has ended or is not currently available.</p>}
                                        {registration.mode === 'scheduled' && registration.opensAt && registration.closesAt && (
                                            <p className="mt-1 text-xs text-muted-foreground">Registration period: {formatDate(registration.opensAt)} – {formatDate(registration.closesAt)}{registration.isOpen ? ` · Closes ${formatDate(registration.closesAt)}` : ''}</p>
                                        )}
                                        {!activeSession && <p className="mt-1 text-xs text-muted-foreground">There is no active competition session at the moment.</p>}
                                    </div>
                                    <div className="mt-5 rounded-2xl bg-muted/60 p-4">
                                        <div className="mb-5 flex justify-between text-sm">
                                            <span className="font-medium">
                                                Evaluation progress
                                            </span>
                                            <span className="text-muted-foreground">
                                                {showcase.progress}%
                                            </span>
                                        </div>
                                        <div className="h-2 rounded-full bg-border">
                                            <div className="h-2 rounded-full bg-primary" style={{ width: `${showcase.progress}%` }} />
                                        </div>
                                        <div className="mt-4 flex justify-between text-xs text-muted-foreground">
                                            <span>Round 1</span>
                                            <span>Final review</span>
                                        </div>
                                    </div>
                                    <div className="mt-5 space-y-3">
                                        {showcase.rankings.length > 0 ? showcase.rankings.map((rank, index) => (
                                            <Rank key={`${rank.name}-${index}`} name={rank.name} place={String(index + 1).padStart(2, '0')} />
                                        )) : <p className="text-sm text-muted-foreground">No approved projects yet.</p>}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
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
        </>
    );
}
function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-2xl border border-border bg-background p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-2 text-2xl font-semibold tracking-tight">
                {value}
            </p>
        </div>
    );
}
function formatDate(value: string) {
    return new Intl.DateTimeFormat('en', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`));
}
function Rank({
    name,
    place,
}: {
    name: string;
    place: string;
}) {
    return (
        <div className="flex items-center gap-3 text-sm">
            <span className="flex size-8 items-center justify-center rounded-lg bg-muted text-xs font-semibold text-muted-foreground">
                {place}
            </span>
            <span className="flex-1 font-medium">{name}</span>
        </div>
    );
}
