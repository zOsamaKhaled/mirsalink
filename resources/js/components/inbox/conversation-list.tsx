import { Link, router } from '@inertiajs/react';
import { AlarmClock, Bell, BellOff, FlaskConical, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { ConversationItem } from '@/components/inbox/conversation-item';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/hooks/use-translations';
import { formatPhone } from '@/lib/phone';
import { formatListTime } from '@/lib/time';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/inbox';
import { index as simulatorPage } from '@/routes/simulator';
import type { ConversationSummary, InboxTab, InboxTag } from '@/types/inbox';

export type InboxFilters = { q: string; tag: number | null };

/** Query string for a tab and filters, without empty values. */
export function inboxQuery(
    tab: InboxTab,
    filters: InboxFilters,
): Record<string, string | number> {
    return {
        ...(tab !== 'mine' ? { tab } : {}),
        ...(filters.q !== '' ? { q: filters.q } : {}),
        ...(filters.tag !== null ? { tag: filters.tag } : {}),
    };
}

export function ConversationList({
    tab,
    tabs,
    counts,
    filters,
    conversations,
    selectedId,
    tags,
    longWaitMinutes,
    simulator,
    notifications,
    className,
}: {
    tab: InboxTab;
    tabs: InboxTab[];
    counts: Partial<Record<InboxTab, number>>;
    filters: InboxFilters;
    conversations: ConversationSummary[];
    selectedId: number | null;
    tags: InboxTag[];
    longWaitMinutes: number;
    simulator: boolean;
    notifications: {
        permission: NotificationPermission | 'unsupported';
        request: () => void;
    };
    className?: string;
}) {
    const { t, locale } = useTranslations();
    const getInitials = useInitials();
    const filtered = filters.q !== '' || filters.tag !== null;

    const visit = (nextTab: InboxTab, nextFilters: InboxFilters) =>
        router.get(
            selectedId === null
                ? index.url({ query: inboxQuery(nextTab, nextFilters) })
                : show.url(selectedId, {
                      query: inboxQuery(nextTab, nextFilters),
                  }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const waiting = counts.waiting ?? 0;

    return (
        <aside
            className={cn('flex min-h-0 flex-col bg-card', className)}
            aria-label={t('inbox.title')}
        >
            <div className="flex flex-col gap-3 border-b p-3">
                <div className="flex items-center gap-2">
                    <h1 className="min-w-0 flex-1 truncate text-heading font-extrabold">
                        {t('inbox.title')}
                    </h1>
                    {notifications.permission === 'default' && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={notifications.request}
                        >
                            <Bell />
                            {t('inbox.notifications.enable')}
                        </Button>
                    )}
                    {notifications.permission === 'denied' && (
                        <span
                            className="text-muted-foreground"
                            title={t('inbox.notifications.blocked')}
                        >
                            <BellOff className="size-4" />
                            <span className="sr-only">
                                {t('inbox.notifications.blocked')}
                            </span>
                        </span>
                    )}
                    {simulator && (
                        <Button size="sm" variant="outline" asChild>
                            <Link href={simulatorPage()}>
                                <FlaskConical />
                                {t('inbox.simulator_link')}
                            </Link>
                        </Button>
                    )}
                </div>

                <nav
                    className="-mx-1 flex gap-0.5 overflow-x-auto px-1 pb-0.5"
                    aria-label={t('inbox.title')}
                >
                    {tabs
                        .filter((name) => name !== 'waiting')
                        .map((name) => (
                            <button
                                key={name}
                                type="button"
                                onClick={() => visit(name, filters)}
                                aria-current={tab === name ? 'page' : undefined}
                                className={cn(
                                    'inline-flex h-8 shrink-0 items-center gap-1 rounded-full px-2.5 text-body-sm transition-colors',
                                    tab === name
                                        ? 'bg-primary font-semibold text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                                )}
                            >
                                {t(`inbox.tabs.${name}`)}
                                {counts[name] !== undefined && (
                                    <span
                                        className={cn(
                                            'text-caption tabular',
                                            tab === name
                                                ? 'opacity-80'
                                                : 'opacity-70',
                                        )}
                                    >
                                        {counts[name]}
                                    </span>
                                )}
                            </button>
                        ))}
                </nav>

                {tabs.includes('waiting') &&
                    (waiting > 0 || tab === 'waiting') && (
                        <button
                            type="button"
                            onClick={() =>
                                visit(
                                    tab === 'waiting' ? 'all' : 'waiting',
                                    filters,
                                )
                            }
                            aria-pressed={tab === 'waiting'}
                            className={cn(
                                'flex items-center gap-2 rounded-xl px-3 py-2 text-start text-body-sm font-medium transition-colors',
                                'bg-warning-soft text-warning',
                                tab === 'waiting'
                                    ? 'ring-2 ring-warning/50'
                                    : 'hover:ring-1 hover:ring-warning/30',
                            )}
                        >
                            <AlarmClock className="size-4 shrink-0" />
                            <span className="tabular">
                                {t('inbox.waiting_banner', {
                                    minutes: String(longWaitMinutes),
                                    count: String(waiting),
                                })}
                            </span>
                        </button>
                    )}

                <ListFilters
                    filters={filters}
                    tags={tags}
                    onChange={(next) => visit(tab, next)}
                />
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto p-2">
                {conversations.length === 0 ? (
                    <p className="px-3 py-10 text-center text-body-sm text-muted-foreground">
                        {filtered
                            ? t('inbox.empty.filtered')
                            : t(`inbox.empty.${tab}`)}
                    </p>
                ) : (
                    <ul className="flex flex-col gap-0.5">
                        {conversations.map((conversation) => {
                            const name =
                                conversation.name ??
                                (conversation.phone
                                    ? formatPhone(conversation.phone)
                                    : t('inbox.unnamed'));

                            return (
                                <li key={conversation.id}>
                                    <ConversationItem
                                        href={show.url(conversation.id, {
                                            query: inboxQuery(tab, filters),
                                        })}
                                        name={name}
                                        nameIsPhone={
                                            conversation.name === null &&
                                            conversation.phone !== null
                                        }
                                        initials={
                                            conversation.name
                                                ? getInitials(conversation.name)
                                                : '#'
                                        }
                                        preview={conversation.preview}
                                        time={
                                            conversation.last_message_at
                                                ? formatListTime(
                                                      conversation.last_message_at,
                                                      locale,
                                                      t('inbox.yesterday'),
                                                  )
                                                : ''
                                        }
                                        unread={conversation.unread}
                                        unreadLabel={t('inbox.unread', {
                                            count: String(conversation.unread),
                                        })}
                                        tags={conversation.tags.map((tag) => ({
                                            label: tag.name,
                                            tone: tag.color,
                                            byBot: tag.by_bot,
                                        }))}
                                        handler={
                                            conversation.assignee
                                                ? {
                                                      kind: 'agent',
                                                      name: conversation
                                                          .assignee.name,
                                                      initials: getInitials(
                                                          conversation.assignee
                                                              .name,
                                                      ),
                                                  }
                                                : undefined
                                        }
                                        waitingForPerson={
                                            !conversation.closed &&
                                            conversation.assignee === null
                                        }
                                        flag={
                                            conversation.waiting_long
                                                ? t('inbox.waiting_long')
                                                : undefined
                                        }
                                        selected={
                                            conversation.id === selectedId
                                        }
                                        closed={conversation.closed}
                                    />
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </aside>
    );
}

function ListFilters({
    filters,
    tags,
    onChange,
}: {
    filters: InboxFilters;
    tags: InboxTag[];
    onChange: (filters: InboxFilters) => void;
}) {
    const { t } = useTranslations();
    const [q, setQ] = useState(filters.q);
    const first = useRef(true);

    // Search as the member types, a moment after they stop.
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(() => onChange({ ...filters, q }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    return (
        <div className="flex gap-2">
            <div className="relative min-w-0 flex-1">
                <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    value={q}
                    onChange={(event) => setQ(event.target.value)}
                    placeholder={t('inbox.search')}
                    aria-label={t('inbox.search')}
                    className="h-9 ps-9"
                />
            </div>
            {tags.length > 0 && (
                <Select
                    value={filters.tag === null ? 'all' : String(filters.tag)}
                    onValueChange={(value) =>
                        onChange({
                            ...filters,
                            q,
                            tag: value === 'all' ? null : Number(value),
                        })
                    }
                >
                    <SelectTrigger
                        className="h-9 w-32 shrink-0"
                        aria-label={t('inbox.all_tags')}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">
                            {t('inbox.all_tags')}
                        </SelectItem>
                        {tags.map((tag) => (
                            <SelectItem key={tag.id} value={String(tag.id)}>
                                {tag.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}
        </div>
    );
}
