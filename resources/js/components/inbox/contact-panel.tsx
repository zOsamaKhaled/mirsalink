import { Link, router } from '@inertiajs/react';
import { BellOff, Plus, X } from 'lucide-react';
import type { ReactNode } from 'react';

import { CategoryTag } from '@/components/inbox/category-tag';
import { RizqCard } from '@/components/inbox/rizq-card';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/hooks/use-translations';
import { formatPhone } from '@/lib/phone';
import { formatDate, formatTime } from '@/lib/time';
import { index as contactsIndex } from '@/routes/contacts';
import { destroy as untag, store as tag } from '@/routes/inbox/tags';
import type { InboxTag, SelectedConversation } from '@/types/inbox';

/**
 * The customer beside the thread: who they are, the conversation's tags
 * (which decide routing) and the contact's own.
 */
export function ContactPanel({
    conversation,
    tags,
}: {
    conversation: SelectedConversation;
    tags: InboxTag[];
}) {
    const { t, locale } = useTranslations();
    const getInitials = useInitials();
    const contact = conversation.contact;
    const unused = tags.filter(
        (option) =>
            !conversation.tags.some((tagged) => tagged.id === option.id),
    );
    const options = { preserveScroll: true, preserveState: true };

    return (
        <div className="flex flex-col gap-6 p-4">
            <div className="flex flex-col items-center gap-2 text-center">
                <Avatar className="size-16">
                    <AvatarFallback className="bg-secondary font-display text-title font-bold text-secondary-foreground">
                        {contact.name ? getInitials(contact.name) : '#'}
                    </AvatarFallback>
                </Avatar>
                <div className="flex flex-col gap-0.5">
                    <span className="text-title font-bold">
                        {contact.name ?? t('inbox.unnamed')}
                    </span>
                    <bdi
                        dir="ltr"
                        className="text-body-sm text-muted-foreground tabular"
                    >
                        {formatPhone(contact.phone)}
                    </bdi>
                </div>
                {contact.opted_out && (
                    <span
                        className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-caption text-muted-foreground"
                        title={t('inbox.thread.opted_out')}
                    >
                        <BellOff className="size-3.5" />
                        {t('inbox.contact.opted_out')}
                    </span>
                )}
                {contact.deleted && (
                    <p className="text-caption text-muted-foreground">
                        {t('inbox.contact.deleted')}
                    </p>
                )}
            </div>

            <dl className="flex flex-col gap-3 text-body-sm">
                {contact.email && (
                    <Detail label={t('inbox.contact.email')}>
                        <bdi dir="ltr" className="break-all">
                            {contact.email}
                        </bdi>
                    </Detail>
                )}
                {contact.company && (
                    <Detail label={t('inbox.contact.company')}>
                        {contact.company}
                    </Detail>
                )}
                {contact.notes && (
                    <Detail label={t('inbox.contact.notes')}>
                        <span className="whitespace-pre-wrap">
                            {contact.notes}
                        </span>
                    </Detail>
                )}
                {contact.since && (
                    <p className="text-caption text-muted-foreground">
                        {t('inbox.contact.since', {
                            date: formatDate(contact.since, locale),
                        })}
                    </p>
                )}
                {conversation.first_response_at && (
                    <p className="text-caption text-muted-foreground">
                        {t('inbox.contact.first_response', {
                            time: `${formatDate(conversation.first_response_at, locale)} ${formatTime(conversation.first_response_at, locale)}`,
                        })}
                    </p>
                )}
            </dl>

            {!contact.deleted && <RizqCard conversation={conversation} />}

            <section className="flex flex-col gap-2">
                <h3 className="text-caption font-semibold text-muted-foreground">
                    {t('inbox.contact.tags')}
                </h3>
                <div className="flex flex-wrap items-center gap-1.5">
                    {conversation.tags.length === 0 && (
                        <span className="text-body-sm text-muted-foreground">
                            {t('inbox.contact.no_tags')}
                        </span>
                    )}
                    {conversation.tags.map((tagged) => (
                        <CategoryTag
                            key={tagged.id}
                            tone={tagged.color}
                            byBot={tagged.by_bot}
                            className="pe-1"
                        >
                            {tagged.name}
                            <button
                                type="button"
                                onClick={() =>
                                    router.delete(
                                        untag.url([conversation.id, tagged.id]),
                                        options,
                                    )
                                }
                                className="rounded-full p-0.5 hover:bg-black/10 dark:hover:bg-white/15"
                                aria-label={t('inbox.contact.remove_tag', {
                                    tag: tagged.name,
                                })}
                            >
                                <X className="size-3" />
                            </button>
                        </CategoryTag>
                    ))}
                    {unused.length > 0 && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    className="h-6 rounded-full px-2 text-caption"
                                >
                                    <Plus className="size-3.5" />
                                    {t('inbox.contact.add_tag')}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="start"
                                className="max-h-72 overflow-y-auto"
                            >
                                {unused.map((option) => (
                                    <DropdownMenuItem
                                        key={option.id}
                                        onSelect={() =>
                                            router.post(
                                                tag.url(conversation.id),
                                                { tag_id: option.id },
                                                options,
                                            )
                                        }
                                    >
                                        <CategoryTag tone={option.color}>
                                            {option.name}
                                        </CategoryTag>
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </section>

            {conversation.contact_tags.length > 0 && (
                <section className="flex flex-col gap-2">
                    <h3 className="text-caption font-semibold text-muted-foreground">
                        {t('inbox.contact.contact_tags')}
                    </h3>
                    <div className="flex flex-wrap gap-1.5">
                        {conversation.contact_tags.map((contactTag) => (
                            <CategoryTag
                                key={contactTag.id}
                                tone={contactTag.color}
                            >
                                {contactTag.name}
                            </CategoryTag>
                        ))}
                    </div>
                </section>
            )}

            {!contact.deleted && (
                <Button variant="outline" size="sm" asChild>
                    <Link href={contactsIndex({ query: { q: contact.phone } })}>
                        {t('inbox.contact.open_contacts')}
                    </Link>
                </Button>
            )}
        </div>
    );
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5">
            <dt className="text-caption text-muted-foreground">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}
