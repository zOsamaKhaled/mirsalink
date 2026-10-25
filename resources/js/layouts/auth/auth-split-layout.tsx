import { Link } from '@inertiajs/react';

import { MirsalinkWordmark } from '@/components/brand/mirsalink-logo';
import { HandoverMark } from '@/components/inbox/handover-mark';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { useTranslations } from '@/hooks/use-translations';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * The form on the start side, and on the end side a navy panel that tells
 * what the platform does in one scene: a customer asks, the assistant
 * hands them to a person. On phones only the form shows.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { t, locale } = useTranslations();
    const lang = locale === 'ar' ? 'ar' : 'en';

    return (
        <div className="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
            <div className="flex flex-col px-4 py-6 sm:px-8">
                <div className="flex items-center justify-between">
                    <Link href={home()} className="lg:invisible">
                        <MirsalinkWordmark lang={lang} className="text-xl" />
                    </Link>
                    <LocaleSwitcher />
                </div>

                <main className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-8 py-10">
                    <div className="flex flex-col gap-2">
                        <h1 className="text-display font-extrabold">{title}</h1>
                        {description && (
                            <p className="text-body text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {children}
                </main>
            </div>

            <aside className="relative hidden overflow-hidden bg-brand-navy p-10 text-white lg:flex lg:flex-col">
                <MirsalinkWordmark
                    lang={lang}
                    tone="on-dark"
                    className="text-2xl"
                />

                <div className="my-auto flex max-w-md flex-col gap-10">
                    <div className="flex flex-col gap-3">
                        <p className="text-hero font-extrabold">
                            {t('auth.panel.headline')}
                        </p>
                        <p className="text-title text-white/75">
                            {t('auth.panel.body')}
                        </p>
                    </div>

                    <HandoverScene />
                </div>

                <svg
                    aria-hidden
                    viewBox="0 0 48 48"
                    className="pointer-events-none absolute -end-24 -bottom-24 size-[28rem] opacity-[0.07]"
                >
                    <g
                        fill="none"
                        strokeWidth="8"
                        strokeLinecap="round"
                        transform="rotate(-35 24 24)"
                        stroke="#ffffff"
                    >
                        <path d="M6 19 H29" />
                        <path d="M19 29 H42" />
                    </g>
                </svg>
            </aside>
        </div>
    );
}

/** A customer's question and the handover that answers it, drawn small. */
function HandoverScene() {
    const { t } = useTranslations();

    return (
        <div aria-hidden className="flex flex-col gap-3">
            <p className="max-w-[80%] self-start rounded-2xl rounded-ss-md bg-white px-4 py-2.5 text-body text-brand-navy">
                {t('auth.panel.scene.customer')}
            </p>
            <p className="inline-flex items-center gap-2 self-center rounded-full bg-white/10 px-3 py-1 text-caption text-white">
                <HandoverMark className="size-4 text-white" />
                {t('auth.panel.scene.handover')}
            </p>
            <p className="max-w-[80%] self-end rounded-2xl rounded-se-md bg-white/15 px-4 py-2.5 text-body text-white">
                {t('auth.panel.scene.agent')}
            </p>
        </div>
    );
}
