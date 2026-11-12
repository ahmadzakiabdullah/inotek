import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Building2, Mail, MapPin, Phone } from 'lucide-react';

const facultyWebsite = 'https://ftkek.utem.edu.my/';

export default function ContactIndex() {
    return (
        <>
            <Head title="Contact" />
            <section className="mx-auto max-w-7xl px-6 py-16 sm:py-20 lg:px-8 lg:py-24">
                <div className="mx-auto max-w-3xl text-center">
                    <p className="text-sm font-semibold tracking-[0.18em] text-primary uppercase">
                        INOTEK
                    </p>
                    <h1 className="mt-4 text-4xl font-bold tracking-tight sm:text-5xl">
                        Contact
                    </h1>
                    <p className="mt-5 text-lg leading-8 text-muted-foreground">
                        For enquiries about INOTEK, contact the Secretariat at
                        the Faculty of Electronic and Computer Technology and
                        Engineering, Universiti Teknikal Malaysia Melaka.
                    </p>
                </div>

                <div className="mx-auto mt-12 grid max-w-4xl gap-5 md:grid-cols-2">
                    <article className="rounded-2xl border border-border bg-card p-7 shadow-sm sm:p-8">
                        <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Mail className="size-5" aria-hidden="true" />
                        </div>
                        <h2 className="mt-6 text-xl font-semibold">INOTEK Secretariat</h2>
                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                            For INOTEK enquiries, contact the Faculty of
                            Electronic and Computer Technology and Engineering.
                        </p>
                        <div className="mt-6 space-y-3 text-sm">
                            <a className="flex items-center gap-3 font-medium hover:text-primary" href="tel:+6062292118">
                                <Phone className="size-4 text-muted-foreground" aria-hidden="true" />
                                +606-2292118
                            </a>
                            <a className="flex items-center gap-3 font-medium hover:text-primary" href="mailto:ftkek@utem.edu.my">
                                <Mail className="size-4 text-muted-foreground" aria-hidden="true" />
                                ftkek@utem.edu.my
                            </a>
                        </div>
                    </article>

                    <article className="rounded-2xl border border-border bg-card p-7 shadow-sm sm:p-8">
                        <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Building2 className="size-5" aria-hidden="true" />
                        </div>
                        <h2 className="mt-6 text-xl font-semibold">Faculty</h2>
                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                            Faculty of Electronic and Computer Technology and
                            Engineering, Universiti Teknikal Malaysia Melaka.
                        </p>
                        <div className="mt-5 flex items-start gap-3 text-sm leading-6 text-muted-foreground">
                            <MapPin className="mt-1 size-4 shrink-0" aria-hidden="true" />
                            <span>
                                Hang Tuah Jaya, 76100 Durian Tunggal,
                                Melaka, Malaysia.
                            </span>
                        </div>
                        <a
                            href={facultyWebsite}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-6 inline-flex items-center gap-2 text-sm font-medium text-primary underline underline-offset-4 hover:opacity-80"
                        >
                            Visit faculty website
                            <ArrowUpRight className="size-4" aria-hidden="true" />
                        </a>
                    </article>
                </div>

                <div className="mx-auto mt-10 max-w-4xl rounded-2xl bg-muted/40 px-6 py-5 text-center text-sm text-muted-foreground">
                    <p>
                        INOTEK — Universiti Teknikal Malaysia Melaka
                    </p>
                    <Link href="/" className="mt-2 inline-block font-medium text-foreground underline underline-offset-4">
                        Return to homepage
                    </Link>
                </div>
            </section>
        </>
    );
}
