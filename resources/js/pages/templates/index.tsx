import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/react';
import {
    FileText,
    MoreHorizontal,
    Pencil,
    Plus,
    Send,
    Trash2,
    Users,
    Zap,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';

import { ConfirmDeleteDialog } from '@/components/confirm-dialog';
import { TemplatePreview } from '@/components/inbox/template-preview';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import {
    destroy as destroyReply,
    store as storeReply,
    update as updateReply,
} from '@/routes/quick-replies';
import {
    create,
    destroy as destroyTemplate,
    edit,
    index,
    submit,
} from '@/routes/templates';
import type {
    QuickReply,
    TemplateStatus,
    WhatsAppTemplate,
} from '@/types/templates';

type Tab = 'replies' | 'templates';

export default function Templates({
    tab,
    quickReplies,
    templates,
}: {
    tab: Tab;
    quickReplies: QuickReply[];
    templates: WhatsAppTemplate[];
}) {
    const { t } = useTranslations();

    setLayoutProps({
        breadcrumbs: [{ title: t('templates.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('templates.title')} />

            <div className="flex flex-col gap-6 px-4 py-8 sm:px-6">
                <header className="flex max-w-3xl flex-col gap-1.5">
                    <h1 className="text-display font-extrabold">
                        {t('templates.title')}
                    </h1>
                    <p className="text-body-sm text-muted-foreground">
                        {t('templates.description')}
                    </p>
                </header>

                <nav className="flex gap-1 self-start rounded-full bg-muted p-1">
                    {(['replies', 'templates'] as const).map((name) => (
                        <Link
                            key={name}
                            href={index.url({
                                query: name === 'replies' ? {} : { tab: name },
                            })}
                            preserveState
                            replace
                            aria-current={tab === name ? 'page' : undefined}
                            className={cn(
                                'inline-flex h-9 items-center gap-2 rounded-full px-4 text-body-sm transition-colors',
                                tab === name
                                    ? 'bg-card font-semibold shadow-raised'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {name === 'replies' ? (
                                <Zap className="size-4" />
                            ) : (
                                <FileText className="size-4" />
                            )}
                            {t(`templates.tabs.${name}`)}
                        </Link>
                    ))}
                </nav>

                {tab === 'replies' ? (
                    <QuickReplies replies={quickReplies} />
                ) : (
                    <TemplateList templates={templates} />
                )}
            </div>
        </>
    );
}

function QuickReplies({ replies }: { replies: QuickReply[] }) {
    const { t } = useTranslations();
    const [editing, setEditing] = useState<QuickReply | 'new' | null>(null);
    const [deleting, setDeleting] = useState<QuickReply | null>(null);

    const groups = [
        {
            key: 'mine',
            title: t('quick_replies.mine'),
            empty: t('quick_replies.empty_mine'),
            items: replies.filter((reply) => !reply.shared),
        },
        {
            key: 'shared',
            title: t('quick_replies.shared'),
            empty: t('quick_replies.empty_shared'),
            items: replies.filter((reply) => reply.shared),
        },
    ];

    return (
        <section className="flex flex-col gap-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="max-w-2xl text-body-sm text-muted-foreground">
                    {t('quick_replies.how')}
                </p>
                <Button onClick={() => setEditing('new')}>
                    <Plus />
                    {t('quick_replies.add')}
                </Button>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                {groups.map((group) => (
                    <div key={group.key} className="flex flex-col gap-3">
                        <h2 className="flex items-center gap-2 text-title font-bold">
                            {group.key === 'shared' && (
                                <Users className="size-5 text-muted-foreground" />
                            )}
                            {group.title}
                        </h2>
                        {group.items.length === 0 ? (
                            <p className="rounded-2xl border border-dashed p-6 text-center text-body-sm text-muted-foreground">
                                {group.empty}
                            </p>
                        ) : (
                            <ul className="flex flex-col divide-y overflow-hidden rounded-2xl border bg-card">
                                {group.items.map((reply) => (
                                    <li
                                        key={reply.id}
                                        className="flex items-start gap-3 p-4"
                                    >
                                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <bdi
                                                    dir="ltr"
                                                    className="rounded-md bg-secondary px-2 py-0.5 font-mono text-caption text-secondary-foreground"
                                                >
                                                    /{reply.shortcut}
                                                </bdi>
                                                <span className="font-semibold">
                                                    {reply.title}
                                                </span>
                                            </div>
                                            <p
                                                dir="auto"
                                                className="line-clamp-2 text-body-sm whitespace-pre-wrap text-muted-foreground"
                                            >
                                                {reply.body}
                                            </p>
                                        </div>
                                        {reply.can_manage && (
                                            <RowMenu
                                                onEdit={() => setEditing(reply)}
                                                onDelete={() =>
                                                    setDeleting(reply)
                                                }
                                                editLabel={t(
                                                    'quick_replies.edit',
                                                )}
                                                deleteLabel={t(
                                                    'quick_replies.delete',
                                                )}
                                            />
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                ))}
            </div>

            <QuickReplyDialog
                reply={editing}
                onClose={() => setEditing(null)}
            />
            <ConfirmDeleteDialog
                url={deleting ? destroyReply.url(deleting.id) : null}
                title={t('quick_replies.delete_title', {
                    title: deleting?.title ?? '',
                })}
                confirmLabel={t('quick_replies.delete')}
                onClose={() => setDeleting(null)}
            />
        </section>
    );
}

function QuickReplyDialog({
    reply,
    onClose,
}: {
    reply: QuickReply | 'new' | null;
    onClose: () => void;
}) {
    const { t } = useTranslations();
    const { auth } = usePage().props;
    const editing = reply !== null && reply !== 'new' ? reply : null;
    const form = useForm({ shortcut: '', title: '', body: '', shared: false });

    useEffect(() => {
        if (reply === null) {
            return;
        }

        form.clearErrors();
        form.setData({
            shortcut: editing?.shortcut ?? '',
            title: editing?.title ?? '',
            body: editing?.body ?? '',
            shared: editing?.shared ?? false,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [reply]);

    const save = (event: FormEvent) => {
        event.preventDefault();
        form.submit(editing ? updateReply(editing.id) : storeReply(), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Dialog
            open={reply !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                <DialogTitle>
                    {editing
                        ? t('quick_replies.form.edit_title')
                        : t('quick_replies.form.create_title')}
                </DialogTitle>
                <DialogDescription className="sr-only">
                    {t('quick_replies.how')}
                </DialogDescription>

                <form onSubmit={save} className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-[10rem_1fr]">
                        <div className="grid content-start gap-2">
                            <Label htmlFor="reply-shortcut">
                                {t('quick_replies.form.shortcut')}
                            </Label>
                            <div className="relative" dir="ltr">
                                <span className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 font-mono text-muted-foreground">
                                    /
                                </span>
                                <Input
                                    id="reply-shortcut"
                                    value={form.data.shortcut}
                                    onChange={(event) =>
                                        form.setData(
                                            'shortcut',
                                            event.target.value,
                                        )
                                    }
                                    autoComplete="off"
                                    className="h-10 ps-6 font-mono"
                                />
                            </div>
                            <InputError message={form.errors.shortcut} />
                        </div>
                        <div className="grid content-start gap-2">
                            <Label htmlFor="reply-title">
                                {t('quick_replies.form.title')}
                            </Label>
                            <Input
                                id="reply-title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                className="h-10"
                            />
                            <InputError message={form.errors.title} />
                        </div>
                    </div>
                    <p className="-mt-2 text-caption text-muted-foreground">
                        {t('quick_replies.form.shortcut_hint')}
                    </p>

                    <div className="grid gap-2">
                        <Label htmlFor="reply-body">
                            {t('quick_replies.form.body')}
                        </Label>
                        <Textarea
                            id="reply-body"
                            dir="auto"
                            rows={5}
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                        />
                        <p className="text-caption text-muted-foreground">
                            {t('quick_replies.form.body_hint')}
                        </p>
                        <InputError message={form.errors.body} />
                    </div>

                    {auth.can.manageTeam && (
                        <label className="flex items-start gap-3 rounded-xl border p-3">
                            <Switch
                                checked={form.data.shared}
                                onCheckedChange={(checked) =>
                                    form.setData('shared', checked)
                                }
                            />
                            <span className="flex flex-col gap-0.5">
                                <span className="text-body-sm font-semibold">
                                    {t('quick_replies.form.shared')}
                                </span>
                                <span className="text-caption text-muted-foreground">
                                    {t('quick_replies.form.shared_hint')}
                                </span>
                            </span>
                        </label>
                    )}

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('quick_replies.form.cancel')}
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {t('quick_replies.form.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

const STATUS_CLASSES: Record<TemplateStatus, string> = {
    draft: 'bg-muted text-muted-foreground',
    pending: 'bg-warning-soft text-warning',
    approved: 'bg-success-soft text-success',
    rejected: 'bg-danger-soft text-danger',
};

function TemplateStatusBadge({ status }: { status: TemplateStatus }) {
    const { t } = useTranslations();

    return (
        <span
            className={cn(
                'inline-flex h-6 items-center rounded-full px-2.5 text-caption font-semibold',
                STATUS_CLASSES[status],
            )}
        >
            {t(`templates.status.${status}`)}
        </span>
    );
}

function TemplateList({ templates }: { templates: WhatsAppTemplate[] }) {
    const { t } = useTranslations();
    const { auth } = usePage().props;
    const manage = auth.can.manageTeam;
    const [deleting, setDeleting] = useState<WhatsAppTemplate | null>(null);

    return (
        <section className="flex flex-col gap-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="max-w-2xl text-body-sm text-muted-foreground">
                    {t('templates.intro')} {!manage && t('templates.read_only')}
                </p>
                {manage && (
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            {t('templates.add')}
                        </Link>
                    </Button>
                )}
            </div>

            {templates.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed p-10 text-center">
                    <FileText className="size-8 text-muted-foreground" />
                    <p className="font-semibold">{t('templates.empty')}</p>
                    <p className="text-body-sm text-muted-foreground">
                        {t('templates.empty_hint')}
                    </p>
                </div>
            ) : (
                <ul className="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                    {templates.map((template) => {
                        const editable =
                            template.status === 'draft' ||
                            template.status === 'rejected';

                        return (
                            <li
                                key={template.id}
                                className="flex flex-col gap-4 rounded-2xl border bg-card p-4"
                            >
                                <div className="flex items-start gap-2">
                                    <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                                        <bdi
                                            dir="ltr"
                                            className="truncate font-mono text-body-sm font-semibold"
                                        >
                                            {template.name}
                                        </bdi>
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <TemplateStatusBadge
                                                status={template.status}
                                            />
                                            <span className="inline-flex h-6 items-center rounded-full bg-secondary px-2.5 text-caption text-secondary-foreground">
                                                {t(
                                                    `templates.category.${template.category}`,
                                                )}
                                            </span>
                                            <span className="inline-flex h-6 items-center rounded-full border px-2.5 text-caption text-muted-foreground">
                                                {t(
                                                    `templates.language.${template.language}`,
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                    {manage && (
                                        <RowMenu
                                            onEdit={
                                                editable
                                                    ? () =>
                                                          router.visit(
                                                              edit.url(
                                                                  template.id,
                                                              ),
                                                          )
                                                    : undefined
                                            }
                                            onDelete={() =>
                                                setDeleting(template)
                                            }
                                            editLabel={t('templates.edit')}
                                            deleteLabel={t('templates.delete')}
                                        />
                                    )}
                                </div>

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

                                {template.status === 'rejected' &&
                                    template.rejection_reason && (
                                        <p className="rounded-xl bg-danger-soft px-3 py-2 text-body-sm text-danger">
                                            {t('templates.rejected_reason', {
                                                reason: template.rejection_reason,
                                            })}
                                        </p>
                                    )}

                                {manage && editable && (
                                    <Button
                                        variant="outline"
                                        className="mt-auto self-start"
                                        onClick={() =>
                                            router.post(
                                                submit.url(template.id),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Send className="rtl:-scale-x-100" />
                                        {t('templates.submit')}
                                    </Button>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}

            <ConfirmDeleteDialog
                url={deleting ? destroyTemplate.url(deleting.id) : null}
                title={t('templates.delete_title', {
                    name: deleting?.name ?? '',
                })}
                body={t('templates.delete_body')}
                confirmLabel={t('templates.delete')}
                onClose={() => setDeleting(null)}
            />
        </section>
    );
}

function RowMenu({
    onEdit,
    onDelete,
    editLabel,
    deleteLabel,
}: {
    onEdit?: () => void;
    onDelete: () => void;
    editLabel: string;
    deleteLabel: string;
}) {
    const { t } = useTranslations();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    size="icon"
                    variant="ghost"
                    className="size-8 shrink-0"
                    aria-label={t('inbox.thread.more')}
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {onEdit && (
                    <DropdownMenuItem onSelect={onEdit}>
                        <Pencil />
                        {editLabel}
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                    <Trash2 />
                    {deleteLabel}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
