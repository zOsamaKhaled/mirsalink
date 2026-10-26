import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    usePoll,
} from '@inertiajs/react';
import { Check, Inbox, Paperclip, Send, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

import { MessageStatus } from '@/components/inbox/message-status';
import { TemplatePreview } from '@/components/inbox/template-preview';
import type { DeliveryStatus } from '@/components/inbox/message-status';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/hooks/use-translations';
import { formatPhone } from '@/lib/phone';
import { formatTime } from '@/lib/time';
import { cn } from '@/lib/utils';
import { index as inbox } from '@/routes/inbox';
import { index } from '@/routes/simulator';
import { status as markStatus, store } from '@/routes/simulator/messages';
import { decide } from '@/routes/simulator/templates';
import type { WhatsAppTemplate } from '@/types/templates';

type SimContact = { id: number; name: string | null; phone: string };

type SentMessage = {
    id: number;
    to: string | null;
    preview: string;
    author: string | null;
    status: DeliveryStatus | null;
    at: string;
};

const RANK: Record<DeliveryStatus, number> = {
    pending: 0,
    sent: 1,
    delivered: 2,
    read: 3,
    failed: 4,
};

export default function Simulator({
    contacts,
    sent,
    pendingTemplates,
}: {
    contacts: SimContact[];
    sent: SentMessage[];
    pendingTemplates: WhatsAppTemplate[];
}) {
    const { t } = useTranslations();

    usePoll(5000, { only: ['sent', 'pendingTemplates'] });

    setLayoutProps({
        breadcrumbs: [
            { title: t('inbox.title'), href: inbox() },
            { title: t('simulator.title'), href: index() },
        ],
    });

    return (
        <>
            <Head title={t('simulator.title')} />

            <div className="flex flex-col gap-6 px-4 py-8 sm:px-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="flex max-w-2xl flex-col gap-1.5">
                        <h1 className="text-display font-extrabold">
                            {t('simulator.title')}
                        </h1>
                        <p className="text-body-sm text-muted-foreground">
                            {t('simulator.description')}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={inbox()}>
                            <Inbox />
                            {t('simulator.open_inbox')}
                        </Link>
                    </Button>
                </header>

                {pendingTemplates.length > 0 && (
                    <PendingTemplates templates={pendingTemplates} />
                )}

                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <CustomerForm contacts={contacts} />
                    <SentList sent={sent} />
                </div>
            </div>
        </>
    );
}

function CustomerForm({ contacts }: { contacts: SimContact[] }) {
    const { t } = useTranslations();
    const [existing, setExisting] = useState(contacts.length > 0);
    const form = useForm<{
        phone: string;
        name: string;
        body: string;
        file: File | null;
    }>({ phone: contacts[0]?.phone ?? '', name: '', body: '', file: null });
    const [fileKey, setFileKey] = useState(0);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('body', 'file');
                setFileKey((key) => key + 1);
            },
        });
    };

    const choose = (useExisting: boolean) => {
        setExisting(useExisting);
        form.setData((data) => ({
            ...data,
            phone: useExisting ? (contacts[0]?.phone ?? '') : '',
            name: '',
        }));
        form.clearErrors();
    };

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-5 rounded-2xl border bg-card p-5 shadow-raised"
        >
            <div className="flex flex-col gap-2">
                <span className="text-body-sm font-semibold">
                    {t('simulator.customer')}
                </span>
                <div className="flex gap-1 rounded-full bg-muted p-1">
                    {[
                        { value: true, label: t('simulator.existing') },
                        { value: false, label: t('simulator.new_number') },
                    ].map((option) => (
                        <button
                            key={String(option.value)}
                            type="button"
                            onClick={() => choose(option.value)}
                            disabled={option.value && contacts.length === 0}
                            aria-pressed={existing === option.value}
                            className={cn(
                                'h-8 flex-1 rounded-full text-body-sm transition-colors disabled:opacity-50',
                                existing === option.value
                                    ? 'bg-card font-semibold shadow-raised'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            </div>

            {existing ? (
                <div className="flex flex-col gap-2">
                    <Label>{t('simulator.pick_contact')}</Label>
                    <Select
                        value={form.data.phone}
                        onValueChange={(phone) => form.setData('phone', phone)}
                    >
                        <SelectTrigger aria-label={t('simulator.pick_contact')}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {contacts.map((contact) => (
                                <SelectItem
                                    key={contact.id}
                                    value={contact.phone}
                                >
                                    <span>
                                        {contact.name ?? t('inbox.unnamed')}
                                    </span>
                                    <bdi
                                        dir="ltr"
                                        className="text-muted-foreground tabular"
                                    >
                                        {formatPhone(contact.phone)}
                                    </bdi>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.phone} />
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="sim-phone">
                            {t('simulator.phone')}
                        </Label>
                        <Input
                            id="sim-phone"
                            dir="ltr"
                            inputMode="tel"
                            value={form.data.phone}
                            onChange={(event) =>
                                form.setData('phone', event.target.value)
                            }
                            placeholder="05X XXX XXXX"
                            className="text-start"
                        />
                        <InputError message={form.errors.phone} />
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="sim-name">{t('simulator.name')}</Label>
                        <Input
                            id="sim-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        <p className="text-caption text-muted-foreground">
                            {t('simulator.name_hint')}
                        </p>
                    </div>
                </div>
            )}

            <div className="flex flex-col gap-2">
                <Label htmlFor="sim-body">{t('simulator.body')}</Label>
                <Textarea
                    id="sim-body"
                    dir="auto"
                    rows={3}
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                    placeholder={t('simulator.body_placeholder')}
                />
                <InputError message={form.errors.body} />
            </div>

            <div className="flex flex-col gap-2">
                <span className="text-sm font-medium">
                    {t('simulator.file')}
                </span>
                {/* The browser's own file field speaks the browser's language, so a labelled button stands in. */}
                <label className="flex cursor-pointer items-center gap-3 rounded-md border border-input px-3 py-2 text-body-sm focus-within:ring-[3px] focus-within:ring-ring/50">
                    <input
                        key={fileKey}
                        id="sim-file"
                        type="file"
                        className="sr-only"
                        onChange={(event) =>
                            form.setData(
                                'file',
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <span className="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-full bg-secondary px-3 font-medium text-secondary-foreground">
                        <Paperclip className="size-3.5" />
                        {t('simulator.choose_file')}
                    </span>
                    <span
                        dir="auto"
                        className="min-w-0 truncate text-muted-foreground"
                    >
                        {form.data.file?.name ?? t('simulator.no_file')}
                    </span>
                </label>
                <InputError message={form.errors.file} />
            </div>

            <Button
                type="submit"
                disabled={form.processing}
                className="self-start"
            >
                {form.processing ? (
                    <Spinner />
                ) : (
                    <Send className="rtl:-scale-x-100" />
                )}
                {t('simulator.send')}
            </Button>
        </form>
    );
}

function SentList({ sent }: { sent: SentMessage[] }) {
    const { t, locale } = useTranslations();

    const mark = (id: number, status: 'delivered' | 'read' | 'failed') =>
        router.put(
            markStatus.url(id),
            { status },
            { preserveScroll: true, preserveState: true, only: ['sent'] },
        );

    return (
        <section className="flex flex-col gap-3 rounded-2xl border bg-card p-5">
            <div className="flex flex-col gap-1">
                <h2 className="text-title font-bold">
                    {t('simulator.sent_title')}
                </h2>
                <p className="text-body-sm text-muted-foreground">
                    {t('simulator.sent_hint')}
                </p>
            </div>

            {sent.length === 0 ? (
                <p className="py-8 text-center text-body-sm text-muted-foreground">
                    {t('simulator.sent_empty')}
                </p>
            ) : (
                <ul className="flex flex-col divide-y">
                    {sent.map((message) => {
                        const rank = message.status ? RANK[message.status] : 0;

                        return (
                            <li
                                key={message.id}
                                className="flex flex-col gap-2 py-3"
                            >
                                <div className="flex items-center gap-2 text-caption text-muted-foreground">
                                    <span className="min-w-0 flex-1 truncate">
                                        {t('simulator.to', {
                                            name: message.to ?? '',
                                        })}
                                        {message.author &&
                                            ` · ${message.author}`}
                                    </span>
                                    <time dir="ltr" className="tabular">
                                        {formatTime(message.at, locale)}
                                    </time>
                                    {message.status && (
                                        <span className="inline-flex items-center gap-1">
                                            <MessageStatus
                                                status={message.status}
                                                label={t(
                                                    `inbox.status.${message.status}`,
                                                )}
                                            />
                                            {t(
                                                `inbox.status.${message.status}`,
                                            )}
                                        </span>
                                    )}
                                </div>
                                <p
                                    dir="auto"
                                    className="line-clamp-2 text-body-sm"
                                >
                                    {message.preview}
                                </p>
                                <div className="flex flex-wrap gap-1.5">
                                    {(
                                        ['delivered', 'read', 'failed'] as const
                                    ).map((status) => (
                                        <Button
                                            key={status}
                                            type="button"
                                            size="sm"
                                            variant={
                                                status === 'failed'
                                                    ? 'ghost'
                                                    : 'outline'
                                            }
                                            className="h-7 rounded-full px-3 text-caption"
                                            disabled={
                                                message.status === 'failed' ||
                                                rank >= RANK[status]
                                            }
                                            onClick={() =>
                                                mark(message.id, status)
                                            }
                                        >
                                            {t(`simulator.mark.${status}`)}
                                        </Button>
                                    ))}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

/**
 * Meta's review, played here: each template sent for review waits for an
 * approval or a refusal with its reason.
 */
function PendingTemplates({ templates }: { templates: WhatsAppTemplate[] }) {
    const { t } = useTranslations();
    const [reasons, setReasons] = useState<Record<number, string>>({});

    const record = (
        template: WhatsAppTemplate,
        decision: 'approved' | 'rejected',
    ) =>
        router.put(
            decide.url(template.id),
            {
                decision,
                reason:
                    decision === 'rejected'
                        ? (reasons[template.id] ??
                          t('simulator.templates.default_reason'))
                        : null,
            },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <section className="flex flex-col gap-3 rounded-2xl border bg-card p-5">
            <div className="flex flex-col gap-1">
                <h2 className="text-title font-bold">
                    {t('simulator.templates.title')}
                </h2>
                <p className="text-body-sm text-muted-foreground">
                    {t('simulator.templates.hint')}
                </p>
            </div>

            <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {templates.map((template) => (
                    <li
                        key={template.id}
                        className="flex flex-col gap-3 rounded-xl border p-3"
                    >
                        <bdi
                            dir="ltr"
                            className="truncate font-mono text-body-sm font-semibold"
                        >
                            {template.name}
                        </bdi>
                        <div className="rounded-xl bg-sunken p-3">
                            <TemplatePreview
                                headerType={template.header_type}
                                headerText={template.header_text}
                                headerValue={template.header_example}
                                body={template.body}
                                values={template.body_examples}
                                footer={template.footer}
                                buttons={template.buttons}
                            />
                        </div>
                        <Input
                            value={
                                reasons[template.id] ??
                                t('simulator.templates.default_reason')
                            }
                            onChange={(event) =>
                                setReasons({
                                    ...reasons,
                                    [template.id]: event.target.value,
                                })
                            }
                            dir="auto"
                            aria-label={t('simulator.templates.reason')}
                            className="h-9"
                        />
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                onClick={() => record(template, 'approved')}
                            >
                                <Check />
                                {t('simulator.templates.approve')}
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => record(template, 'rejected')}
                            >
                                <X />
                                {t('simulator.templates.reject')}
                            </Button>
                        </div>
                    </li>
                ))}
            </ul>
        </section>
    );
}
