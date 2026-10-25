import { cn } from '@/lib/utils';

/**
 * The logo's two bars, small, wherever a conversation passes from the bot to
 * a person. It is the only place besides the logo that draws them, so it
 * keeps that one meaning.
 */
export function HandoverMark({
    className,
    animated = false,
    onRose = false,
}: {
    className?: string;
    animated?: boolean;
    /** Inside something already rose (the handover button), draw both bars in the text colour. */
    onRose?: boolean;
}) {
    return (
        <svg
            viewBox="0 0 48 48"
            className={cn('size-4 shrink-0', className)}
            aria-hidden
        >
            <g
                fill="none"
                strokeWidth="9"
                strokeLinecap="round"
                transform="rotate(-35 24 24)"
            >
                <path stroke="currentColor" d="M6 19 H29" opacity=".55" />
                <path
                    stroke={onRose ? 'currentColor' : 'var(--handover)'}
                    d="M19 29 H42"
                    className={cn(animated && 'animate-handover')}
                />
            </g>
        </svg>
    );
}
