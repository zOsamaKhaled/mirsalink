import { AlertCircle, Bot, NotebookPen } from 'lucide-react';
import type { ReactNode } from 'react';

import { MessageStatus } from '@/components/inbox/message-status';
import type { DeliveryStatus } from '@/components/inbox/message-status';
import { cn } from '@/lib/utils';

/**
 * Who a line in the conversation came from. The customer writes on the
 * start side, the team on the end side (right and left in Arabic, the other
 * way in English). The bot's lines are pale navy, a person's solid navy, and
 * an internal note, which the customer never sees, sits on paper yellow.
 */
export type BubbleKind = 'customer' | 'agent' | 'bot' | 'note';

const KIND_CLASSES: Record<BubbleKind, string> = {
    customer:
        'bg-bubble-in text-bubble-in-foreground rounded-ss-md shadow-raised',
    agent: 'bg-bubble-out text-bubble-out-foreground rounded-se-md',
    bot: 'bg-bubble-bot text-bubble-bot-foreground rounded-se-md',
    note: 'bg-bubble-note text-bubble-note-foreground border border-dashed border-bubble-note-border',
};

export function MessageBubble({
    kind,
    children,
    time,
    author,
    status,
    failure,
}: {
    kind: BubbleKind;
    children: ReactNode;
    time: string;
    /** The agent's name, the bot's label or "Internal note"; customers need none. */
    author?: string;
    status?: { status: DeliveryStatus; label: string };
    /** Shown under a message WhatsApp refused, with what to do about it. */
    failure?: ReactNode;
}) {
    const fromTeam = kind !== 'customer';

    return (
        <div
            className={cn(
                'flex flex-col gap-1',
                fromTeam ? 'items-end' : 'items-start',
            )}
        >
            <div
                className={cn(
                    'max-w-[min(32rem,80%)] rounded-2xl px-3.5 py-2.5',
                    KIND_CLASSES[kind],
                    failure && 'ring-2 ring-danger/50',
                )}
            >
                {author && (
                    <div className="mb-1 flex items-center gap-1.5 text-caption font-semibold opacity-80">
                        {kind === 'bot' && <Bot className="size-3.5" />}
                        {kind === 'note' && (
                            <NotebookPen className="size-3.5" />
                        )}
                        {author}
                    </div>
                )}
                <div className="text-body break-words whitespace-pre-wrap">
                    {children}
                </div>
                <div className="mt-1 flex items-center justify-end gap-1 text-caption opacity-75">
                    <time dir="ltr" className="tabular">
                        {time}
                    </time>
                    {status && (
                        <MessageStatus
                            status={status.status}
                            label={status.label}
                        />
                    )}
                </div>
            </div>
            {failure && (
                <p className="flex items-center gap-1.5 text-caption text-danger">
                    <AlertCircle className="size-3.5" />
                    {failure}
                </p>
            )}
        </div>
    );
}
