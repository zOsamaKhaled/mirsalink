import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { FileSpreadsheet, Paperclip } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

import { CategoryTag } from '@/components/inbox/category-tag';
import type { TagTone } from '@/components/inbox/category-tag';
import { TemplatePreview } from '@/components/inbox/template-preview';
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
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { index, show, store, update } from '@/routes/broadcasts';
import { index as templatesPage } from '@/routes/templates';
import type {
    AudienceType,
    BroadcastVariables,
    VariableSource,
    VariableSpec,
} from '@/types/broadcasts';
import type { WhatsAppTemplate } from '@/types/templates';

type Props = {
    broadcast: {
        id: number;
        name: string;
        template_id: number | null;
        audience_type: AudienceType;
        audience_tags: number[];
        has_file: boolean;
        file_columns: string[];
        variables: BroadcastVariables;
    } | null;
    templates: WhatsAppTemplate[];
    tags: { id: number; name: string; color: TagTone }[];
    reachable: number;
};

type BroadcastForm = {
    name: string;
    template_id: number | null;
    audience_type: AudienceType;
    audience_tags: number[];
    file: File | null;
    variables: BroadcastVariables;
};

const SOURCES: VariableSource[] = [
    'name',
    'company',
    'phone',
    'column',
    'text',
];

/** A fresh mapping for a template: the customer's name first, fixed text after. */
function variablesFor(
    template: WhatsAppTemplate | undefined,
): BroadcastVariables {
    if (!template) {
        return { body: [] };
    }

    return {
        body: Array.from({ length: template.variables.body }, (_, position) =>
            position === 0
                ? { source: 'name', fallback: '' }
                : { source: 'text', value: '' },
        ),
        header: template.variables.header
            ? { source: 'text', value: '' }
            : null,
        button: template.variables.button
            ? { source: 'text', value: '' }
            : null,
    };
}

/** The first line of a CSV file, read in the browser, for its column names. */
function readHeadings(file: File, done: (headings: string[]) => void): void {
    const reader = new FileReader();
    reader.onload = () => {
        const text = typeof reader.result === 'string' ? reader.result : '';
        const first = text.replace(/^﻿/, '').split(/\r?\n/)[0] ?? '';
        const delimiter = [',', ';', '\t'].reduce((best, candidate) =>
            first.split(candidate).length > first.split(best).length
                ? candidate
                : best,
        );
        done(
            first
                .split(delimiter)
                .map((heading) => heading.replace(/^"|"$/g, '').trim())
                .filter(Boolean),
        );
    };
    reader.readAsText(file.slice(0, 64 * 1024));
}

export default function BroadcastFormPage({
    broadcast,
    templates,
    tags,
    reachable,
}: Props) {
    const { t } = useTranslations();
    const [columns, setColumns] = useState<string[]>(
        broadcast?.file_columns ?? [],
    );
    const form = useForm<BroadcastForm>({
        name: broadcast?.name ?? '',
        template_id: broadcast?.template_id ?? templates[0]?.id ?? null,
        audience_type: broadcast?.audience_type ?? 'all',
        audience_tags: broadcast?.audience_tags ?? [],
        file: null,
        variables:
            broadcast?.variables ??
            variablesFor(
                templates.find((template) => template.id === templates[0]?.id),
            ),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const template = templates.find(
        (item) => item.id === form.data.template_id,
    );
    const title = broadcast
        ? t('broadcasts.form.edit_title')
        : t('broadcasts.form.create_title');

    setLayoutProps({
        breadcrumbs: [
            { title: t('broadcasts.title'), href: index() },
            { title, href: '#' },
        ],
    });

    const chooseTemplate = (id: number) =>
        form.setData((data) => ({
            ...data,
            template_id: id,
            variables: variablesFor(templates.find((item) => item.id === id)),
        }));

    const setSpec = (
        key: 'header' | 'button' | number,
        changes: Partial<VariableSpec>,
    ) =>
        form.setData('variables', {
            ...form.data.variables,
            ...(typeof key === 'number'
                ? {
                      body: form.data.variables.body.map((spec, at) =>
                          at === key ? { ...spec, ...changes } : spec,
                      ),
                  }
                : { [key]: { ...form.data.variables[key], ...changes } }),
        });

    const save = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => {
            const variables: Record<string, unknown> = {
                body: data.variables.body,
            };

            if (data.variables.header) {
                variables.header = data.variables.header;
            }

            if (data.variables.button) {
                variables.button = data.variables.button;
            }

            return { ...data, variables };
        });
        form.post(broadcast ? update.url(broadcast.id) : store.url(), {
            forceFormData: true,
        });
    };

    // What the preview shows for a variable: its fixed text, or the source's name.
    const shown = (spec: VariableSpec | null | undefined): string => {
        if (!spec) {
            return '';
        }

        if (spec.source === 'text') {
            return spec.value ?? '';
        }

        if (spec.source === 'column') {
            return spec.value ? `‹${spec.value}›` : '';
        }

        return `‹${t(`broadcasts.form.source.${spec.source}`)}›`;
    };

    return (
        <>
            <Head title={title} />

            <form
                onSubmit={save}
                className="grid items-start gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
            >
                <div className="flex max-w-3xl flex-col gap-7">
                    <h1 className="text-display font-extrabold">{title}</h1>

                    <Field
                        label={t('broadcasts.form.name')}
                        htmlFor="broadcast-name"
                        hint={t('broadcasts.form.name_hint')}
                        error={errors.name}
                    >
                        <Input
                            id="broadcast-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            className="h-10"
                        />
                    </Field>

                    <Field
                        label={t('broadcasts.form.template')}
                        hint={t('broadcasts.form.template_hint')}
                        error={errors.template_id ?? errors.variables}
                    >
                        {templates.length === 0 ? (
                            <div className="flex flex-wrap items-center gap-3 rounded-xl border border-dashed p-4">
                                <p className="text-body-sm text-muted-foreground">
                                    {t('broadcasts.form.no_templates')}
                                </p>
                                <Button variant="outline" size="sm" asChild>
                                    <Link
                                        href={templatesPage({
                                            query: { tab: 'templates' },
                                        })}
                                    >
                                        {t('broadcasts.form.manage_templates')}
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <Select
                                value={
                                    form.data.template_id
                                        ? String(form.data.template_id)
                                        : undefined
                                }
                                onValueChange={(value) =>
                                    chooseTemplate(Number(value))
                                }
                            >
                                <SelectTrigger
                                    className="h-10"
                                    aria-label={t('broadcasts.form.template')}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {templates.map((item) => (
                                        <SelectItem
                                            key={item.id}
                                            value={String(item.id)}
                                        >
                                            <bdi
                                                dir="ltr"
                                                className="font-mono"
                                            >
                                                {item.name}
                                            </bdi>
                                            <span className="text-muted-foreground">
                                                {t(
                                                    `templates.category.${item.category}`,
                                                )}
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>

                    <Field
                        label={t('broadcasts.form.audience')}
                        error={errors.audience_tags ?? errors.file}
                    >
                        <div className="flex flex-wrap gap-1 self-start rounded-full bg-muted p-1">
                            {(['all', 'tags', 'csv'] as const).map((type) => (
                                <button
                                    key={type}
                                    type="button"
                                    onClick={() =>
                                        form.setData('audience_type', type)
                                    }
                                    aria-pressed={
                                        form.data.audience_type === type
                                    }
                                    className={cn(
                                        'h-8 rounded-full px-3.5 text-body-sm transition-colors',
                                        form.data.audience_type === type
                                            ? 'bg-card font-semibold shadow-raised'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {t(`broadcasts.audience.${type}`)}
                                </button>
                            ))}
                        </div>

                        {form.data.audience_type === 'all' && (
                            <p className="text-body-sm text-muted-foreground">
                                {t('broadcasts.form.reachable', {
                                    count: reachable.toLocaleString('en'),
                                })}
                            </p>
                        )}

                        {form.data.audience_type === 'tags' && (
                            <div className="flex flex-col gap-2">
                                <div className="flex flex-wrap gap-2">
                                    {tags.map((tag) => {
                                        const chosen =
                                            form.data.audience_tags.includes(
                                                tag.id,
                                            );

                                        return (
                                            <button
                                                key={tag.id}
                                                type="button"
                                                aria-pressed={chosen}
                                                onClick={() =>
                                                    form.setData(
                                                        'audience_tags',
                                                        chosen
                                                            ? form.data.audience_tags.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      tag.id,
                                                              )
                                                            : [
                                                                  ...form.data
                                                                      .audience_tags,
                                                                  tag.id,
                                                              ],
                                                    )
                                                }
                                                className={cn(
                                                    'rounded-full transition-opacity',
                                                    chosen
                                                        ? 'ring-2 ring-ring ring-offset-2 ring-offset-background'
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
                                <p className="text-caption text-muted-foreground">
                                    {t('broadcasts.form.tags_hint')}
                                </p>
                            </div>
                        )}

                        {form.data.audience_type === 'csv' && (
                            <div className="flex flex-col gap-2">
                                <label className="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 text-body-sm focus-within:ring-[3px] focus-within:ring-ring/50">
                                    <input
                                        type="file"
                                        accept=".csv,text/csv"
                                        className="sr-only"
                                        onChange={(event) => {
                                            const file =
                                                event.target.files?.[0] ?? null;
                                            form.setData('file', file);

                                            if (file) {
                                                readHeadings(file, setColumns);
                                            }
                                        }}
                                    />
                                    <span className="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-full bg-secondary px-3 font-medium text-secondary-foreground">
                                        <Paperclip className="size-3.5" />
                                        {t('broadcasts.form.choose_file')}
                                    </span>
                                    <span
                                        dir="auto"
                                        className="min-w-0 truncate text-muted-foreground"
                                    >
                                        {form.data.file?.name ??
                                            (broadcast?.has_file
                                                ? t('broadcasts.form.file_kept')
                                                : t('broadcasts.form.no_file'))}
                                    </span>
                                </label>
                                <p className="text-caption text-muted-foreground">
                                    {t('broadcasts.form.file_hint')}
                                </p>
                                {columns.length > 0 && (
                                    <p className="flex flex-wrap items-center gap-1.5 text-caption text-muted-foreground">
                                        <FileSpreadsheet className="size-3.5" />
                                        {columns.map((column) => (
                                            <span
                                                key={column}
                                                dir="auto"
                                                className="rounded bg-muted px-1.5 py-0.5"
                                            >
                                                {column}
                                            </span>
                                        ))}
                                    </p>
                                )}
                            </div>
                        )}
                    </Field>

                    {template &&
                        (form.data.variables.body.length > 0 ||
                            form.data.variables.header ||
                            form.data.variables.button) && (
                            <Field
                                label={t('broadcasts.form.variables')}
                                hint={t('broadcasts.form.variables_hint')}
                            >
                                <div className="flex flex-col gap-3">
                                    {form.data.variables.header && (
                                        <SpecRow
                                            label={t(
                                                'broadcasts.form.variable_header',
                                            )}
                                            spec={form.data.variables.header}
                                            columns={columns}
                                            csv={
                                                form.data.audience_type ===
                                                'csv'
                                            }
                                            errors={errors}
                                            errorKey="variables.header"
                                            onChange={(changes) =>
                                                setSpec('header', changes)
                                            }
                                        />
                                    )}
                                    {form.data.variables.body.map(
                                        (spec, position) => (
                                            <SpecRow
                                                key={position}
                                                label={`{{${position + 1}}}`}
                                                spec={spec}
                                                columns={columns}
                                                csv={
                                                    form.data.audience_type ===
                                                    'csv'
                                                }
                                                errors={errors}
                                                errorKey={`variables.body.${position}`}
                                                onChange={(changes) =>
                                                    setSpec(position, changes)
                                                }
                                            />
                                        ),
                                    )}
                                    {form.data.variables.button && (
                                        <SpecRow
                                            label={t(
                                                'broadcasts.form.variable_button',
                                            )}
                                            spec={form.data.variables.button}
                                            columns={columns}
                                            csv={
                                                form.data.audience_type ===
                                                'csv'
                                            }
                                            errors={errors}
                                            errorKey="variables.button"
                                            onChange={(changes) =>
                                                setSpec('button', changes)
                                            }
                                        />
                                    )}
                                </div>
                            </Field>
                        )}

                    <div className="flex flex-wrap gap-2 border-t pt-6">
                        <Button
                            type="submit"
                            disabled={form.processing || !template}
                        >
                            {form.processing && <Spinner />}
                            {t('broadcasts.form.save')}
                        </Button>
                        <Button variant="secondary" asChild>
                            <Link
                                href={broadcast ? show(broadcast.id) : index()}
                            >
                                {t('broadcasts.form.cancel')}
                            </Link>
                        </Button>
                    </div>
                </div>

                {template && (
                    <aside className="flex flex-col gap-3 lg:sticky lg:top-6">
                        <h2 className="text-body-sm font-semibold text-muted-foreground">
                            {t('broadcasts.form.preview')}
                        </h2>
                        <div className="rounded-2xl bg-sunken p-4">
                            <TemplatePreview
                                headerType={template.header_type}
                                headerText={template.header_text}
                                headerValue={shown(form.data.variables.header)}
                                body={template.body}
                                values={form.data.variables.body.map(shown)}
                                footer={template.footer}
                                buttons={template.buttons}
                            />
                        </div>
                    </aside>
                )}
            </form>
        </>
    );
}

function SpecRow({
    label,
    spec,
    columns,
    csv,
    errors,
    errorKey,
    onChange,
}: {
    label: string;
    spec: VariableSpec;
    columns: string[];
    csv: boolean;
    errors: Record<string, string | undefined>;
    errorKey: string;
    onChange: (changes: Partial<VariableSpec>) => void;
}) {
    const { t } = useTranslations();
    const fromContact = ['name', 'company', 'phone'].includes(spec.source);
    // {{1}} reads left to right in a code face; a worded label stays as written.
    const numbered = label.startsWith('{{');

    return (
        <div className="grid items-start gap-2 rounded-xl border p-3 sm:grid-cols-[5.5rem_11rem_minmax(0,1fr)]">
            <bdi
                dir={numbered ? 'ltr' : 'auto'}
                className={cn(
                    'pt-2.5 text-body-sm text-muted-foreground',
                    numbered && 'font-mono',
                )}
            >
                {label}
            </bdi>
            <Select
                value={spec.source}
                onValueChange={(source) =>
                    onChange({ source: source as VariableSource, value: '' })
                }
            >
                <SelectTrigger className="h-10" aria-label={label}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {SOURCES.filter((source) => source !== 'column' || csv).map(
                        (source) => (
                            <SelectItem key={source} value={source}>
                                {t(`broadcasts.form.source.${source}`)}
                            </SelectItem>
                        ),
                    )}
                </SelectContent>
            </Select>
            <div className="grid gap-1.5">
                {spec.source === 'text' && (
                    <Input
                        dir="auto"
                        value={spec.value ?? ''}
                        onChange={(event) =>
                            onChange({ value: event.target.value })
                        }
                        placeholder={t('broadcasts.form.value')}
                        aria-label={t('broadcasts.form.value')}
                        className="h-10"
                    />
                )}
                {spec.source === 'column' && (
                    <Select
                        value={spec.value ?? undefined}
                        onValueChange={(value) => onChange({ value })}
                    >
                        <SelectTrigger
                            className="h-10"
                            aria-label={t('broadcasts.form.column')}
                        >
                            <SelectValue
                                placeholder={t('broadcasts.form.column')}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {columns.map((column) => (
                                <SelectItem key={column} value={column}>
                                    {column}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
                {(fromContact || spec.source === 'column') && (
                    <Input
                        dir="auto"
                        value={spec.fallback ?? ''}
                        onChange={(event) =>
                            onChange({ fallback: event.target.value })
                        }
                        placeholder={t('broadcasts.form.fallback')}
                        aria-label={t('broadcasts.form.fallback')}
                        className="h-10"
                    />
                )}
                <InputError
                    message={
                        errors[`${errorKey}.value`] ??
                        errors[`${errorKey}.source`]
                    }
                />
            </div>
        </div>
    );
}

function Field({
    label,
    htmlFor,
    hint,
    error,
    children,
}: {
    label: string;
    htmlFor?: string;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {hint && (
                <p className="text-caption text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}
