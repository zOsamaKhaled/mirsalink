import {
    FileText,
    Image,
    Link2,
    MessageSquare,
    Phone,
    Video,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Fragment } from 'react';

import { cn } from '@/lib/utils';
import type { TemplateButton, TemplateHeaderType } from '@/types/templates';

/**
 * A template drawn the way the customer's WhatsApp shows it: header, text,
 * footer, and its buttons as rows beneath. Variables show their value when
 * one is given, otherwise their example, otherwise {{n}} as a soft chip.
 */
export function TemplatePreview({
    headerType,
    headerText,
    headerValue,
    body,
    values = [],
    footer,
    buttons = [],
    className,
}: {
    headerType: TemplateHeaderType;
    headerText?: string | null;
    headerValue?: string | null;
    body: string;
    values?: (string | null | undefined)[];
    footer?: string | null;
    buttons?: TemplateButton[];
    className?: string;
}) {
    const media = {
        image: <Image className="size-8" />,
        document: <FileText className="size-8" />,
        video: <Video className="size-8" />,
    } as const;

    return (
        <div
            className={cn(
                'w-full max-w-sm overflow-hidden rounded-2xl rounded-se-md bg-card text-card-foreground shadow-raised',
                className,
            )}
        >
            <div className="flex flex-col gap-1.5 px-3.5 py-2.5">
                {headerType !== 'none' && headerType !== 'text' && (
                    <div className="grid h-28 place-items-center rounded-lg bg-muted text-muted-foreground">
                        {media[headerType]}
                    </div>
                )}
                {headerType === 'text' && headerText && (
                    <p dir="auto" className="font-bold">
                        <Filled text={headerText} values={[headerValue]} />
                    </p>
                )}
                <p
                    dir="auto"
                    className="text-body break-words whitespace-pre-wrap"
                >
                    {body.trim() === '' ? (
                        <span className="text-muted-foreground">…</span>
                    ) : (
                        <Filled text={body} values={values} />
                    )}
                </p>
                {footer && (
                    <p
                        dir="auto"
                        className="text-caption text-muted-foreground"
                    >
                        {footer}
                    </p>
                )}
            </div>
            {buttons.length > 0 && (
                <ul className="divide-y border-t">
                    {buttons.map((button, position) => (
                        <li
                            key={position}
                            className="flex items-center justify-center gap-1.5 px-3 py-2 text-body-sm font-semibold text-primary dark:text-tag-blue-foreground"
                        >
                            {button.type === 'url' ? (
                                <Link2 className="size-4" />
                            ) : button.type === 'phone' ? (
                                <Phone className="size-4" />
                            ) : (
                                <MessageSquare className="size-4" />
                            )}
                            <span dir="auto">{button.text || '…'}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Filled({
    text,
    values,
}: {
    text: string;
    values: (string | null | undefined)[];
}) {
    const parts: ReactNode[] = [];
    let last = 0;

    for (const match of text.matchAll(/\{\{\s*(\d+)\s*\}\}/g)) {
        const at = match.index ?? 0;
        parts.push(text.slice(last, at));

        const value = values[Number(match[1]) - 1];
        parts.push(
            value ? (
                <span className="rounded bg-tag-blue px-0.5 text-tag-blue-foreground">
                    {value}
                </span>
            ) : (
                <bdi
                    dir="ltr"
                    className="rounded bg-muted px-1 font-mono text-caption text-muted-foreground"
                >
                    {match[0]}
                </bdi>
            ),
        );
        last = at + match[0].length;
    }

    parts.push(text.slice(last));

    return (
        <>
            {parts.map((part, position) => (
                <Fragment key={position}>{part}</Fragment>
            ))}
        </>
    );
}
