import {
    ArrowLeft,
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    type LucideProps,
} from 'lucide-react';

import { useDirection } from '@/hooks/use-direction';

/*
 * Arrows that mean a way through the page point the way the page reads:
 * forward is → in English and ← in Arabic, back is the other way.
 * Pick the arrow by its meaning with these, never a fixed side, and never
 * flip a glyph with CSS.
 */

export function ForwardArrow(props: LucideProps) {
    return useDirection() === 'rtl' ? (
        <ArrowLeft {...props} />
    ) : (
        <ArrowRight {...props} />
    );
}

export function BackArrow(props: LucideProps) {
    return useDirection() === 'rtl' ? (
        <ArrowRight {...props} />
    ) : (
        <ArrowLeft {...props} />
    );
}

export function ForwardChevron(props: LucideProps) {
    return useDirection() === 'rtl' ? (
        <ChevronLeft {...props} />
    ) : (
        <ChevronRight {...props} />
    );
}

export function BackChevron(props: LucideProps) {
    return useDirection() === 'rtl' ? (
        <ChevronRight {...props} />
    ) : (
        <ChevronLeft {...props} />
    );
}
