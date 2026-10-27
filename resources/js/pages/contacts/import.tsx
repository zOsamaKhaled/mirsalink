import { Head, Link, setLayoutProps, useForm, usePage } from '@inertiajs/react';
import { CircleAlert, FileSpreadsheet } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

import { BackArrow } from '@/components/direction-icons';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { index as contacts } from '@/routes/contacts';
import { create, store } from '@/routes/contacts/import';

type ImportResult = {
    created: number;
    updated: number;
    skipped: { line: number; reason: 'no_phone' | 'bad_phone' }[];
    skipped_count: number;
    tags_created: number;
    owners: number;
    unknown_owners: string[];
    missing_phone_column: boolean;
};

const COLUMN_KEYS = ['phone', 'name', 'email', 'company', 'tags'] as const;

export default function ImportContacts({ maxRows }: { maxRows: number }) {
    const { t } = useTranslations();
    const result = (usePage().flash as { import?: ImportResult }).import;
    const form = useForm<{ file: File | null }>({ file: null });
    // Bumped after an import so the file field empties with the form.
    const [fieldKey, setFieldKey] = useState(0);

    setLayoutProps({
        breadcrumbs: [
            { title: t('contacts.title'), href: contacts() },
            { title: t('contacts.import.title'), href: create() },
        ],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(store(), {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                setFieldKey((key) => key + 1);
            },
        });
    };

    const how = [0, 1, 2, 3].map((index) => t(`contacts.import.how.${index}`));

    return (
        <>
            <Head title={t('contacts.import.title')} />

            <div className="flex max-w-3xl flex-col gap-6 px-4 py-8 sm:px-6">
                <Link
                    href={contacts()}
                    className="inline-flex w-fit items-center gap-1.5 text-body-sm text-muted-foreground hover:text-foreground"
                >
                    <BackArrow className="size-4" />
                    {t('contacts.import.back')}
                </Link>

                <header className="flex flex-col gap-1.5">
                    <h1 className="text-display font-extrabold">
                        {t('contacts.import.title')}
                    </h1>
                    <p className="text-body-sm text-muted-foreground">
                        {t('contacts.import.description')}
                    </p>
                </header>

                {result && <ResultPanel result={result} />}

                <form
                    onSubmit={submit}
                    className="flex flex-col gap-4 rounded-2xl border bg-card p-5 sm:p-6"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="file">
                            {t('contacts.import.file')}
                        </Label>
                        <input
                            key={fieldKey}
                            id="file"
                            type="file"
                            accept=".csv,text/csv"
                            onChange={(event) =>
                                form.setData(
                                    'file',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                            className="block w-full rounded-md border border-dashed border-input bg-sunken p-4 text-body-sm file:me-3 file:rounded-full file:border-0 file:bg-primary file:px-4 file:py-1.5 file:text-body-sm file:font-bold file:text-primary-foreground"
                            aria-describedby="file-hint"
                        />
                        <p
                            id="file-hint"
                            className="text-caption text-muted-foreground"
                        >
                            {t('contacts.import.file_hint', {
                                max: maxRows.toLocaleString('en'),
                            })}
                        </p>
                        <InputError message={form.errors.file} />
                    </div>
                    <div>
                        <Button
                            type="submit"
                            disabled={!form.data.file || form.processing}
                        >
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <FileSpreadsheet />
                            )}
                            {t('contacts.import.submit')}
                        </Button>
                    </div>
                </form>

                <section className="grid gap-5 rounded-2xl border bg-card p-5 sm:grid-cols-2 sm:p-6">
                    <div className="flex flex-col gap-3">
                        <h2 className="text-title font-bold">
                            {t('contacts.import.how_title')}
                        </h2>
                        <ul className="flex flex-col gap-2 text-body-sm text-muted-foreground">
                            {how.map((line) => (
                                <li key={line} className="flex gap-2">
                                    <span
                                        className="mt-2 size-1.5 shrink-0 rounded-full bg-primary"
                                        aria-hidden
                                    />
                                    {line}
                                </li>
                            ))}
                        </ul>
                    </div>
                    <div className="flex flex-col gap-3">
                        <h2 className="text-title font-bold">
                            {t('contacts.import.columns_title')}
                        </h2>
                        <ul className="flex flex-col gap-2 text-body-sm text-muted-foreground">
                            {COLUMN_KEYS.map((key) => (
                                <li key={key}>
                                    {t(`contacts.import.columns.${key}`)}
                                </li>
                            ))}
                        </ul>
                    </div>
                </section>
            </div>
        </>
    );
}

function ResultPanel({ result }: { result: ImportResult }) {
    const { t } = useTranslations();

    if (result.missing_phone_column) {
        return (
            <p className="flex items-start gap-2 rounded-xl bg-danger-soft p-4 text-body-sm text-danger">
                <CircleAlert className="mt-0.5 size-4 shrink-0" />
                {t('contacts.import.missing_phone_column')}
            </p>
        );
    }

    const figures = [
        {
            label: t('contacts.import.created'),
            value: result.created,
            tone: 'text-success',
        },
        {
            label: t('contacts.import.updated'),
            value: result.updated,
            tone: 'text-info',
        },
        {
            label: t('contacts.import.skipped'),
            value: result.skipped_count,
            tone:
                result.skipped_count > 0
                    ? 'text-warning'
                    : 'text-muted-foreground',
        },
        {
            label: t('contacts.import.tags_created'),
            value: result.tags_created,
            tone: 'text-muted-foreground',
        },
        // Only when the file named each customer's member (Respond.io's assignee).
        ...(result.owners > 0 || result.unknown_owners.length > 0
            ? [
                  {
                      label: t('contacts.import.owners'),
                      value: result.owners,
                      tone: 'text-success',
                  },
              ]
            : []),
    ];

    return (
        <section
            className="flex flex-col gap-4 rounded-2xl border bg-card p-5 sm:p-6"
            aria-live="polite"
        >
            <h2 className="text-title font-bold">
                {t('contacts.import.result_title')}
            </h2>
            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {figures.map((figure) => (
                    <div
                        key={figure.label}
                        className="flex flex-col gap-1 rounded-xl bg-sunken p-3"
                    >
                        <dt className="text-caption text-muted-foreground">
                            {figure.label}
                        </dt>
                        <dd
                            className={`text-heading font-extrabold tabular ${figure.tone}`}
                        >
                            {figure.value.toLocaleString('en')}
                        </dd>
                    </div>
                ))}
            </dl>

            {result.unknown_owners.length > 0 && (
                <p className="flex items-start gap-2 rounded-xl bg-warning-soft p-3 text-body-sm text-warning">
                    <CircleAlert className="mt-0.5 size-4 shrink-0" />
                    {t('contacts.import.unknown_owners', {
                        names: result.unknown_owners.join('، '),
                    })}
                </p>
            )}

            {result.skipped.length > 0 && (
                <div className="flex flex-col gap-2">
                    <h3 className="text-body-sm font-bold">
                        {t('contacts.import.skipped_title')}
                    </h3>
                    <ul className="grid gap-1 text-body-sm text-muted-foreground sm:grid-cols-2">
                        {result.skipped.map((row) => (
                            <li key={row.line}>
                                <span className="tabular">
                                    {t('contacts.import.line', {
                                        line: String(row.line),
                                    })}
                                </span>
                                {': '}
                                {t(`contacts.import.reasons.${row.reason}`)}
                            </li>
                        ))}
                    </ul>
                    {result.skipped_count > result.skipped.length && (
                        <p className="text-caption text-muted-foreground">
                            {t('contacts.import.skipped_more', {
                                count: String(
                                    result.skipped_count -
                                        result.skipped.length,
                                ),
                            })}
                        </p>
                    )}
                </div>
            )}
        </section>
    );
}
