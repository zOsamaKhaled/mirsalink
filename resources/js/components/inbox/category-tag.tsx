import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * The seven colours a conversation's tags are drawn in. Rose is left out on
 * purpose: it belongs to the handover. A team gives each tag one of these.
 */
export const TAG_TONES = [
    'blue',
    'violet',
    'cyan',
    'amber',
    'slate',
    'red',
    'brown',
] as const;

export type TagTone = (typeof TAG_TONES)[number];

const TONE_CLASSES: Record<TagTone, string> = {
    blue: 'bg-tag-blue text-tag-blue-foreground',
    violet: 'bg-tag-violet text-tag-violet-foreground',
    cyan: 'bg-tag-cyan text-tag-cyan-foreground',
    amber: 'bg-tag-amber text-tag-amber-foreground',
    slate: 'bg-tag-slate text-tag-slate-foreground',
    red: 'bg-tag-red text-tag-red-foreground',
    brown: 'bg-tag-brown text-tag-brown-foreground',
};

export function CategoryTag({
    tone,
    children,
    byBot = false,
    className,
}: {
    tone: TagTone;
    children: ReactNode;
    /** A tag the bot set shows a small dot, so the team can tell it from their own. */
    byBot?: boolean;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-caption font-medium whitespace-nowrap',
                TONE_CLASSES[tone],
                className,
            )}
        >
            {byBot && (
                <span
                    className="size-1.5 rounded-full bg-current opacity-60"
                    aria-hidden
                />
            )}
            {children}
        </span>
    );
}
