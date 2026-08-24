import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    BarChart3,
    Check,
    ChevronRight,
    Layers3,
    Menu,
    ShieldCheck,
    Sparkles,
    Trophy,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { dashboard, login, register } from '@/routes';

interface Showcase {
    submissions: number;
    categories: number;
    progress: number;
    rankings: { name: string; score: number }[];
}

export default function Welcome() {
    const { auth, showcase } = usePage().props as unknown as {
        auth: { user?: unknown };
        showcase: Showcase;
    };
    const [open, setOpen] = useState(false);

    return (
        <>
            <Head title="Innovation, organised" />
            <div className="min-h-screen overflow-hidden bg-background text-foreground">
                <header className="relative z-20 border-b border-border/60 bg-background/80 backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-6 lg:px-8">
                        <a href="#top" className="flex items-center gap-3">
                            <img
                                src="/logo.png"
                                alt="Inotek"
                                className="size-10 rounded-xl shadow-sm"
                            />
                            <div>
                                <p className="text-base font-semibold tracking-tight">
                                    INOTEK
                                </p>
                                <p className="text-[10px] font-medium tracking-[0.22em] text-muted-foreground">
                                    INNOVATION PLATFORM
                                </p>
                            </div>
                        </a>
                        <nav className="hidden items-center gap-8 text-sm text-muted-foreground md:flex">
                            <a
                                href="#about"
                                className="transition-colors hover:text-foreground"
                            >
                                About
                            </a>
                            <Link
                                href="/leaderboard"
                                className="transition-colors hover:text-foreground"
                            >
                                Leaderboard
                            </Link>
                            <a
                                href="#features"
                                className="transition-colors hover:text-foreground"
                            >
                                Features
                            </a>
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
                                <a href="#about" onClick={() => setOpen(false)}>
                                    About
                                </a>
                                <Link href="/leaderboard">Leaderboard</Link>
                                <Link href={auth.user ? dashboard() : login()}>
                                    {auth.user ? 'Dashboard' : 'Log in'}
                                </Link>
                            </div>
                        </div>
                    )}
                </header>
                <main id="top">
                    <section className="relative mx-auto max-w-7xl px-6 pt-20 pb-24 lg:px-8 lg:pt-28 lg:pb-32">
                        <div className="pointer-events-none absolute -top-24 -right-40 size-[34rem] rounded-full bg-primary/[0.06] blur-3xl" />
                        <div className="relative grid items-center gap-16 lg:grid-cols-[1.05fr_.95fr]">
                            <div>
                                <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-border bg-muted/50 px-3 py-1.5 text-xs font-medium text-muted-foreground">
                                    <Sparkles className="size-3.5 text-primary" />{' '}
                                    Built for meaningful ideas
                                </div>
                                <h1 className="max-w-3xl text-5xl font-bold tracking-[-0.045em] sm:text-6xl lg:text-7xl">
                                    Turn bold ideas into{' '}
                                    <span className="text-muted-foreground">
                                        real impact.
                                    </span>
                                </h1>
                                <p className="mt-7 max-w-xl text-lg leading-8 text-muted-foreground">
                                    INOTEK brings innovators, reviewers, and
                                    organisers together in one clear, connected
                                    competition platform.
                                </p>
                                <div className="mt-9 flex flex-wrap items-center gap-4">
                                    <Link
                                        href={
                                            auth.user ? dashboard() : register()
                                        }
                                        className="rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground shadow-lg shadow-primary/10 transition hover:-translate-y-0.5"
                                    >
                                        Explore the platform{' '}
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
                                            <Rank key={`${rank.name}-${index}`} name={rank.name} place={String(index + 1).padStart(2, '0')} score={rank.score.toString()} />
                                        )) : <p className="text-sm text-muted-foreground">No approved projects yet.</p>}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section
                        id="features"
                        className="border-y border-border/70 bg-muted/30"
                    >
                        <div className="mx-auto max-w-7xl px-6 py-20 lg:px-8">
                            <div className="max-w-xl">
                                <p className="text-sm font-semibold text-muted-foreground">
                                    ONE SHARED WORKSPACE
                                </p>
                                <h2 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                    From first submission to final award.
                                </h2>
                            </div>
                            <div className="mt-12 grid gap-5 md:grid-cols-3">
                                <Feature
                                    icon={Layers3}
                                    title="Organise everything"
                                    text="Manage sessions, categories, rubrics, and submissions from one focused workspace."
                                />
                                <Feature
                                    icon={ShieldCheck}
                                    title="Evaluate with confidence"
                                    text="Give judges the structure and insight they need for fair, transparent scoring."
                                />
                                <Feature
                                    icon={Trophy}
                                    title="Make results visible"
                                    text="Celebrate progress with live rankings, awards, and a polished public showcase."
                                />
                            </div>
                        </div>
                    </section>
                    <section
                        id="about"
                        className="mx-auto max-w-7xl px-6 py-24 lg:px-8"
                    >
                        <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                            <div>
                                <p className="text-sm font-semibold text-muted-foreground">
                                    DESIGNED FOR PEOPLE WHO BUILD
                                </p>
                                <h2 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                    A better stage for the next big idea.
                                </h2>
                                <p className="mt-5 max-w-lg leading-7 text-muted-foreground">
                                    Whether you are submitting a project,
                                    reviewing a breakthrough, or running the
                                    programme, INOTEK keeps every moment moving
                                    forward.
                                </p>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Mini
                                    icon={Users}
                                    title="One community"
                                    text="Connect teams, mentors, judges, and organisers."
                                />
                                <Mini
                                    icon={Check}
                                    title="Clear progress"
                                    text="Know what is next at every stage of the journey."
                                />
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
function Rank({
    name,
    place,
    score,
}: {
    name: string;
    place: string;
    score: string;
}) {
    return (
        <div className="flex items-center gap-3 text-sm">
            <span className="flex size-8 items-center justify-center rounded-lg bg-muted text-xs font-semibold text-muted-foreground">
                {place}
            </span>
            <span className="flex-1 font-medium">{name}</span>
            <span className="font-semibold">
                {score}
                <span className="ml-1 text-xs font-normal text-muted-foreground">
                    pts
                </span>
            </span>
        </div>
    );
}
function Feature({
    icon: Icon,
    title,
    text,
}: {
    icon: typeof Layers3;
    title: string;
    text: string;
}) {
    return (
        <div className="rounded-2xl border border-border bg-background p-6">
            <div className="mb-8 flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                <Icon className="size-5" />
            </div>
            <h3 className="text-lg font-semibold">{title}</h3>
            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                {text}
            </p>
        </div>
    );
}
function Mini({
    icon: Icon,
    title,
    text,
}: {
    icon: typeof Users;
    title: string;
    text: string;
}) {
    return (
        <div className="rounded-2xl border border-border p-5">
            <Icon className="size-5" />
            <h3 className="mt-5 font-semibold">{title}</h3>
            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                {text}
            </p>
        </div>
    );
}
