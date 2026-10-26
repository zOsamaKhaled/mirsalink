import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/react';
import {
    BookOpenText,
    CircleCheck,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';

import { ConfirmDeleteDialog } from '@/components/confirm-dialog';
import { BackChevron, ForwardChevron } from '@/components/direction-icons';
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
import { formatDate } from '@/lib/time';
import { cn } from '@/lib/utils';
import { destroy, index, review, store, update } from '@/routes/knowledge';

type Entry = {
    id: number;
    question: string;
    answer: string;
    source: string | null;
    active: boolean;
    needs_review: boolean;
    reviewed_at: string | null;
    reviewed_by: string | null;
};

type Show = 'all' | 'review' | 'inactive';

type Props = {
    entries: {
        data: Entry[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { q: string; show: Show };
    counts: Record<Show, number>;
    reviewDays: number;
};

export default function Knowledge({
    entries,
    filters,
    counts,
    reviewDays,
}: Props) {
    const { t } = useTranslations();
    const { auth } = usePage().props;
    const manage = auth.can.manageTeam;
    const [editing, setEditing] = useState<Entry | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Entry | null>(null);

    setLayoutProps({
        breadcrumbs: [{ title: t('knowledge.title'), href: index() }],
    });

    const visit = (next: Partial<Props['filters']>) => {
        const merged = { ...filters, ...next };
        router.get(
            index.url(),
            {
                q: merged.q || undefined,
                show: merged.show === 'all' ? undefined : merged.show,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title={t('knowledge.title')} />

            <div className="flex flex-col gap-6 px-4 py-8 sm:px-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="flex max-w-2xl flex-col gap-1.5">
                        <h1 className="text-display font-extrabold">
                            {t('knowledge.title')}
                        </h1>
                        <p className="text-body-sm text-muted-foreground">
                            {t('knowledge.description')}
                        </p>
                    </div>
                    {manage && (
                        <Button onClick={() => setEditing('new')}>
                            <Plus />
                            {t('knowledge.add')}
                        </Button>
                    )}
                </header>

                {counts.all === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed p-10 text-center">
                        <BookOpenText className="size-8 text-muted-foreground" />
                        <p className="font-semibold">{t('knowledge.empty')}</p>
                        <p className="text-body-sm text-muted-foreground">
                            {t('knowledge.empty_hint')}
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-3">
                            <SearchBox
                                value={filters.q}
                                onSearch={(q) => visit({ q })}
                            />
                            <div className="flex gap-1 rounded-full bg-muted p-1">
                                {(['all', 'review', 'inactive'] as const).map(
                                    (show) => (
                                        <button
                                            key={show}
                                            type="button"
                                            onClick={() => visit({ show })}
                                            aria-pressed={filters.show === show}
                                            className={cn(
                                                'inline-flex h-8 items-center gap-1.5 rounded-full px-3 text-body-sm transition-colors',
                                                filters.show === show
                                                    ? 'bg-card font-semibold shadow-raised'
                                                    : 'text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            {t(`knowledge.filters.${show}`)}
                                            <span
                                                className={cn(
                                                    'text-caption tabular opacity-70',
                                                    show === 'review' &&
                                                        counts.review > 0 &&
                                                        'font-bold text-warning opacity-100',
                                                )}
                                            >
                                                {counts[show]}
                                            </span>
                                        </button>
                                    ),
                                )}
                            </div>
                        </div>

                        {entries.data.length === 0 ? (
                            <p className="rounded-2xl border bg-card p-8 text-center text-body-sm text-muted-foreground">
                                {t('knowledge.no_results')}
                            </p>
                        ) : (
                            <ul className="grid gap-4 xl:grid-cols-2">
                                {entries.data.map((entry) => (
                                    <EntryCard
                                        key={entry.id}
                                        entry={entry}
                                        manage={manage}
                                        reviewDays={reviewDays}
                                        onEdit={() => setEditing(entry)}
                                        onDelete={() => setDeleting(entry)}
                                    />
                                ))}
                            </ul>
                        )}

                        {entries.last_page > 1 && (
                            <nav className="flex items-center justify-center gap-2">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    disabled={!entries.prev_page_url}
                                    asChild={!!entries.prev_page_url}
                                >
                                    {entries.prev_page_url ? (
                                        <Link
                                            href={entries.prev_page_url}
                                            preserveScroll
                                        >
                                            <BackChevron />
                                        </Link>
                                    ) : (
                                        <BackChevron />
                                    )}
                                </Button>
                                <span className="text-body-sm tabular">
                                    {entries.current_page} / {entries.last_page}
                                </span>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    disabled={!entries.next_page_url}
                                    asChild={!!entries.next_page_url}
                                >
                                    {entries.next_page_url ? (
                                        <Link
                                            href={entries.next_page_url}
                                            preserveScroll
                                        >
                                            <ForwardChevron />
                                        </Link>
                                    ) : (
                                        <ForwardChevron />
                                    )}
                                </Button>
                            </nav>
                        )}
                    </>
                )}
            </div>

            <EntryDialog entry={editing} onClose={() => setEditing(null)} />
            <ConfirmDeleteDialog
                url={deleting ? destroy.url(deleting.id) : null}
                title={t('knowledge.delete_title')}
                body={t('knowledge.delete_body')}
                confirmLabel={t('knowledge.delete')}
                onClose={() => setDeleting(null)}
            />
        </>
    );
}

function SearchBox({
    value,
    onSearch,
}: {
    value: string;
    onSearch: (q: string) => void;
}) {
    const { t } = useTranslations();
    const [q, setQ] = useState(value);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(() => onSearch(q), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    return (
        <div className="relative w-full max-w-sm">
            <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                type="search"
                value={q}
                onChange={(event) => setQ(event.target.value)}
                placeholder={t('knowledge.search')}
                aria-label={t('knowledge.search')}
                className="h-10 ps-9"
            />
        </div>
    );
}

function EntryCard({
    entry,
    manage,
    reviewDays,
    onEdit,
    onDelete,
}: {
    entry: Entry;
    manage: boolean;
    reviewDays: number;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const { t, locale } = useTranslations();

    return (
        <li
            className={cn(
                'flex flex-col gap-3 rounded-2xl border bg-card p-4',
                !entry.active && 'opacity-70',
            )}
        >
            <div className="flex items-start gap-2">
                <h2 dir="auto" className="min-w-0 flex-1 font-bold">
                    {entry.question}
                </h2>
                {manage && (
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
                            <DropdownMenuItem onSelect={onEdit}>
                                <Pencil />
                                {t('knowledge.edit')}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={onDelete}
                            >
                                <Trash2 />
                                {t('knowledge.delete')}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
            <p
                dir="auto"
                className="text-body-sm whitespace-pre-wrap text-foreground/90"
            >
                {entry.answer}
            </p>
            {entry.source && (
                <p className="text-caption text-muted-foreground">
                    {t('knowledge.source', { source: entry.source })}
                </p>
            )}
            <div className="mt-auto flex flex-wrap items-center gap-2 border-t pt-3 text-caption text-muted-foreground">
                {!entry.active && (
                    <span className="rounded-full bg-muted px-2.5 py-1 font-medium">
                        {t('knowledge.inactive')}
                    </span>
                )}
                {entry.active && entry.needs_review ? (
                    <span
                        className="rounded-full bg-warning-soft px-2.5 py-1 font-medium text-warning"
                        title={t('knowledge.needs_review_hint', {
                            days: String(reviewDays),
                        })}
                    >
                        {t('knowledge.needs_review')}
                    </span>
                ) : (
                    entry.reviewed_at && (
                        <span>
                            {t('knowledge.reviewed', {
                                date: formatDate(entry.reviewed_at, locale),
                                name: entry.reviewed_by ?? '',
                            })}
                        </span>
                    )
                )}
                {manage && entry.active && entry.needs_review && (
                    <Button
                        size="sm"
                        variant="outline"
                        className="ms-auto h-7 rounded-full"
                        onClick={() =>
                            router.post(
                                review.url(entry.id),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <CircleCheck />
                        {t('knowledge.mark_reviewed')}
                    </Button>
                )}
            </div>
        </li>
    );
}

function EntryDialog({
    entry,
    onClose,
}: {
    entry: Entry | 'new' | null;
    onClose: () => void;
}) {
    const { t } = useTranslations();
    const editing = entry !== null && entry !== 'new' ? entry : null;
    const form = useForm({
        question: '',
        answer: '',
        source: '',
        active: true,
    });

    useEffect(() => {
        if (entry === null) {
            return;
        }

        form.clearErrors();
        form.setData({
            question: editing?.question ?? '',
            answer: editing?.answer ?? '',
            source: editing?.source ?? '',
            active: editing?.active ?? true,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [entry]);

    const save = (event: FormEvent) => {
        event.preventDefault();
        form.submit(editing ? update(editing.id) : store(), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Dialog
            open={entry !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                <DialogTitle>
                    {editing
                        ? t('knowledge.form.edit_title')
                        : t('knowledge.form.create_title')}
                </DialogTitle>
                <DialogDescription className="sr-only">
                    {t('knowledge.description')}
                </DialogDescription>

                <form onSubmit={save} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="entry-question">
                            {t('knowledge.form.question')}
                        </Label>
                        <Input
                            id="entry-question"
                            dir="auto"
                            value={form.data.question}
                            onChange={(event) =>
                                form.setData('question', event.target.value)
                            }
                            className="h-10"
                        />
                        <p className="text-caption text-muted-foreground">
                            {t('knowledge.form.question_hint')}
                        </p>
                        <InputError message={form.errors.question} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="entry-answer">
                            {t('knowledge.form.answer')}
                        </Label>
                        <Textarea
                            id="entry-answer"
                            dir="auto"
                            rows={6}
                            value={form.data.answer}
                            onChange={(event) =>
                                form.setData('answer', event.target.value)
                            }
                        />
                        <InputError message={form.errors.answer} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="entry-source">
                            {t('knowledge.form.source')}
                        </Label>
                        <Input
                            id="entry-source"
                            dir="auto"
                            value={form.data.source}
                            onChange={(event) =>
                                form.setData('source', event.target.value)
                            }
                            className="h-10"
                        />
                        <p className="text-caption text-muted-foreground">
                            {t('knowledge.form.source_hint')}
                        </p>
                        <InputError message={form.errors.source} />
                    </div>
                    <label className="flex items-start gap-3 rounded-xl border p-3">
                        <Switch
                            checked={form.data.active}
                            onCheckedChange={(checked) =>
                                form.setData('active', checked)
                            }
                        />
                        <span className="flex flex-col gap-0.5">
                            <span className="text-body-sm font-semibold">
                                {t('knowledge.form.active')}
                            </span>
                            <span className="text-caption text-muted-foreground">
                                {t('knowledge.form.active_hint')}
                            </span>
                        </span>
                    </label>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('knowledge.form.cancel')}
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {t('knowledge.form.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
