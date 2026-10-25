import { usePage } from '@inertiajs/react';
import { createContext, useContext } from 'react';

export type Direction = 'rtl' | 'ltr';

/** Lets a page show a direction other than its locale's, as the design system page does. */
export const DirectionContext = createContext<Direction | null>(null);

export function useDirection(): Direction {
    const override = useContext(DirectionContext);
    const { dir } = usePage().props;

    return override ?? dir;
}
