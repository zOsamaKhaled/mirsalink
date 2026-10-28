import { Deferred, useForm, usePage } from '@inertiajs/react';
import { BriefcaseBusiness, CloudOff, UserPlus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/hooks/use-translations';
import { formatDate } from '@/lib/time';
import { cn } from '@/lib/utils';
import { store as storeLead } from '@/routes/inbox/rizq-lead';
import type {
    RizqCard as RizqCardData,
    SelectedConversation,
} from '@/types/inbox';

const VERIFICATION_TONE: Record<string, string> = {
    verified: 'bg-success-soft text-success',
    pending: 'bg-warning-soft text-warning',
    rejected: 'bg-danger-soft text-danger',
    not_submitted: 'bg-muted text-muted-foreground',
};

/**
 * Who the customer is on Rizq, asked of Rizq after the page shows: their
 * account, package, verification and who has them in sales, with a button
 * that hands them to sales as a lead. Hidden when Rizq is not linked.
 */
export function RizqCard({
    conversation,
}: {
    conversation: SelectedConversation;
}) {
    const { t } = useTranslations();

    return (
        <Deferred
            data="rizqCard"
            fallback={
                <Section>
                    <p className="sr-only">{t('inbox.rizq.loading')}</p>
                    <Skeleton className="h-4 w-2/3" />
                    <Skeleton className="h-4 w-1/2" />
                    <Skeleton className="h-4 w-3/5" />
                </Section>
            }
        >
            <Loaded conversation={conversation} />
        </Deferred>
    );
}

function Loaded({ conversation }: { conversation: SelectedConversation }) {
    const { t, locale } = useTranslations();
    const card = usePage<{ rizqCard: RizqCardData | null }>().props.rizqCard;

    if (card === null || card.state === 'not_linked') {
        return null;
    }

    if (card.state === 'unavailable') {
        return (
            <Section>
                <p className="flex items-center gap-2 text-body-sm text-muted-foreground">
                    <CloudOff className="size-4 shrink-0" />
                    {t('inbox.rizq.unavailable')}
                </p>
            </Section>
        );
    }

    const { account, sales } = card;

    return (
        <Section>
            {account === null ? (
                <p className="text-body-sm text-muted-foreground">
                    {t('inbox.rizq.no_account')}
                </p>
            ) : (
                <dl className="flex flex-col gap-2.5 text-body-sm">
                    <div className="flex flex-col gap-0.5">
                        <dt className="text-caption text-muted-foreground">
                            {t(`inbox.rizq.types.${account.type}`)}
                        </dt>
                        <dd className="font-semibold">
                            <bdi>{account.name}</bdi>
                        </dd>
                    </div>
                    <Row label={t('inbox.rizq.plan')}>
                        {account.plan === null ? (
                            <span className="text-muted-foreground">
                                {t('inbox.rizq.no_plan')}
                            </span>
                        ) : (
                            <span className="flex flex-col items-end gap-0.5 text-end">
                                <span className="font-semibold">
                                    <bdi>{account.plan.name}</bdi>
                                    {account.plan.is_trial && (
                                        <span className="ms-1.5 rounded-full bg-muted px-1.5 py-0.5 text-caption font-normal">
                                            {t('inbox.rizq.trial')}
                                        </span>
                                    )}
                                </span>
                                {account.plan.ends_at && (
                                    <span className="text-caption text-muted-foreground tabular">
                                        {t('inbox.rizq.ends', {
                                            date: formatDate(
                                                account.plan.ends_at,
                                                locale,
                                            ),
                                        })}
                                    </span>
                                )}
                            </span>
                        )}
                    </Row>
                    {account.verification && (
                        <Row label={t('inbox.rizq.verification')}>
                            <span
                                className={cn(
                                    'rounded-full px-2 py-0.5 text-caption font-semibold',
                                    VERIFICATION_TONE[account.verification],
                                )}
                            >
                                {t(
                                    `inbox.rizq.verifications.${account.verification}`,
                                )}
                            </span>
                        </Row>
                    )}
                </dl>
            )}

            <div className="flex flex-col gap-2 border-t pt-3 text-body-sm">
                <dl>
                    <Row label={t('inbox.rizq.sales')}>
                        {sales === null ? (
                            <span className="text-muted-foreground">
                                {t('inbox.rizq.sales_none')}
                            </span>
                        ) : (
                            <span className="flex flex-col items-end gap-0.5 text-end">
                                <span className="font-semibold">
                                    {t(`inbox.rizq.statuses.${sales.status}`)}
                                </span>
                                <span className="text-caption text-muted-foreground">
                                    {sales.owner
                                        ? t('inbox.rizq.owner', {
                                              name: sales.owner,
                                          })
                                        : t('inbox.rizq.pool')}
                                </span>
                            </span>
                        )}
                    </Row>
                </dl>
                {sales === null && (
                    <LeadButton
                        conversation={conversation}
                        defaultName={
                            account?.name ?? conversation.contact.name ?? ''
                        }
                        defaultType={
                            account?.type === 'individual_employer'
                                ? 'individual'
                                : 'company'
                        }
                    />
                )}
            </div>
        </Section>
    );
}

function LeadButton({
    conversation,
    defaultName,
    defaultType,
}: {
    conversation: SelectedConversation;
    defaultName: string;
    defaultType: 'company' | 'individual';
}) {
    const { t } = useTranslations();
    const [open, setOpen] = useState(false);
    const form = useForm({ type: defaultType, name: defaultName, note: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(storeLead.url(conversation.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setOpen(false);
                form.reset('note');
            },
        });
    };

    return (
        <>
            <Button
                variant="outline"
                size="sm"
                className="w-full"
                onClick={() => setOpen(true)}
            >
                <UserPlus />
                {t('inbox.rizq.lead.open')}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogTitle>{t('inbox.rizq.lead.title')}</DialogTitle>
                    <DialogDescription>
                        {t('inbox.rizq.lead.description')}
                    </DialogDescription>

                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="grid gap-2">
                            <Label>{t('inbox.rizq.lead.type')}</Label>
                            <div className="flex flex-wrap gap-1 self-start rounded-full bg-muted p-1">
                                {(['company', 'individual'] as const).map(
                                    (type) => (
                                        <button
                                            key={type}
                                            type="button"
                                            onClick={() =>
                                                form.setData('type', type)
                                            }
                                            aria-pressed={
                                                form.data.type === type
                                            }
                                            className={cn(
                                                'h-8 rounded-full px-3.5 text-body-sm transition-colors',
                                                form.data.type === type
                                                    ? 'bg-card font-semibold shadow-raised'
                                                    : 'text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            {t(`inbox.rizq.lead.types.${type}`)}
                                        </button>
                                    ),
                                )}
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="lead-name">
                                {t('inbox.rizq.lead.name')}
                            </Label>
                            <Input
                                id="lead-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                maxLength={255}
                                required
                            />
                            <p className="text-caption text-muted-foreground">
                                {t('inbox.rizq.lead.name_hint')}
                            </p>
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="lead-note">
                                {t('inbox.rizq.lead.note')}
                            </Label>
                            <Textarea
                                id="lead-note"
                                value={form.data.note}
                                onChange={(event) =>
                                    form.setData('note', event.target.value)
                                }
                                placeholder={t(
                                    'inbox.rizq.lead.note_placeholder',
                                )}
                                maxLength={2000}
                                rows={3}
                            />
                            <InputError message={form.errors.note} />
                        </div>

                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                {t('inbox.rizq.lead.cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    form.data.name.trim() === ''
                                }
                            >
                                {form.processing && <Spinner />}
                                {t('inbox.rizq.lead.submit')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Section({ children }: { children: ReactNode }) {
    const { t } = useTranslations();

    return (
        <section className="flex flex-col gap-3 rounded-xl border bg-background p-3">
            <h3 className="flex items-center gap-1.5 text-caption font-semibold text-muted-foreground">
                <BriefcaseBusiness className="size-3.5" />
                {t('inbox.rizq.title')}
            </h3>
            {children}
        </section>
    );
}

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-3">
            <dt className="shrink-0 text-muted-foreground">{label}</dt>
            <dd className="min-w-0">{children}</dd>
        </div>
    );
}
