import { Link, router } from '@inertiajs/react';
import { FileText, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

import { BackArrow } from '@/components/direction-icons';
import { TemplatePreview } from '@/components/inbox/template-preview';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { store as sendTemplate } from '@/routes/inbox/template';
import { index as templatesPage } from '@/routes/templates';
import type { SelectedConversation } from '@/types/inbox';
import type { WhatsAppTemplate } from '@/types/templates';

/**
 * Choosing an approved template and filling its variables, with the
 * message shown as the customer will read it before it goes.
 */
export function TemplateDialog({
    open,
    onOpenChange,
    conversation,
    templates,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    conversation: SelectedConversation;
    templates: WhatsAppTemplate[];
}) {
    const { t } = useTranslations();
    const [chosen, setChosen] = useState<WhatsAppTemplate | null>(null);
    const [search, setSearch] = useState('');
    const [body, setBody] = useState<string[]>([]);
    const [header, setHeader] = useState('');
    const [button, setButton] = useState('');
    const [sending, setSending] = useState(false);
    const optedOut = conversation.contact.opted_out;

    useEffect(() => {
        if (!open) {
            setChosen(null);
            setSearch('');
        }
    }, [open]);

    const choose = (template: WhatsAppTemplate) => {
        setChosen(template);
        // The customer's name is the usual first variable, so it starts there.
        setBody(
            Array.from({ length: template.variables.body }, (_, position) =>
                position === 0 ? (conversation.contact.name ?? '') : '',
            ),
        );
        setHeader('');
        setButton('');
    };

    const ready =
        chosen !== null &&
        body.every((value) => value.trim() !== '') &&
        (!chosen.variables.header || header.trim() !== '') &&
        (!chosen.variables.button || button.trim() !== '');

    const send = () => {
        if (!chosen || !ready) {
            return;
        }

        router.post(
            sendTemplate.url(conversation.id),
            { template_id: chosen.id, body, header, button },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    const shown = templates.filter((template) =>
        template.name.toLowerCase().includes(search.trim().toLowerCase()),
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>{t('inbox.template_dialog.title')}</DialogTitle>
                <DialogDescription>
                    {t('inbox.template_dialog.description')}
                </DialogDescription>

                {chosen === null ? (
                    templates.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed p-8 text-center">
                            <FileText className="size-8 text-muted-foreground" />
                            <p className="text-body-sm text-muted-foreground">
                                {t('inbox.template_dialog.empty')}
                            </p>
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={templatesPage({
                                        query: { tab: 'templates' },
                                    })}
                                >
                                    {t('inbox.template_dialog.manage')}
                                </Link>
                            </Button>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {optedOut && (
                                <p className="rounded-xl bg-muted px-3 py-2 text-body-sm text-muted-foreground">
                                    {t('inbox.template_dialog.opted_out')}
                                </p>
                            )}
                            <div className="relative">
                                <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder={t(
                                        'inbox.template_dialog.search',
                                    )}
                                    aria-label={t(
                                        'inbox.template_dialog.search',
                                    )}
                                    className="h-10 ps-9"
                                />
                            </div>
                            <ul className="flex flex-col gap-2">
                                {shown.map((template) => {
                                    const blocked =
                                        optedOut &&
                                        template.category === 'marketing';

                                    return (
                                        <li key={template.id}>
                                            <button
                                                type="button"
                                                disabled={blocked}
                                                onClick={() => choose(template)}
                                                className={cn(
                                                    'flex w-full flex-col gap-1.5 rounded-xl border p-3 text-start transition-colors',
                                                    'hover:bg-accent/60 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50',
                                                )}
                                            >
                                                <span className="flex items-center gap-2">
                                                    <bdi
                                                        dir="ltr"
                                                        className="truncate font-mono text-body-sm font-semibold"
                                                    >
                                                        {template.name}
                                                    </bdi>
                                                    <span className="ms-auto shrink-0 rounded-full bg-secondary px-2 py-0.5 text-caption text-secondary-foreground">
                                                        {t(
                                                            `templates.category.${template.category}`,
                                                        )}
                                                    </span>
                                                </span>
                                                <span
                                                    dir="auto"
                                                    className="line-clamp-2 text-body-sm text-muted-foreground"
                                                >
                                                    {template.body}
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )
                ) : (
                    <div className="grid gap-5 sm:grid-cols-[minmax(0,1fr)_16rem]">
                        <div className="flex flex-col gap-4">
                            <Button
                                variant="ghost"
                                size="sm"
                                className="self-start"
                                onClick={() => setChosen(null)}
                            >
                                <BackArrow />
                                {t('inbox.template_dialog.back')}
                            </Button>
                            <bdi
                                dir="ltr"
                                className="font-mono text-body-sm font-semibold"
                            >
                                {chosen.name}
                            </bdi>

                            {chosen.variables.header && (
                                <ValueField
                                    id="template-header"
                                    label={t(
                                        'inbox.template_dialog.header_value',
                                    )}
                                    value={header}
                                    placeholder={chosen.header_example ?? ''}
                                    onChange={setHeader}
                                />
                            )}
                            {body.length > 0 && (
                                <div className="flex flex-col gap-3">
                                    <span className="text-body-sm font-semibold">
                                        {t('inbox.template_dialog.variables')}
                                    </span>
                                    {body.map((value, position) => (
                                        <ValueField
                                            key={position}
                                            id={`template-value-${position}`}
                                            label={`{{${position + 1}}}`}
                                            value={value}
                                            placeholder={
                                                chosen.body_examples[
                                                    position
                                                ] ?? ''
                                            }
                                            onChange={(next) =>
                                                setBody(
                                                    body.map((old, at) =>
                                                        at === position
                                                            ? next
                                                            : old,
                                                    ),
                                                )
                                            }
                                        />
                                    ))}
                                </div>
                            )}
                            {chosen.variables.button && (
                                <ValueField
                                    id="template-button"
                                    label={t(
                                        'inbox.template_dialog.button_value',
                                    )}
                                    value={button}
                                    placeholder={
                                        chosen.buttons.find(
                                            (item) => item.example,
                                        )?.example ?? ''
                                    }
                                    onChange={setButton}
                                    ltr
                                />
                            )}

                            <Button
                                className="self-start"
                                disabled={!ready || sending}
                                onClick={send}
                            >
                                {sending && <Spinner />}
                                {t('inbox.template_dialog.send')}
                            </Button>
                        </div>
                        <div className="self-start rounded-2xl bg-sunken p-3">
                            <TemplatePreview
                                headerType={chosen.header_type}
                                headerText={chosen.header_text}
                                headerValue={header}
                                body={chosen.body}
                                values={body}
                                footer={chosen.footer}
                                buttons={chosen.buttons}
                            />
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

function ValueField({
    id,
    label,
    value,
    placeholder,
    onChange,
    ltr = false,
}: {
    id: string;
    label: string;
    value: string;
    placeholder: string;
    onChange: (value: string) => void;
    ltr?: boolean;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>
                <bdi dir="auto">{label}</bdi>
            </Label>
            <Input
                id={id}
                dir={ltr ? 'ltr' : 'auto'}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                className="h-10 text-start"
            />
        </div>
    );
}
