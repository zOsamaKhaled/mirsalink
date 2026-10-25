import { usePage } from '@inertiajs/react';
import { createContext, useContext } from 'react';

export type Direction = 'rtl' | 'ltr';

const RTL_LOCALES = ['ar'];

/** Lets a page show a direction other than its locale's, as the design system page does. */
export const DirectionContext = createContext<Direction | null>(null);

export function directionOf(locale: string): Direction {
    return RTL_LOCALES.includes(locale) ? 'rtl' : 'ltr';
}

export function useDirection(): Direction {
    const override = useContext(DirectionContext);
    const { locale } = usePage().props;

    return override ?? directionOf(locale);
}
