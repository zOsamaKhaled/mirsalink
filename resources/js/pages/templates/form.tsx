import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { Braces, Link2, MessageSquare, Phone, Plus, X } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useRef } from 'react';

import { TemplatePreview } from '@/components/inbox/template-preview';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { index, store, update } from '@/routes/templates';
import type {
    TemplateButton,
    TemplateCategory,
    TemplateHeaderType,
    WhatsAppTemplate,
} from '@/types/templates';
import { variableCount, variablesIn } from '@/types/templates';

type TemplateForm = {
    name: string;
    language: 'ar' | 'en';
    category: TemplateCategory;
    header_type: TemplateHeaderType;
    header_text: string;
    header_example: string;
    body: string;
    body_examples: string[];
    footer: string;
    buttons: TemplateButton[];
};

const HEADERS: TemplateHeaderType[] = [
    'none',
    'text',
    'image',
    'document',
    'video',
];

export default function TemplateFormPage({
    template,
}: {
    template: WhatsAppTemplate | null;
}) {
    const { t } = useTranslations();
    const bodyField = useRef<HTMLTextAreaElement>(null);
    const form = useForm<TemplateForm>({
        name: template?.name ?? '',
        language: template?.language ?? 'ar',
        category: template?.category ?? 'utility',
        header_type: template?.header_type ?? 'none',
        header_text: template?.header_text ?? '',
        header_example: template?.header_example ?? '',
        body: template?.body ?? '',
        body_examples: template?.body_examples ?? [],
        footer: template?.footer ?? '',
        buttons: template?.buttons ?? [],
    });
    const title = template
        ? t('templates.form.edit_title')
        : t('templates.form.create_title');
    const errors = form.errors as Record<string, string | undefined>;

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('templates.title'),
                href: index({ query: { tab: 'templates' } }),
            },
            { title, href: '#' },
        ],
    });

    const variables = variableCount(form.data.body);
    const examples = Array.from(
        { length: variables },
        (_, position) => form.data.body_examples[position] ?? '',
    );
    const headerVariable =
        form.data.header_type === 'text' &&
        variablesIn(form.data.header_text).length > 0;

    const setBody = (body: string) =>
        form.setData((data) => ({
            ...data,
            body,
            body_examples: Array.from(
                { length: variableCount(body) },
                (_, position) => data.body_examples[position] ?? '',
            ),
        }));

    // Puts the next {{n}} where the cursor is.
    const addVariable = () => {
        const field = bodyField.current;
        const next = `{{${variables + 1}}}`;
        const at = field?.selectionStart ?? form.data.body.length;
        const body =
            form.data.body.slice(0, at) + next + form.data.body.slice(at);
        setBody(body);
        requestAnimationFrame(() => {
            field?.focus();
            field?.setSelectionRange(at + next.length, at + next.length);
        });
    };

    const setButton = (position: number, changes: Partial<TemplateButton>) =>
        form.setData(
            'buttons',
            form.data.buttons.map((button, at) =>
                at === position ? { ...button, ...changes } : button,
            ),
        );

    const save = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, body_examples: examples }));
        form.submit(template ? update(template.id) : store());
    };

    return (
        <>
            <Head title={title} />

            <form
                onSubmit={save}
                className="grid items-start gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
            >
                <div className="flex max-w-3xl flex-col gap-6">
                    <h1 className="text-display font-extrabold">{title}</h1>

                    <Field
                        label={t('templates.form.name')}
                        htmlFor="template-name"
                        hint={t('templates.form.name_hint')}
                        error={errors.name}
                    >
                        <Input
                            id="template-name"
                            dir="ltr"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            autoComplete="off"
                            placeholder="order_follow_up"
                            className="h-10 text-start font-mono"
                        />
                    </Field>

                    <div className="grid gap-6 sm:grid-cols-[12rem_1fr]">
                        <Field label={t('templates.form.language')}>
                            <Choice
                                options={(['ar', 'en'] as const).map(
                                    (language) => ({
                                        value: language,
                                        label: t(
                                            `templates.language.${language}`,
                                        ),
                                    }),
                                )}
                                value={form.data.language}
                                onChange={(language) =>
                                    form.setData('language', language)
                                }
                            />
                        </Field>
                        <Field
                            label={t('templates.form.category')}
                            hint={t(
                                `templates.category_hint.${form.data.category}`,
                            )}
                        >
                            <Choice
                                options={(
                                    ['utility', 'marketing'] as const
                                ).map((category) => ({
                                    value: category,
                                    label: t(`templates.category.${category}`),
                                }))}
                                value={form.data.category}
                                onChange={(category) =>
                                    form.setData('category', category)
                                }
                            />
                        </Field>
                    </div>

                    <Field label={t('templates.form.header')}>
                        <Choice
                            options={HEADERS.map((header) => ({
                                value: header,
                                label: t(`templates.header.${header}`),
                            }))}
                            value={form.data.header_type}
                            onChange={(header) =>
                                form.setData('header_type', header)
                            }
                        />
                        {form.data.header_type === 'text' && (
                            <div className="mt-2 grid gap-3 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Input
                                        dir="auto"
                                        value={form.data.header_text}
                                        onChange={(event) =>
                                            form.setData(
                                                'header_text',
                                                event.target.value,
                                            )
                                        }
                                        maxLength={60}
                                        aria-label={t(
                                            'templates.form.header_text',
                                        )}
                                        placeholder={t(
                                            'templates.form.header_text',
                                        )}
                                        className="h-10"
                                    />
                                    <InputError message={errors.header_text} />
                                </div>
                                {headerVariable && (
                                    <div className="grid gap-1.5">
                                        <Input
                                            dir="auto"
                                            value={form.data.header_example}
                                            onChange={(event) =>
                                                form.setData(
                                                    'header_example',
                                                    event.target.value,
                                                )
                                            }
                                            aria-label={t(
                                                'templates.form.example_for',
                                                { number: '1' },
                                            )}
                                            placeholder={t(
                                                'templates.form.example_for',
                                                { number: '1' },
                                            )}
                                            className="h-10"
                                        />
                                        <InputError
                                            message={errors.header_example}
                                        />
                                    </div>
                                )}
                            </div>
                        )}
                        {form.data.header_type !== 'none' &&
                            form.data.header_type !== 'text' && (
                                <p className="mt-1 text-caption text-muted-foreground">
                                    {t('templates.form.header_media_hint')}
                                </p>
                            )}
                    </Field>

                    <Field
                        label={t('templates.form.body')}
                        htmlFor="template-body"
                        hint={t('templates.form.body_hint')}
                        error={errors.body}
                        action={
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={addVariable}
                            >
                                <Braces />
                                {t('templates.form.add_variable')}
                            </Button>
                        }
                    >
                        <Textarea
                            id="template-body"
                            ref={bodyField}
                            dir="auto"
                            rows={6}
                            maxLength={1024}
                            value={form.data.body}
                            onChange={(event) => setBody(event.target.value)}
                        />
                        <span
                            dir="ltr"
                            className="self-end text-caption text-muted-foreground tabular"
                        >
                            {form.data.body.length} / 1024
                        </span>
                    </Field>

                    {variables > 0 && (
                        <Field
                            label={t('templates.form.examples')}
                            hint={t('templates.form.examples_hint')}
                            error={errors.body_examples}
                        >
                            <div className="grid gap-3 sm:grid-cols-2">
                                {examples.map((example, position) => (
                                    <div
                                        key={position}
                                        className="grid gap-1.5"
                                    >
                                        <div className="relative">
                                            <bdi
                                                dir="ltr"
                                                className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 font-mono text-caption text-muted-foreground"
                                            >
                                                {`{{${position + 1}}}`}
                                            </bdi>
                                            <Input
                                                dir="auto"
                                                value={example}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'body_examples',
                                                        examples.map(
                                                            (value, at) =>
                                                                at === position
                                                                    ? event
                                                                          .target
                                                                          .value
                                                                    : value,
                                                        ),
                                                    )
                                                }
                                                aria-label={t(
                                                    'templates.form.example_for',
                                                    {
                                                        number: String(
                                                            position + 1,
                                                        ),
                                                    },
                                                )}
                                                className="h-10 ps-12"
                                            />
                                        </div>
                                        <InputError
                                            message={
                                                errors[
                                                    `body_examples.${position}`
                                                ]
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                        </Field>
                    )}

                    <Field
                        label={t('templates.form.footer')}
                        htmlFor="template-footer"
                        hint={t('templates.form.footer_hint')}
                        error={errors.footer}
                    >
                        <Input
                            id="template-footer"
                            dir="auto"
                            maxLength={60}
                            value={form.data.footer}
                            onChange={(event) =>
                                form.setData('footer', event.target.value)
                            }
                            className="h-10"
                        />
                    </Field>

                    <Field
                        label={t('templates.form.buttons')}
                        hint={t('templates.form.buttons_hint')}
                        error={errors.buttons}
                    >
                        <ul className="flex flex-col gap-3">
                            {form.data.buttons.map((button, position) => (
                                <li
                                    key={position}
                                    className="flex flex-col gap-3 rounded-xl border p-3"
                                >
                                    <div className="flex items-center gap-2">
                                        <span className="inline-flex items-center gap-1.5 text-body-sm font-semibold">
                                            {button.type === 'url' ? (
                                                <Link2 className="size-4" />
                                            ) : button.type === 'phone' ? (
                                                <Phone className="size-4" />
                                            ) : (
                                                <MessageSquare className="size-4" />
                                            )}
                                            {t(
                                                `templates.button_type.${button.type}`,
                                            )}
                                        </span>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            className="ms-auto size-8"
                                            onClick={() =>
                                                form.setData(
                                                    'buttons',
                                                    form.data.buttons.filter(
                                                        (_, at) =>
                                                            at !== position,
                                                    ),
                                                )
                                            }
                                            aria-label={t(
                                                'templates.form.remove_button',
                                            )}
                                        >
                                            <X />
                                        </Button>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="grid gap-1.5">
                                            <Input
                                                dir="auto"
                                                maxLength={25}
                                                value={button.text}
                                                onChange={(event) =>
                                                    setButton(position, {
                                                        text: event.target
                                                            .value,
                                                    })
                                                }
                                                placeholder={t(
                                                    'templates.form.button_text',
                                                )}
                                                aria-label={t(
                                                    'templates.form.button_text',
                                                )}
                                                className="h-10"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `buttons.${position}.text`
                                                    ]
                                                }
                                            />
                                        </div>
                                        {button.type === 'url' && (
                                            <div className="grid gap-1.5">
                                                <Input
                                                    dir="ltr"
                                                    value={button.url ?? ''}
                                                    onChange={(event) =>
                                                        setButton(position, {
                                                            url: event.target
                                                                .value,
                                                        })
                                                    }
                                                    placeholder="https://"
                                                    aria-label={t(
                                                        'templates.form.button_url',
                                                    )}
                                                    className="h-10 text-start font-mono"
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `buttons.${position}.url`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        )}
                                        {button.type === 'phone' && (
                                            <div className="grid gap-1.5">
                                                <Input
                                                    dir="ltr"
                                                    inputMode="tel"
                                                    value={button.phone ?? ''}
                                                    onChange={(event) =>
                                                        setButton(position, {
                                                            phone: event.target
                                                                .value,
                                                        })
                                                    }
                                                    placeholder="+966 5x xxx xxxx"
                                                    aria-label={t(
                                                        'templates.form.button_phone',
                                                    )}
                                                    className="h-10 text-start font-mono"
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `buttons.${position}.phone`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        )}
                                    </div>
                                    {button.type === 'url' && (
                                        <div className="grid gap-1.5">
                                            <p className="text-caption text-muted-foreground">
                                                {t(
                                                    'templates.form.button_url_hint',
                                                )}
                                            </p>
                                            {variablesIn(button.url).length >
                                                0 && (
                                                <Input
                                                    dir="ltr"
                                                    value={button.example ?? ''}
                                                    onChange={(event) =>
                                                        setButton(position, {
                                                            example:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                    placeholder={t(
                                                        'templates.form.example_for',
                                                        { number: '1' },
                                                    )}
                                                    aria-label={t(
                                                        'templates.form.example_for',
                                                        { number: '1' },
                                                    )}
                                                    className="h-10 text-start"
                                                />
                                            )}
                                            <InputError
                                                message={
                                                    errors[
                                                        `buttons.${position}.example`
                                                    ]
                                                }
                                            />
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                        {form.data.buttons.length < 10 && (
                            <div className="flex flex-wrap gap-2">
                                {(['quick_reply', 'url', 'phone'] as const).map(
                                    (type) => (
                                        <Button
                                            key={type}
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                form.setData('buttons', [
                                                    ...form.data.buttons,
                                                    { type, text: '' },
                                                ])
                                            }
                                        >
                                            <Plus />
                                            {t(`templates.button_type.${type}`)}
                                        </Button>
                                    ),
                                )}
                                {form.data.category === 'marketing' && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="secondary"
                                        onClick={() =>
                                            form.setData('buttons', [
                                                ...form.data.buttons,
                                                {
                                                    type: 'quick_reply',
                                                    text:
                                                        form.data.language ===
                                                        'ar'
                                                            ? 'إيقاف'
                                                            : 'Stop',
                                                },
                                            ])
                                        }
                                    >
                                        <Plus />
                                        {t('templates.form.add_stop')}
                                    </Button>
                                )}
                            </div>
                        )}
                    </Field>

                    <div className="flex flex-wrap gap-2 border-t pt-6">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {t('templates.form.save')}
                        </Button>
                        <Button variant="secondary" asChild>
                            <Link href={index({ query: { tab: 'templates' } })}>
                                {t('templates.form.cancel')}
                            </Link>
                        </Button>
                    </div>
                </div>

                <aside className="flex flex-col gap-3 lg:sticky lg:top-6">
                    <h2 className="text-body-sm font-semibold text-muted-foreground">
                        {t('templates.form.preview')}
                    </h2>
                    <div className="rounded-2xl bg-sunken p-4">
                        <TemplatePreview
                            headerType={form.data.header_type}
                            headerText={form.data.header_text}
                            headerValue={form.data.header_example}
                            body={form.data.body}
                            values={examples}
                            footer={form.data.footer}
                            buttons={form.data.buttons}
                        />
                    </div>
                </aside>
            </form>
        </>
    );
}

function Field({
    label,
    htmlFor,
    hint,
    error,
    action,
    children,
}: {
    label: string;
    htmlFor?: string;
    hint?: string;
    error?: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between gap-2">
                <Label htmlFor={htmlFor}>{label}</Label>
                {action}
            </div>
            {children}
            {hint && (
                <p className="text-caption text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

function Choice<T extends string>({
    options,
    value,
    onChange,
}: {
    options: { value: T; label: string }[];
    value: T;
    onChange: (value: T) => void;
}) {
    return (
        <div className="flex flex-wrap gap-1 self-start rounded-full bg-muted p-1">
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    onClick={() => onChange(option.value)}
                    aria-pressed={value === option.value}
                    className={cn(
                        'h-8 rounded-full px-3.5 text-body-sm transition-colors',
                        value === option.value
                            ? 'bg-card font-semibold shadow-raised'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}
