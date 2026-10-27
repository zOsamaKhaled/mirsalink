import {
    ArrowLeftRight,
    Bot,
    CircleCheck,
    FileText,
    MapPin,
    MoonStar,
    RotateCcw,
    Tag,
    UserRound,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Fragment, useLayoutEffect, useRef } from 'react';

import { MessageBubble } from '@/components/inbox/message-bubble';
import type { BubbleKind } from '@/components/inbox/message-bubble';
import { SystemEvent } from '@/components/inbox/system-event';
import { useTranslations } from '@/hooks/use-translations';
import { formatDay, formatFileSize, formatTime, isSameDay } from '@/lib/time';
import type { ThreadItem, ThreadMessage } from '@/types/inbox';

const EVENT_ICONS: Record<string, ReactNode> = {
    assigned: <UserRound className="size-3.5" />,
    transferred: <ArrowLeftRight className="size-3.5" />,
    unassigned: <UserRound className="size-3.5" />,
    tagged: <Tag className="size-3.5" />,
    untagged: <Tag className="size-3.5" />,
    closed: <CircleCheck className="size-3.5" />,
    reopened: <RotateCcw className="size-3.5" />,
    auto_replied: <MoonStar className="size-3.5" />,
    classified: <Bot className="size-3.5" />,
    assistant_toggled: <Bot className="size-3.5" />,
};

/**
 * The thread, oldest at the top, with a line for each new day. It keeps to
 * the bottom while the member is there, so new messages come into view,
 * and leaves them alone when they have scrolled up to read.
 */
export function ThreadMessages({ items }: { items: ThreadItem[] }) {
    const { t, locale } = useTranslations();
    const scroller = useRef<HTMLDivElement>(null);
    const atBottom = useRef(true);
    const last = items.at(-1);

    const stickToBottom = () => {
        if (atBottom.current && scroller.current) {
            scroller.current.scrollTop = scroller.current.scrollHeight;
        }
    };

    useLayoutEffect(stickToBottom, [last?.id, items.length]);

    return (
        <div
            ref={scroller}
            onScroll={(event) => {
                const element = event.currentTarget;
                atBottom.current =
                    element.scrollHeight -
                        element.scrollTop -
                        element.clientHeight <
                    96;
            }}
            className="min-h-0 flex-1 overflow-y-auto bg-sunken"
        >
            <div className="mx-auto flex max-w-4xl flex-col gap-3 px-3 py-5 sm:px-6">
                {items.map((item, position) => {
                    const previous = items[position - 1];
                    const newDay =
                        !previous || !isSameDay(previous.at, item.at);

                    return (
                        <Fragment key={item.id}>
                            {newDay && (
                                <div className="flex justify-center py-1">
                                    <span className="rounded-full bg-card px-3 py-1 text-caption font-medium text-muted-foreground shadow-raised">
                                        {formatDay(item.at, locale, {
                                            today: t('inbox.today'),
                                            yesterday: t('inbox.yesterday'),
                                        })}
                                    </span>
                                </div>
                            )}
                            {item.kind === 'event' ? (
                                <SystemEvent
                                    icon={EVENT_ICONS[item.type]}
                                    handover={item.type === 'handed_over'}
                                    time={formatTime(item.at, locale)}
                                >
                                    {item.text}
                                </SystemEvent>
                            ) : (
                                <ThreadBubble
                                    message={item}
                                    onMediaLoad={stickToBottom}
                                />
                            )}
                        </Fragment>
                    );
                })}
            </div>
        </div>
    );
}

function ThreadBubble({
    message,
    onMediaLoad,
}: {
    message: ThreadMessage;
    onMediaLoad: () => void;
}) {
    const { t, locale } = useTranslations();

    const kind: BubbleKind =
        message.type === 'note'
            ? 'note'
            : message.direction === 'inbound'
              ? 'customer'
              : message.sender === 'agent'
                ? 'agent'
                : 'bot';

    const author =
        message.type === 'note'
            ? [t('inbox.sender.note'), message.author]
                  .filter(Boolean)
                  .join(' · ')
            : message.sender === 'agent'
              ? [
                    message.author,
                    message.template && t('inbox.composer.template'),
                ]
                    .filter(Boolean)
                    .join(' · ') || undefined
              : message.sender === 'bot'
                ? t('inbox.sender.bot')
                : message.sender === 'system'
                  ? message.broadcast
                      ? t('inbox.sender.broadcast', {
                            name: message.broadcast,
                        })
                      : t('inbox.sender.system')
                  : undefined;

    const showStatus =
        message.direction === 'outbound' &&
        message.type !== 'note' &&
        message.status !== null;

    return (
        <MessageBubble
            kind={kind}
            author={author}
            time={formatTime(message.at, locale)}
            status={
                showStatus && message.status
                    ? {
                          status: message.status,
                          label: t(`inbox.status.${message.status}`),
                      }
                    : undefined
            }
            failure={
                message.status === 'failed'
                    ? (message.error ?? t('inbox.status.failed'))
                    : undefined
            }
        >
            <MessageContent message={message} onMediaLoad={onMediaLoad} />
        </MessageBubble>
    );
}

function MessageContent({
    message,
    onMediaLoad,
}: {
    message: ThreadMessage;
    onMediaLoad: () => void;
}) {
    const { t } = useTranslations();
    const media = message.media;

    return (
        <>
            {media && message.type === 'image' && (
                <a
                    href={media.url}
                    target="_blank"
                    rel="noopener"
                    className="block"
                >
                    <img
                        src={media.url}
                        alt={media.name ?? t('inbox.preview.image')}
                        onLoad={onMediaLoad}
                        className="max-h-72 rounded-lg object-cover"
                    />
                </a>
            )}
            {media && message.type === 'video' && (
                <video
                    src={media.url}
                    controls
                    preload="metadata"
                    onLoadedMetadata={onMediaLoad}
                    className="max-h-72 rounded-lg"
                />
            )}
            {media && message.type === 'audio' && (
                <audio
                    src={media.url}
                    controls
                    preload="metadata"
                    className="w-64 max-w-full"
                />
            )}
            {media && message.type === 'document' && (
                <a
                    href={`${media.url}?download=1`}
                    className="flex items-center gap-3 rounded-lg bg-black/5 p-2.5 transition-colors hover:bg-black/10 dark:bg-white/10 dark:hover:bg-white/15"
                >
                    <FileText className="size-8 shrink-0 opacity-80" />
                    <span className="flex min-w-0 flex-col">
                        <span dir="auto" className="truncate font-medium">
                            {media.name ?? t('inbox.preview.document')}
                        </span>
                        {media.size !== null && (
                            <span
                                dir="ltr"
                                className="text-caption tabular opacity-75"
                            >
                                {formatFileSize(media.size)}
                            </span>
                        )}
                    </span>
                </a>
            )}
            {message.type === 'location' && (
                <span className="inline-flex items-center gap-1.5">
                    <MapPin className="size-4" />
                    {t('inbox.preview.location')}
                </span>
            )}
            {message.template?.header && (
                <div dir="auto" className="mb-1 font-bold">
                    {message.template.header}
                </div>
            )}
            {message.body && (
                <div dir="auto" className={media ? 'mt-2' : undefined}>
                    {message.body}
                </div>
            )}
            {message.template?.footer && (
                <div dir="auto" className="mt-1 text-caption opacity-75">
                    {message.template.footer}
                </div>
            )}
            {message.template && message.template.buttons.length > 0 && (
                <ul className="mt-2 flex flex-col gap-1 border-t border-current/20 pt-2">
                    {message.template.buttons.map((text, position) => (
                        <li
                            key={position}
                            dir="auto"
                            className="text-center text-body-sm font-semibold opacity-90"
                        >
                            {text}
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}
