import { Head, Link, router, setLayoutProps, usePoll } from '@inertiajs/react';
import { CircleCheck, Info, MessagesSquare } from 'lucide-react';
import { useEffect, useState } from 'react';

import { BackArrow } from '@/components/direction-icons';
import { AssignMenu } from '@/components/inbox/assign-menu';
import { Composer } from '@/components/inbox/composer';
import { ContactPanel } from '@/components/inbox/contact-panel';
import {
    ConversationList,
    inboxQuery,
} from '@/components/inbox/conversation-list';
import type { InboxFilters } from '@/components/inbox/conversation-list';
import { ThreadMessages } from '@/components/inbox/thread-messages';
import { WindowTimer } from '@/components/inbox/window-timer';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { useInboxNotifications } from '@/hooks/use-inbox-notifications';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/hooks/use-translations';
import { formatPhone } from '@/lib/phone';
import { formatHoursLeft } from '@/lib/time';
import { cn } from '@/lib/utils';
import { close, index, reopen } from '@/routes/inbox';
import type {
    ConversationSummary,
    IncomingMessage,
    InboxMember,
    InboxTab,
    InboxTag,
    SelectedConversation,
    ThreadItem,
} from '@/types/inbox';
import type { QuickReply, WhatsAppTemplate } from '@/types/templates';

type Props = {
    tab: InboxTab;
    tabs: InboxTab[];
    filters: InboxFilters;
    counts: Partial<Record<InboxTab, number>>;
    conversations: ConversationSummary[];
    selected: SelectedConversation | null;
    thread: ThreadItem[];
    incoming: IncomingMessage[];
    members: InboxMember[];
    tags: InboxTag[];
    quickReplies: QuickReply[];
    templates: WhatsAppTemplate[];
    pollSeconds: number;
    longWaitMinutes: number;
    simulator: boolean;
};

const WIDE = '(min-width: 1280px)';

export default function Inbox(props: Props) {
    const { tab, filters, selected } = props;
    const { t } = useTranslations();
    const notifications = useInboxNotifications(
        props.incoming,
        selected?.id ?? null,
    );
    // Beside the thread on wide screens; on narrow ones a sheet opened by hand.
    const [details, setDetails] = useState(
        () => typeof window !== 'undefined' && window.matchMedia(WIDE).matches,
    );
    const wide = useMediaQuery(WIDE);

    usePoll(
        props.pollSeconds * 1000,
        {
            only: ['counts', 'conversations', 'selected', 'thread', 'incoming'],
        },
        { keepAlive: true },
    );

    setLayoutProps({
        breadcrumbs: [{ title: t('inbox.title'), href: index() }],
    });

    return (
        <>
            <Head
                title={
                    selected
                        ? `${selected.name ?? formatPhone(selected.contact.phone)} · ${t('inbox.title')}`
                        : t('inbox.title')
                }
            />

            <div className="flex h-[calc(100svh-4rem)] min-h-0 md:h-[calc(100svh-5rem)] md:group-has-data-[collapsible=icon]/sidebar-wrapper:h-[calc(100svh-4rem)]">
                <ConversationList
                    tab={tab}
                    tabs={props.tabs}
                    counts={props.counts}
                    filters={filters}
                    conversations={props.conversations}
                    selectedId={selected?.id ?? null}
                    tags={props.tags}
                    longWaitMinutes={props.longWaitMinutes}
                    simulator={props.simulator}
                    notifications={notifications}
                    className={cn(
                        'w-full border-e md:w-80 md:shrink-0 2xl:w-96',
                        selected ? 'hidden md:flex' : 'flex',
                    )}
                />

                <section
                    className={cn(
                        'min-w-0 flex-1 flex-col',
                        selected ? 'flex' : 'hidden md:flex',
                    )}
                >
                    {selected ? (
                        <>
                            <ThreadHeader
                                conversation={selected}
                                members={props.members}
                                backHref={index.url({
                                    query: inboxQuery(tab, filters),
                                })}
                                onToggleDetails={() => setDetails(!details)}
                                detailsOpen={details}
                            />
                            <ThreadMessages
                                key={selected.id}
                                items={props.thread}
                            />
                            <Composer
                                key={`composer-${selected.id}`}
                                conversation={selected}
                                quickReplies={props.quickReplies}
                                templates={props.templates}
                            />
                        </>
                    ) : (
                        <NothingSelected simulator={props.simulator} />
                    )}
                </section>

                {selected && details && wide && (
                    <aside
                        className="w-72 shrink-0 overflow-y-auto border-s bg-card 2xl:w-80"
                        aria-label={t('inbox.thread.details')}
                    >
                        <ContactPanel
                            conversation={selected}
                            tags={props.tags}
                        />
                    </aside>
                )}
            </div>

            {selected && !wide && (
                <DetailsSheet
                    open={details}
                    onOpenChange={setDetails}
                    conversation={selected}
                    tags={props.tags}
                />
            )}
        </>
    );
}

function ThreadHeader({
    conversation,
    members,
    backHref,
    onToggleDetails,
    detailsOpen,
}: {
    conversation: SelectedConversation;
    members: InboxMember[];
    backHref: string;
    onToggleDetails: () => void;
    detailsOpen: boolean;
}) {
    const { t } = useTranslations();
    const getInitials = useInitials();
    const name = conversation.contact.name;
    const options = { preserveScroll: true, preserveState: true };

    return (
        <header className="flex flex-wrap items-center gap-x-3 gap-y-2 border-b bg-card px-3 py-2.5">
            <Button
                variant="ghost"
                size="icon"
                className="-ms-1 md:hidden"
                asChild
            >
                <Link href={backHref} aria-label={t('inbox.back')}>
                    <BackArrow />
                </Link>
            </Button>

            <Avatar className="size-10">
                <AvatarFallback className="bg-secondary font-display font-semibold text-secondary-foreground">
                    {name ? getInitials(name) : '#'}
                </AvatarFallback>
            </Avatar>
            <div className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-body font-semibold">
                    {name ?? t('inbox.unnamed')}
                </span>
                <bdi
                    dir="ltr"
                    className="truncate text-caption text-muted-foreground tabular"
                >
                    {formatPhone(conversation.contact.phone)}
                </bdi>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <ReplyWindow conversation={conversation} />
                <AssignMenu conversation={conversation} members={members} />
                {conversation.closed ? (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.post(
                                reopen.url(conversation.id),
                                {},
                                options,
                            )
                        }
                    >
                        {t('inbox.thread.reopen')}
                    </Button>
                ) : (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.post(close.url(conversation.id), {}, options)
                        }
                    >
                        <CircleCheck />
                        {t('inbox.thread.close')}
                    </Button>
                )}
                <Button
                    size="icon"
                    variant={detailsOpen ? 'secondary' : 'ghost'}
                    className="size-8"
                    onClick={onToggleDetails}
                    aria-pressed={detailsOpen}
                    aria-label={
                        detailsOpen
                            ? t('inbox.thread.hide_details')
                            : t('inbox.thread.details')
                    }
                    title={t('inbox.thread.details')}
                >
                    <Info />
                </Button>
            </div>
        </header>
    );
}

/**
 * How long the team may still reply freely, ticking each minute.
 */
function ReplyWindow({ conversation }: { conversation: SelectedConversation }) {
    const { t } = useTranslations();
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 60_000);

        return () => clearInterval(timer);
    }, []);

    if (!conversation.window_closes_at) {
        return (
            <WindowTimer hoursLeft={0} label={t('inbox.thread.window_never')} />
        );
    }

    const left = new Date(conversation.window_closes_at).getTime() - now;

    return (
        <WindowTimer
            hoursLeft={left / 3_600_000}
            label={
                left > 0
                    ? t('inbox.thread.window_left', {
                          time: formatHoursLeft(left),
                      })
                    : t('inbox.thread.window_closed')
            }
        />
    );
}

function DetailsSheet({
    open,
    onOpenChange,
    conversation,
    tags,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    conversation: SelectedConversation;
    tags: InboxTag[];
}) {
    const { t, dir } = useTranslations();

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side={dir === 'rtl' ? 'left' : 'right'}
                className="w-80 overflow-y-auto p-0"
            >
                <SheetTitle className="sr-only">
                    {t('inbox.thread.details')}
                </SheetTitle>
                <ContactPanel conversation={conversation} tags={tags} />
            </SheetContent>
        </Sheet>
    );
}

function NothingSelected({ simulator }: { simulator: boolean }) {
    const { t } = useTranslations();

    return (
        <div className="flex flex-1 flex-col items-center justify-center gap-3 bg-sunken p-8 text-center">
            <span className="grid size-14 place-items-center rounded-2xl bg-card text-muted-foreground shadow-raised">
                <MessagesSquare className="size-7" />
            </span>
            <h2 className="text-title font-bold">{t('inbox.select.title')}</h2>
            <p className="max-w-sm text-body-sm text-muted-foreground">
                {simulator ? t('inbox.simulator_hint') : t('inbox.select.body')}
            </p>
        </div>
    );
}

function useMediaQuery(query: string): boolean {
    const [matches, setMatches] = useState(
        () => typeof window !== 'undefined' && window.matchMedia(query).matches,
    );

    useEffect(() => {
        const list = window.matchMedia(query);
        const update = () => setMatches(list.matches);
        update();
        list.addEventListener('change', update);

        return () => list.removeEventListener('change', update);
    }, [query]);

    return matches;
}
