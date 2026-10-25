import { cn } from '@/lib/utils';

/**
 * The handover: two rounded bars passing one another, the navy one the
 * platform and the rose one the person it hands the conversation to.
 * Drawn on a 48 × 48 grid; the geometry must not change.
 *
 * auto: navy on light themes, white on dark (the default inside the app).
 * color: always navy, for artwork that sits on white whatever the theme.
 */
type Tone = 'auto' | 'color' | 'on-dark' | 'mono';

const BAR_COLORS: Record<Tone, { first: string; second: string }> = {
    auto: { first: 'var(--logo-ink)', second: 'var(--brand-rose)' },
    color: { first: 'var(--brand-navy)', second: 'var(--brand-rose)' },
    'on-dark': { first: '#ffffff', second: 'var(--brand-rose)' },
    mono: { first: 'currentColor', second: 'currentColor' },
};

function Bars({ tone }: { tone: Tone }) {
    const { first, second } = BAR_COLORS[tone];

    return (
        <g
            fill="none"
            strokeWidth="8"
            strokeLinecap="round"
            transform="rotate(-35 24 24)"
        >
            <path stroke={first} d="M6 19 H29" />
            <path stroke={second} d="M19 29 H42" />
        </g>
    );
}

export function MirsalinkSymbol({
    tone = 'auto',
    className,
    title,
}: {
    tone?: Tone;
    className?: string;
    title?: string;
}) {
    return (
        <svg
            viewBox="0 0 48 48"
            xmlns="http://www.w3.org/2000/svg"
            className={cn('size-8 shrink-0', className)}
            role={title ? 'img' : undefined}
            aria-label={title}
            aria-hidden={title ? undefined : true}
        >
            <Bars tone={tone} />
        </svg>
    );
}

/** The symbol in white and rose on a navy rounded square. */
export function MirsalinkAppIcon({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 48 48"
            xmlns="http://www.w3.org/2000/svg"
            className={cn('size-8 shrink-0', className)}
            aria-hidden
        >
            <rect width="48" height="48" rx="11" fill="var(--brand-navy)" />
            <g transform="translate(7.2 7.2) scale(.7)">
                <Bars tone="on-dark" />
            </g>
        </svg>
    );
}

/**
 * The name beside the symbol. The two words share their l / ل, and the
 * wordmark gives that one letter the rose: mirsa·l·ink, ميرسا·ل·ينك.
 */
export function MirsalinkWordmark({
    lang = 'ar',
    tone = 'auto',
    className,
}: {
    lang?: 'ar' | 'en';
    tone?: Tone;
    className?: string;
}) {
    const [before, shared, after] =
        lang === 'ar' ? ['ميرسا', 'ل', 'ينك'] : ['mirsa', 'l', 'ink'];

    return (
        <span
            dir={lang === 'ar' ? 'rtl' : 'ltr'}
            lang={lang}
            className={cn(
                'inline-flex items-center gap-[0.35em] text-2xl leading-none',
                tone === 'auto' && 'text-logo-ink',
                tone === 'color' && 'text-brand-navy',
                tone === 'on-dark' && 'text-white',
                className,
            )}
        >
            <MirsalinkSymbol tone={tone} className="size-[1.25em]" />
            <span
                className={cn(
                    lang === 'ar'
                        ? 'pt-[0.12em] font-brand-arabic text-[1.12em]'
                        : 'font-brand-latin font-extrabold tracking-[-0.01em]',
                )}
            >
                {before}
                <span
                    className={tone === 'mono' ? undefined : 'text-brand-rose'}
                >
                    {shared}
                </span>
                {after}
            </span>
        </span>
    );
}
