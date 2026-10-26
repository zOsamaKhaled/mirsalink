import { Users } from 'lucide-react';
import { useEffect, useRef } from 'react';

import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { QuickReply } from '@/types/templates';

/** The replies whose shortcut or title holds what follows the "/". */
export function matchQuickReplies(
    replies: QuickReply[],
    query: string,
): QuickReply[] {
    const term = query.trim().toLowerCase();

    if (term === '') {
        return replies;
    }

    return replies.filter(
        (reply) =>
            reply.shortcut.toLowerCase().includes(term) ||
            reply.title.toLowerCase().includes(term),
    );
}

/** {name} and {agent} put in place, as the reply's form promises. */
export function fillQuickReply(
    body: string,
    names: { customer: string | null; agent: string },
): string {
    return body
        .replaceAll('{name}', names.customer ?? '')
        .replaceAll('{agent}', names.agent)
        .replace(/ {2,}/g, ' ');
}

/**
 * The list that opens above the composer when a member types "/" or presses
 * the quick replies button. The keyboard moves through it from the
 * textarea (see the composer); a click picks too.
 */
export function QuickReplyPicker({
    replies,
    highlighted,
    onPick,
    onHover,
}: {
    replies: QuickReply[];
    highlighted: number;
    onPick: (reply: QuickReply) => void;
    onHover: (position: number) => void;
}) {
    const { t } = useTranslations();
    const list = useRef<HTMLUListElement>(null);

    useEffect(() => {
        list.current
            ?.querySelector(`[data-position="${highlighted}"]`)
            ?.scrollIntoView({ block: 'nearest' });
    }, [highlighted]);

    return (
        <div
            className="absolute inset-x-3 bottom-full z-20 mb-2 overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-lg"
            role="listbox"
            aria-label={t('inbox.composer.quick_replies')}
        >
            {replies.length === 0 ? (
                <p className="px-4 py-3 text-body-sm text-muted-foreground">
                    {t('inbox.composer.no_quick_replies')}
                </p>
            ) : (
                <ul ref={list} className="max-h-72 overflow-y-auto p-1">
                    {replies.map((reply, position) => (
                        <li
                            key={reply.id}
                            data-position={position}
                            role="option"
                            aria-selected={position === highlighted}
                            onMouseDown={(event) => {
                                // Keeps the textarea focused.
                                event.preventDefault();
                                onPick(reply);
                            }}
                            onMouseEnter={() => onHover(position)}
                            className={cn(
                                'flex cursor-pointer flex-col gap-0.5 rounded-lg px-3 py-2',
                                position === highlighted && 'bg-accent',
                            )}
                        >
                            <span className="flex items-center gap-2">
                                <bdi
                                    dir="ltr"
                                    className="font-mono text-caption text-muted-foreground"
                                >
                                    /{reply.shortcut}
                                </bdi>
                                <span className="truncate text-body-sm font-semibold">
                                    {reply.title}
                                </span>
                                {reply.shared && (
                                    <Users className="ms-auto size-3.5 shrink-0 text-muted-foreground" />
                                )}
                            </span>
                            <span
                                dir="auto"
                                className="line-clamp-1 text-caption text-muted-foreground"
                            >
                                {reply.body}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
