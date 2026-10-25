import { Bot } from 'lucide-react';

import { CategoryTag } from '@/components/inbox/category-tag';
import type { TagTone } from '@/components/inbox/category-tag';
import { HandoverMark } from '@/components/inbox/handover-mark';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

export type ConversationHandler =
    | { kind: 'bot'; label: string }
    | { kind: 'agent'; name: string; initials: string };

/**
 * One customer in the inbox list. Unread messages make the name bold and
 * put a rose count at the end; a conversation the bot handed over and no one
 * has taken yet shows the handover mark before the name.
 */
export function ConversationItem({
    name,
    initials,
    preview,
    time,
    unread = 0,
    tags = [],
    handler,
    waitingForPerson = false,
    selected = false,
    closed = false,
    unreadLabel,
    onClick,
}: {
    name: string;
    initials: string;
    preview: string;
    time: string;
    unread?: number;
    tags?: { label: string; tone: TagTone; byBot?: boolean }[];
    handler?: ConversationHandler;
    waitingForPerson?: boolean;
    selected?: boolean;
    closed?: boolean;
    /** e.g. "3 unread messages", read out instead of the bare number. */
    unreadLabel?: string;
    onClick?: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-current={selected ? 'true' : undefined}
            className={cn(
                'flex w-full items-start gap-3 rounded-xl px-3 py-3 text-start transition-colors',
                'hover:bg-accent/70 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                selected && 'bg-accent hover:bg-accent',
                closed && 'opacity-70',
            )}
        >
            <Avatar className="size-10">
                <AvatarFallback className="bg-secondary font-display text-body-sm font-semibold text-secondary-foreground">
                    {initials}
                </AvatarFallback>
            </Avatar>

            <span className="flex min-w-0 flex-1 flex-col gap-1">
                <span className="flex items-center gap-2">
                    {waitingForPerson && <HandoverMark className="size-4" />}
                    <span
                        className={cn(
                            'min-w-0 flex-1 truncate text-body',
                            unread > 0 ? 'font-bold' : 'font-medium',
                        )}
                    >
                        {name}
                    </span>
                    <time
                        dir="ltr"
                        className={cn(
                            'shrink-0 text-caption tabular',
                            unread > 0
                                ? 'font-semibold text-handover-ink'
                                : 'text-muted-foreground',
                        )}
                    >
                        {time}
                    </time>
                </span>

                <span className="flex items-center gap-2">
                    <span
                        className={cn(
                            'min-w-0 flex-1 truncate text-body-sm',
                            unread > 0
                                ? 'text-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        {preview}
                    </span>
                    {unread > 0 && (
                        <span
                            className="grid h-5 min-w-5 shrink-0 place-items-center rounded-full bg-handover px-1.5 text-caption font-bold text-handover-foreground tabular"
                            aria-label={unreadLabel}
                        >
                            {unread}
                        </span>
                    )}
                </span>

                {(tags.length > 0 || handler) && (
                    <span className="flex flex-wrap items-center gap-1.5 pt-0.5">
                        {tags.map((tag) => (
                            <CategoryTag
                                key={tag.label}
                                tone={tag.tone}
                                byBot={tag.byBot}
                            >
                                {tag.label}
                            </CategoryTag>
                        ))}
                        {handler && (
                            <span className="ms-auto inline-flex items-center gap-1 text-caption text-muted-foreground">
                                {handler.kind === 'bot' ? (
                                    <>
                                        <Bot className="size-3.5" />
                                        {handler.label}
                                    </>
                                ) : (
                                    <>
                                        <span className="grid size-5 place-items-center rounded-full bg-primary text-[0.625rem] font-bold text-primary-foreground">
                                            {handler.initials}
                                        </span>
                                        {handler.name}
                                    </>
                                )}
                            </span>
                        )}
                    </span>
                )}
            </span>
        </button>
    );
}
