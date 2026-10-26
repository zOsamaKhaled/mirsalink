import { router, useForm } from '@inertiajs/react';
import {
    LockKeyhole,
    MessageSquareText,
    NotebookPen,
    Paperclip,
    RotateCcw,
    Send,
    X,
} from 'lucide-react';
import type { FormEvent, KeyboardEvent } from 'react';
import { useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/hooks/use-translations';
import { formatFileSize } from '@/lib/time';
import { cn } from '@/lib/utils';
import { reopen } from '@/routes/inbox';
import { store } from '@/routes/inbox/messages';
import type { SelectedConversation } from '@/types/inbox';

type Mode = 'reply' | 'note';

/**
 * Where the member writes: a reply to the customer, or a note for the
 * team on paper yellow. After the 24-hour window a reply is closed off and
 * says why; a note is always open.
 */
export function Composer({
    conversation,
}: {
    conversation: SelectedConversation;
}) {
    const { t } = useTranslations();
    const [mode, setMode] = useState<Mode>('reply');
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm<{ body: string; note: boolean; file: File | null }>({
        body: '',
        note: false,
        file: null,
    });

    if (conversation.closed) {
        return (
            <div className="flex flex-wrap items-center gap-3 border-t bg-card px-4 py-3">
                <p className="min-w-0 flex-1 text-body-sm text-muted-foreground">
                    {t('inbox.thread.closed_notice')}
                </p>
                <Button
                    variant="outline"
                    onClick={() =>
                        router.post(
                            reopen.url(conversation.id),
                            {},
                            { preserveScroll: true, preserveState: true },
                        )
                    }
                >
                    <RotateCcw />
                    {t('inbox.thread.reopen')}
                </Button>
            </div>
        );
    }

    const note = mode === 'note';
    const blocked = !note && !conversation.window_open;
    const empty = form.data.body.trim() === '' && form.data.file === null;

    const switchTo = (next: Mode) => {
        setMode(next);
        form.setData((data) => ({
            ...data,
            note: next === 'note',
            file: next === 'note' ? null : data.file,
        }));
        form.clearErrors();
    };

    const submit = (event?: FormEvent) => {
        event?.preventDefault();

        if (blocked || empty || form.processing) {
            return;
        }

        form.post(store.url(conversation.id), {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                form.reset('body', 'file');

                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (
            event.key === 'Enter' &&
            !event.shiftKey &&
            !event.nativeEvent.isComposing
        ) {
            event.preventDefault();
            submit();
        }
    };

    return (
        <form
            onSubmit={submit}
            className={cn(
                'flex flex-col gap-2 border-t px-3 py-3 transition-colors',
                note ? 'bg-bubble-note/60' : 'bg-card',
            )}
        >
            <div className="flex items-center gap-1">
                <ModeButton
                    active={!note}
                    onClick={() => switchTo('reply')}
                    icon={<MessageSquareText />}
                >
                    {t('inbox.composer.reply')}
                </ModeButton>
                <ModeButton
                    active={note}
                    onClick={() => switchTo('note')}
                    icon={<NotebookPen />}
                >
                    {t('inbox.composer.note')}
                </ModeButton>
                <span className="ms-auto hidden text-caption text-muted-foreground 2xl:inline">
                    {t('inbox.composer.hint')}
                </span>
            </div>

            {blocked ? (
                <p className="flex items-start gap-2 rounded-xl bg-danger-soft px-3 py-2.5 text-body-sm text-danger">
                    <LockKeyhole className="mt-0.5 size-4 shrink-0" />
                    {t('inbox.composer.window_closed')}
                </p>
            ) : (
                <>
                    {form.data.file && (
                        <span className="inline-flex max-w-full items-center gap-2 self-start rounded-full bg-muted px-3 py-1 text-body-sm">
                            <Paperclip className="size-3.5 shrink-0" />
                            <span dir="auto" className="truncate">
                                {form.data.file.name}
                            </span>
                            <span
                                dir="ltr"
                                className="shrink-0 text-caption text-muted-foreground tabular"
                            >
                                {formatFileSize(form.data.file.size)}
                            </span>
                            <button
                                type="button"
                                onClick={() => {
                                    form.setData('file', null);

                                    if (fileInput.current) {
                                        fileInput.current.value = '';
                                    }
                                }}
                                className="rounded-full p-0.5 hover:bg-background"
                                aria-label={t('inbox.composer.remove_file')}
                            >
                                <X className="size-3.5" />
                            </button>
                        </span>
                    )}

                    <div className="flex items-end gap-2">
                        {!note && (
                            <>
                                <input
                                    ref={fileInput}
                                    type="file"
                                    className="hidden"
                                    onChange={(event) =>
                                        form.setData(
                                            'file',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-10 shrink-0 rounded-full"
                                    onClick={() => fileInput.current?.click()}
                                    aria-label={t('inbox.composer.attach')}
                                    title={t('inbox.composer.attach')}
                                >
                                    <Paperclip />
                                </Button>
                            </>
                        )}
                        <Textarea
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            onKeyDown={onKeyDown}
                            rows={1}
                            dir="auto"
                            placeholder={
                                note
                                    ? t('inbox.composer.note_placeholder')
                                    : t('inbox.composer.placeholder')
                            }
                            aria-label={
                                note
                                    ? t('inbox.composer.note_placeholder')
                                    : t('inbox.composer.placeholder')
                            }
                            className={cn(
                                'field-sizing-content max-h-40 min-h-10 flex-1 resize-none rounded-2xl px-4 py-2.5',
                                note
                                    ? 'border-bubble-note-border bg-bubble-note text-bubble-note-foreground'
                                    : 'bg-muted/60',
                            )}
                        />
                        <Button
                            type="submit"
                            className="h-10 shrink-0 rounded-full px-5"
                            disabled={empty || form.processing}
                        >
                            {form.processing ? (
                                <Spinner />
                            ) : note ? (
                                <NotebookPen />
                            ) : (
                                <Send className="rtl:-scale-x-100" />
                            )}
                            <span className="hidden sm:inline">
                                {note
                                    ? t('inbox.composer.add_note')
                                    : t('inbox.composer.send')}
                            </span>
                        </Button>
                    </div>
                    <InputError
                        message={form.errors.body ?? form.errors.file}
                    />
                </>
            )}
        </form>
    );
}

function ModeButton({
    active,
    onClick,
    icon,
    children,
}: {
    active: boolean;
    onClick: () => void;
    icon: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'inline-flex h-8 items-center gap-1.5 rounded-full px-3 text-body-sm transition-colors [&_svg]:size-4',
                active
                    ? 'bg-secondary font-semibold text-secondary-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-foreground',
            )}
        >
            {icon}
            {children}
        </button>
    );
}
