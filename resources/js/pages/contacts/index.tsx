import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/react';
import {
    BellOff,
    Contact as ContactIcon,
    MoreHorizontal,
    Plus,
    Search,
    Upload,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';

import { BackChevron, ForwardChevron } from '@/components/direction-icons';
import { CategoryTag } from '@/components/inbox/category-tag';
import type { TagTone } from '@/components/inbox/category-tag';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
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
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/hooks/use-translations';
import { formatPhone } from '@/lib/phone';
import { cn } from '@/lib/utils';
import { create as importPage } from '@/routes/contacts/import';
import { destroy, index, store, update } from '@/routes/contacts';

type TagOption = { id: number; name: string; color: TagTone };

type Contact = {
    id: number;
    name: string | null;
    phone: string;
    email: string | null;
    company: string | null;
    notes: string | null;
    opted_out: boolean;
    source: string;
    tags: TagOption[];
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filters = { q: string; tag: number | null; opted_out: boolean };

type Props = {
    contacts: Paginated<Contact>;
    filters: Filters;
    tags: TagOption[];
    total: number;
};

export default function Contacts({ contacts, filters, tags, total }: Props) {
    const { t } = useTranslations();
    const { auth } = usePage().props;
    const [editing, setEditing] = useState<Contact | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Contact | null>(null);

    setLayoutProps({
        breadcrumbs: [{ title: t('contacts.title'), href: index() }],
    });

    const filtered =
        filters.q !== '' || filters.tag !== null || filters.opted_out;

    return (
        <>
            <Head title={t('contacts.title')} />

            <div className="flex flex-col gap-6 px-4 py-8 sm:px-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="flex flex-col gap-1.5">
                        <h1 className="text-display font-extrabold">
                            {t('contacts.title')}
                        </h1>
                        <p className="text-body-sm text-muted-foreground">
                            {t('contacts.description')}{' '}
                            <span className="tabular">
                                {t('contacts.count', {
                                    count: String(total),
                                })}
                            </span>
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {auth.can.manageTeam && (
                            <Button variant="outline" asChild>
                                <Link href={importPage()}>
                                    <Upload />
                                    {t('contacts.import_button')}
                                </Link>
                            </Button>
                        )}
                        <Button onClick={() => setEditing('new')}>
                            <Plus />
                            {t('contacts.add')}
                        </Button>
                    </div>
                </header>

                {total === 0 ? (
                    <EmptyState onAdd={() => setEditing('new')} />
                ) : (
                    <>
                        <FilterBar filters={filters} tags={tags} />

                        {contacts.data.length === 0 ? (
                            <p className="rounded-2xl border bg-card p-8 text-center text-body-sm text-muted-foreground">
                                {t('contacts.no_results')}
                            </p>
                        ) : (
                            <ul className="flex flex-col overflow-hidden rounded-2xl border bg-card">
                                {contacts.data.map((contact) => (
                                    <ContactRow
                                        key={contact.id}
                                        contact={contact}
                                        canDelete={auth.can.manageTeam}
                                        onEdit={() => setEditing(contact)}
                                        onDelete={() => setDeleting(contact)}
                                    />
                                ))}
                            </ul>
                        )}

                        {(contacts.last_page > 1 || filtered) && (
                            <Pagination contacts={contacts} />
                        )}
                    </>
                )}
            </div>

            <ContactDialog
                contact={editing}
                tags={tags}
                onClose={() => setEditing(null)}
            />
            <DeleteDialog
                contact={deleting}
                onClose={() => setDeleting(null)}
            />
        </>
    );
}

function contactLabel(contact: Contact, unnamed: string): string {
    return contact.name ?? unnamed;
}

function FilterBar({ filters, tags }: { filters: Filters; tags: TagOption[] }) {
    const { t } = useTranslations();
    const [q, setQ] = useState(filters.q);
    const first = useRef(true);

    const apply = (next: Partial<Filters>) => {
        const merged = { ...filters, q, ...next };
        router.get(
            index.url(),
            {
                q: merged.q || undefined,
                tag: merged.tag ?? undefined,
                opted_out: merged.opted_out ? 1 : undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Search as the person types, once they pause.
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(() => apply({ q }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const filtered =
        filters.q !== '' || filters.tag !== null || filters.opted_out;

    return (
        <div className="flex flex-wrap items-center gap-2">
            <div className="relative w-full min-w-0 sm:w-auto sm:max-w-sm sm:flex-1">
                <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    value={q}
                    onChange={(event) => setQ(event.target.value)}
                    aria-label={t('contacts.search')}
                    placeholder={t('contacts.search')}
                    className="h-10 ps-9"
                />
            </div>
            <select
                value={filters.tag ?? ''}
                onChange={(event) =>
                    apply({
                        tag: event.target.value
                            ? Number(event.target.value)
                            : null,
                    })
                }
                aria-label={t('contacts.columns.tags')}
                className="h-10 rounded-md border border-input bg-card px-3 text-body-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="">{t('contacts.all_tags')}</option>
                {tags.map((tag) => (
                    <option key={tag.id} value={tag.id}>
                        {tag.name}
                    </option>
                ))}
            </select>
            <button
                type="button"
                aria-pressed={filters.opted_out}
                onClick={() => apply({ opted_out: !filters.opted_out })}
                className={cn(
                    'inline-flex h-10 items-center gap-2 rounded-full border px-4 text-body-sm transition-colors',
                    'focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                    filters.opted_out
                        ? 'border-primary bg-accent font-bold text-accent-foreground'
                        : 'bg-card text-muted-foreground hover:text-foreground',
                )}
            >
                <BellOff className="size-4" />
                {t('contacts.opted_out_filter')}
            </button>
            {filtered && (
                <Button
                    variant="link"
                    onClick={() => {
                        setQ('');
                        router.get(index.url(), {}, { preserveScroll: true });
                    }}
                >
                    {t('contacts.clear_filters')}
                </Button>
            )}
        </div>
    );
}

function ContactRow({
    contact,
    canDelete,
    onEdit,
    onDelete,
}: {
    contact: Contact;
    canDelete: boolean;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const { t, dir } = useTranslations();
    const getInitials = useInitials();
    const label = contactLabel(contact, t('contacts.unnamed'));

    return (
        <li className="flex flex-wrap items-center gap-x-4 gap-y-2 border-t px-4 py-3.5 first:border-t-0 sm:px-5">
            <Avatar className="size-10">
                <AvatarFallback className="bg-secondary font-bold text-secondary-foreground">
                    {contact.name ? (
                        getInitials(contact.name)
                    ) : (
                        <ContactIcon className="size-4" />
                    )}
                </AvatarFallback>
            </Avatar>

            <button
                type="button"
                onClick={onEdit}
                className="flex min-w-0 flex-1 flex-col items-start gap-0.5 rounded-md text-start focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <span
                    className={cn(
                        'max-w-full truncate text-body font-bold',
                        !contact.name && 'font-normal text-muted-foreground',
                    )}
                >
                    {label}
                </span>
                <span className="flex max-w-full items-center gap-2 text-body-sm text-muted-foreground">
                    <bdi dir="ltr" className="font-mono tabular">
                        {formatPhone(contact.phone)}
                    </bdi>
                    {contact.company && (
                        <span className="truncate">· {contact.company}</span>
                    )}
                </span>
            </button>

            <div className="order-last flex w-full flex-wrap items-center gap-1.5 ps-14 sm:order-none sm:w-auto sm:max-w-[45%] sm:justify-end sm:ps-0">
                {contact.opted_out && (
                    <Badge variant="neutral" className="gap-1 rounded-full">
                        <BellOff />
                        {t('contacts.opted_out')}
                    </Badge>
                )}
                {contact.tags.map((tag) => (
                    <CategoryTag key={tag.id} tone={tag.color}>
                        {tag.name}
                    </CategoryTag>
                ))}
            </div>

            <DropdownMenu dir={dir}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={t('contacts.actions.menu', { name: label })}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={onEdit}>
                        {t('contacts.actions.edit')}
                    </DropdownMenuItem>
                    {canDelete && (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={onDelete}
                        >
                            {t('contacts.actions.delete')}
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </li>
    );
}

function Pagination({ contacts }: { contacts: Paginated<Contact> }) {
    const { t } = useTranslations();

    return (
        <nav className="flex items-center justify-between gap-4 text-body-sm">
            <span className="text-muted-foreground tabular">
                {contacts.from !== null &&
                    t('contacts.pagination.summary', {
                        from: String(contacts.from),
                        to: String(contacts.to),
                        total: String(contacts.total),
                    })}
            </span>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!contacts.prev_page_url}
                    asChild={!!contacts.prev_page_url}
                >
                    {contacts.prev_page_url ? (
                        <Link href={contacts.prev_page_url} preserveScroll>
                            <BackChevron />
                            {t('contacts.pagination.previous')}
                        </Link>
                    ) : (
                        <span>
                            <BackChevron />
                            {t('contacts.pagination.previous')}
                        </span>
                    )}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!contacts.next_page_url}
                    asChild={!!contacts.next_page_url}
                >
                    {contacts.next_page_url ? (
                        <Link href={contacts.next_page_url} preserveScroll>
                            {t('contacts.pagination.next')}
                            <ForwardChevron />
                        </Link>
                    ) : (
                        <span>
                            {t('contacts.pagination.next')}
                            <ForwardChevron />
                        </span>
                    )}
                </Button>
            </div>
        </nav>
    );
}

function EmptyState({ onAdd }: { onAdd: () => void }) {
    const { t } = useTranslations();
    const { auth } = usePage().props;

    return (
        <div className="flex flex-col items-center gap-4 rounded-2xl border border-dashed bg-card px-6 py-14 text-center">
            <span className="grid size-14 place-items-center rounded-2xl bg-accent text-accent-foreground">
                <ContactIcon className="size-6" />
            </span>
            <div className="flex max-w-md flex-col gap-1.5">
                <h2 className="text-title font-bold">
                    {t('contacts.empty.title')}
                </h2>
                <p className="text-body-sm text-muted-foreground">
                    {t('contacts.empty.body')}
                </p>
            </div>
            <div className="flex flex-wrap justify-center gap-2">
                <Button onClick={onAdd}>
                    <Plus />
                    {t('contacts.add')}
                </Button>
                {auth.can.manageTeam && (
                    <Button variant="outline" asChild>
                        <Link href={importPage()}>
                            <Upload />
                            {t('contacts.import_button')}
                        </Link>
                    </Button>
                )}
            </div>
        </div>
    );
}

type ContactForm = {
    name: string;
    phone: string;
    email: string;
    company: string;
    notes: string;
    opted_out: boolean;
    tags: number[];
};

function ContactDialog({
    contact,
    tags,
    onClose,
}: {
    contact: Contact | 'new' | null;
    tags: TagOption[];
    onClose: () => void;
}) {
    const { t } = useTranslations();
    const form = useForm<ContactForm>({
        name: '',
        phone: '',
        email: '',
        company: '',
        notes: '',
        opted_out: false,
        tags: [],
    });
    const editing = contact !== null && contact !== 'new' ? contact : null;

    useEffect(() => {
        if (contact === null) {
            return;
        }

        form.clearErrors();
        form.setData({
            name: editing?.name ?? '',
            phone: editing?.phone ?? '',
            email: editing?.email ?? '',
            company: editing?.company ?? '',
            notes: editing?.notes ?? '',
            opted_out: editing?.opted_out ?? false,
            tags: editing?.tags.map((tag) => tag.id) ?? [],
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [contact]);

    const toggleTag = (id: number) =>
        form.setData(
            'tags',
            form.data.tags.includes(id)
                ? form.data.tags.filter((tag) => tag !== id)
                : [...form.data.tags, id],
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(editing ? update(editing.id) : store(), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Dialog
            open={contact !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                <DialogTitle>
                    {editing
                        ? t('contacts.form.edit_title')
                        : t('contacts.form.add_title')}
                </DialogTitle>
                <DialogDescription className="sr-only">
                    {t('contacts.description')}
                </DialogDescription>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="contact-name">
                                {t('contacts.form.name')}
                            </Label>
                            <Input
                                id="contact-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                autoComplete="off"
                                className="h-10"
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="contact-phone">
                                {t('contacts.form.phone')}
                            </Label>
                            <Input
                                id="contact-phone"
                                dir="ltr"
                                inputMode="tel"
                                value={form.data.phone}
                                onChange={(event) =>
                                    form.setData('phone', event.target.value)
                                }
                                required
                                autoComplete="off"
                                placeholder="+966 5x xxx xxxx"
                                className="h-10 text-start font-mono"
                                aria-describedby="contact-phone-hint"
                            />
                            <p
                                id="contact-phone-hint"
                                className="text-caption text-muted-foreground"
                            >
                                {t('contacts.form.phone_hint')}
                            </p>
                            <InputError message={form.errors.phone} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="contact-email">
                                {t('contacts.form.email')}
                            </Label>
                            <Input
                                id="contact-email"
                                type="email"
                                dir="ltr"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                autoComplete="off"
                                className="h-10 text-start"
                            />
                            <InputError message={form.errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="contact-company">
                                {t('contacts.form.company')}
                            </Label>
                            <Input
                                id="contact-company"
                                value={form.data.company}
                                onChange={(event) =>
                                    form.setData('company', event.target.value)
                                }
                                autoComplete="off"
                                className="h-10"
                            />
                            <InputError message={form.errors.company} />
                        </div>
                    </div>

                    <fieldset className="grid gap-2">
                        <legend className="mb-2 text-sm font-medium">
                            {t('contacts.form.tags')}
                        </legend>
                        {tags.length === 0 ? (
                            <p className="text-caption text-muted-foreground">
                                {t('contacts.form.no_tags')}
                            </p>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {tags.map((tag) => {
                                    const selected = form.data.tags.includes(
                                        tag.id,
                                    );

                                    return (
                                        <button
                                            key={tag.id}
                                            type="button"
                                            aria-pressed={selected}
                                            onClick={() => toggleTag(tag.id)}
                                            className={cn(
                                                'rounded-full ring-offset-2 ring-offset-background transition',
                                                'focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                                selected
                                                    ? 'ring-2 ring-primary'
                                                    : 'opacity-60 hover:opacity-100',
                                            )}
                                        >
                                            <CategoryTag tone={tag.color}>
                                                {tag.name}
                                            </CategoryTag>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </fieldset>

                    <div className="grid gap-2">
                        <Label htmlFor="contact-notes">
                            {t('contacts.form.notes')}
                        </Label>
                        <Textarea
                            id="contact-notes"
                            dir="auto"
                            rows={3}
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>

                    <label className="flex items-start gap-3">
                        <Switch
                            checked={form.data.opted_out}
                            onCheckedChange={(optedOut) =>
                                form.setData('opted_out', optedOut)
                            }
                            className="mt-0.5"
                        />
                        <span className="flex flex-col gap-0.5">
                            <span className="text-body-sm font-bold">
                                {t('contacts.form.opted_out')}
                            </span>
                            <span className="text-caption text-muted-foreground">
                                {t('contacts.form.opted_out_hint')}
                            </span>
                        </span>
                    </label>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.cancel')}
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {t('contacts.form.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteDialog({
    contact,
    onClose,
}: {
    contact: Contact | null;
    onClose: () => void;
}) {
    const { t } = useTranslations();
    const [processing, setProcessing] = useState(false);
    const label = contact ? contactLabel(contact, contact.phone) : '';

    const confirm = () => {
        if (!contact) {
            return;
        }

        router.delete(destroy.url(contact.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                onClose();
            },
        });
    };

    return (
        <Dialog
            open={contact !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-md">
                <DialogTitle>
                    {t('contacts.delete_dialog.title', { name: label })}
                </DialogTitle>
                <DialogDescription>
                    {t('contacts.delete_dialog.body')}
                </DialogDescription>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            {t('common.cancel')}
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={processing}
                        onClick={confirm}
                    >
                        {processing && <Spinner />}
                        {t('contacts.delete_dialog.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
