import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';

import { AgentStatusDot } from '@/components/inbox/agent-status';
import { CategoryTag, TAG_TONES } from '@/components/inbox/category-tag';
import type { TagTone } from '@/components/inbox/category-tag';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { index } from '@/routes/routing';
import { update as updateRule } from '@/routes/routing/rules';
import { destroy, store, update } from '@/routes/routing/tags';
import type { Availability, Role } from '@/types';

type Method = 'round_robin' | 'least_busy';
type Rule = { method: Method; members: number[] };
type Tag = {
    id: number;
    name: string;
    color: TagTone;
    description: string | null;
    contacts_count: number;
    rule: Rule;
};
type Member = {
    id: number;
    name: string;
    role: Role;
    availability: Availability;
};

type Props = { tags: Tag[]; fallback: Rule; members: Member[] };

export default function Routing({ tags, fallback, members }: Props) {
    const { t } = useTranslations();
    const [editing, setEditing] = useState<Tag | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Tag | null>(null);

    setLayoutProps({
        breadcrumbs: [{ title: t('routing.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('routing.title')} />

            <div className="flex flex-col gap-8 px-4 py-8 sm:px-6">
                <header className="flex flex-col gap-1.5">
                    <h1 className="text-display font-extrabold">
                        {t('routing.title')}
                    </h1>
                    <p className="text-body-sm text-muted-foreground">
                        {t('routing.description')}
                    </p>
                </header>

                <section className="flex max-w-4xl flex-col gap-4 rounded-2xl border bg-card p-5 sm:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="flex max-w-[60ch] flex-col gap-1">
                            <h2 className="text-title font-bold">
                                {t('routing.tags.title')}
                            </h2>
                            <p className="text-body-sm text-muted-foreground">
                                {t('routing.tags.description')}
                            </p>
                        </div>
                        <Button onClick={() => setEditing('new')}>
                            <Plus />
                            {t('routing.tags.add')}
                        </Button>
                    </div>

                    {tags.length === 0 ? (
                        <p className="rounded-xl bg-sunken p-6 text-center text-body-sm text-muted-foreground">
                            {t('routing.tags.empty')}
                        </p>
                    ) : (
                        <ul className="flex flex-col">
                            {tags.map((tag) => (
                                <li
                                    key={tag.id}
                                    className="flex flex-wrap items-center gap-x-4 gap-y-1 border-t py-3 first:border-t-0"
                                >
                                    <CategoryTag tone={tag.color}>
                                        {tag.name}
                                    </CategoryTag>
                                    <p className="min-w-0 flex-1 truncate text-body-sm text-muted-foreground">
                                        {tag.description}
                                    </p>
                                    <span className="text-caption text-muted-foreground tabular">
                                        {t('routing.tags.contacts_count', {
                                            count: String(tag.contacts_count),
                                        })}
                                    </span>
                                    <div className="flex">
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label={t(
                                                'routing.tags.actions.edit',
                                                { name: tag.name },
                                            )}
                                            onClick={() => setEditing(tag)}
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label={t(
                                                'routing.tags.actions.delete',
                                                { name: tag.name },
                                            )}
                                            onClick={() => setDeleting(tag)}
                                            className="text-danger hover:bg-danger-soft hover:text-danger"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="flex flex-col gap-4">
                    <div className="flex max-w-[70ch] flex-col gap-1">
                        <h2 className="text-heading font-bold">
                            {t('routing.rules.title')}
                        </h2>
                        <p className="text-body-sm text-muted-foreground">
                            {t('routing.rules.description')}
                        </p>
                        <p className="text-caption text-muted-foreground">
                            {t('routing.rules.only_available')}
                        </p>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <RuleEditor
                            tag={null}
                            rule={fallback}
                            members={members}
                        />
                        {tags.map((tag) => (
                            <RuleEditor
                                key={tag.id}
                                tag={tag}
                                rule={tag.rule}
                                members={members}
                            />
                        ))}
                    </div>
                </section>
            </div>

            <TagDialog tag={editing} onClose={() => setEditing(null)} />
            <DeleteTagDialog tag={deleting} onClose={() => setDeleting(null)} />
        </>
    );
}

function RuleEditor({
    tag,
    rule,
    members,
}: {
    tag: Tag | null;
    rule: Rule;
    members: Member[];
}) {
    const { t } = useTranslations();
    const form = useForm({
        tag_id: tag?.id ?? null,
        method: rule.method,
        members: rule.members,
    });

    const toggle = (id: number) =>
        form.setData(
            'members',
            form.data.members.includes(id)
                ? form.data.members.filter((member) => member !== id)
                : [...form.data.members, id],
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        // What was saved becomes the starting point, so Save greys out again.
        form.submit(updateRule(), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    const id = tag ? `rule-${tag.id}` : 'rule-default';

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-4 rounded-2xl border bg-card p-5"
            aria-labelledby={`${id}-title`}
        >
            <div className="flex flex-col gap-1">
                <h3
                    id={`${id}-title`}
                    className="flex items-center gap-2 text-title font-bold"
                >
                    {tag ? (
                        <CategoryTag tone={tag.color}>{tag.name}</CategoryTag>
                    ) : (
                        t('routing.rules.fallback_title')
                    )}
                </h3>
                {!tag && (
                    <p className="text-caption text-muted-foreground">
                        {t('routing.rules.fallback_description')}
                    </p>
                )}
            </div>

            <fieldset className="flex flex-col gap-2">
                <legend className="mb-2 text-sm font-medium">
                    {t('routing.rules.method')}
                </legend>
                <div className="grid grid-cols-2 gap-2">
                    {(['round_robin', 'least_busy'] as const).map((method) => (
                        <button
                            key={method}
                            type="button"
                            role="radio"
                            aria-checked={form.data.method === method}
                            onClick={() => form.setData('method', method)}
                            className={cn(
                                'flex flex-col gap-0.5 rounded-xl border p-3 text-start transition-colors',
                                'focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                form.data.method === method
                                    ? 'border-primary bg-accent'
                                    : 'hover:bg-accent/50',
                            )}
                        >
                            <span className="text-body-sm font-bold">
                                {t(`routing.rules.methods.${method}`)}
                            </span>
                            <span className="text-caption text-muted-foreground">
                                {t(`routing.rules.method_hints.${method}`)}
                            </span>
                        </button>
                    ))}
                </div>
            </fieldset>

            <fieldset className="flex flex-col gap-2">
                <legend className="mb-2 flex w-full items-center justify-between text-sm font-medium">
                    {t('routing.rules.members')}
                    <span className="flex items-center gap-1.5 text-caption font-normal text-muted-foreground">
                        <Users className="size-3.5" />
                        {form.data.members.length === 0
                            ? t('routing.rules.whole_team')
                            : t('routing.rules.selected', {
                                  count: String(form.data.members.length),
                              })}
                    </span>
                </legend>
                <div className="flex flex-wrap gap-2">
                    {members.map((member) => {
                        const checked = form.data.members.includes(member.id);
                        const inputId = `${id}-member-${member.id}`;

                        return (
                            <label
                                key={member.id}
                                htmlFor={inputId}
                                className={cn(
                                    'flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-body-sm transition-colors',
                                    checked
                                        ? 'border-primary bg-accent font-bold'
                                        : 'hover:bg-accent/50',
                                )}
                            >
                                <Checkbox
                                    id={inputId}
                                    checked={checked}
                                    onCheckedChange={() => toggle(member.id)}
                                    className="sr-only"
                                />
                                <AgentStatusDot
                                    status={member.availability}
                                    className="ring-0"
                                />
                                {member.name}
                            </label>
                        );
                    })}
                </div>
                {form.data.members.length === 0 && (
                    <p className="text-caption text-muted-foreground">
                        {t('routing.rules.whole_team_hint')}
                    </p>
                )}
                <InputError message={form.errors.members} />
            </fieldset>

            <div>
                <Button
                    type="submit"
                    size="sm"
                    variant={form.isDirty ? 'default' : 'secondary'}
                    disabled={form.processing || !form.isDirty}
                >
                    {form.processing && <Spinner />}
                    {t('routing.rules.save')}
                </Button>
            </div>
        </form>
    );
}

function TagDialog({
    tag,
    onClose,
}: {
    tag: Tag | 'new' | null;
    onClose: () => void;
}) {
    const { t } = useTranslations();
    const editing = tag !== null && tag !== 'new' ? tag : null;
    const form = useForm<{ name: string; color: TagTone; description: string }>(
        {
            name: '',
            color: 'blue',
            description: '',
        },
    );

    useEffect(() => {
        if (tag === null) {
            return;
        }

        form.clearErrors();
        form.setData({
            name: editing?.name ?? '',
            color: editing?.color ?? 'blue',
            description: editing?.description ?? '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tag]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(editing ? update(editing.id) : store(), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Dialog open={tag !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>
                    {editing
                        ? t('routing.tags.form.edit_title')
                        : t('routing.tags.form.add_title')}
                </DialogTitle>
                <DialogDescription className="sr-only">
                    {t('routing.tags.description')}
                </DialogDescription>

                <form onSubmit={submit} className="flex flex-col gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="tag-name">
                            {t('routing.tags.form.name')}
                        </Label>
                        <Input
                            id="tag-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                            maxLength={60}
                            autoComplete="off"
                            placeholder={t(
                                'routing.tags.form.name_placeholder',
                            )}
                            className="h-10"
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <fieldset className="grid gap-2">
                        <legend className="mb-2 text-sm font-medium">
                            {t('routing.tags.form.color')}
                        </legend>
                        <div className="flex flex-wrap gap-2">
                            {TAG_TONES.map((tone) => (
                                <button
                                    key={tone}
                                    type="button"
                                    role="radio"
                                    aria-checked={form.data.color === tone}
                                    onClick={() => form.setData('color', tone)}
                                    className={cn(
                                        'rounded-full ring-offset-2 ring-offset-background transition',
                                        'focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                        form.data.color === tone
                                            ? 'ring-2 ring-primary'
                                            : 'opacity-70 hover:opacity-100',
                                    )}
                                >
                                    <CategoryTag tone={tone}>
                                        {form.data.name.trim() ||
                                            t(`routing.colors.${tone}`)}
                                    </CategoryTag>
                                </button>
                            ))}
                        </div>
                    </fieldset>

                    <div className="grid gap-2">
                        <Label htmlFor="tag-description">
                            {t('routing.tags.form.description')}
                        </Label>
                        <Textarea
                            id="tag-description"
                            dir="auto"
                            rows={3}
                            maxLength={500}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            placeholder={t(
                                'routing.tags.form.description_placeholder',
                            )}
                            aria-describedby="tag-description-hint"
                        />
                        <p
                            id="tag-description-hint"
                            className="text-caption text-muted-foreground"
                        >
                            {t('routing.tags.form.description_hint')}
                        </p>
                        <InputError message={form.errors.description} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                {t('common.cancel')}
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {t('routing.tags.form.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteTagDialog({
    tag,
    onClose,
}: {
    tag: Tag | null;
    onClose: () => void;
}) {
    const { t } = useTranslations();
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        if (!tag) {
            return;
        }

        router.delete(destroy.url(tag.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                onClose();
            },
        });
    };

    return (
        <Dialog open={tag !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>
                    {t('routing.tags.delete_dialog.title', {
                        name: tag?.name ?? '',
                    })}
                </DialogTitle>
                <DialogDescription>
                    {t('routing.tags.delete_dialog.body')}
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
                        {t('routing.tags.delete_dialog.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
