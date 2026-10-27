import { useTranslations } from '@/hooks/use-translations';
import { formatDuration } from '@/lib/time';

/** formatDuration with the unit letters of the member's language; null reads as a dash. */
export function useDuration(): (seconds: number | null) => string {
    const { t } = useTranslations();
    const units = {
        s: t('common.units.s'),
        m: t('common.units.m'),
        h: t('common.units.h'),
        d: t('common.units.d'),
    };

    return (seconds) =>
        seconds === null ? '—' : formatDuration(seconds, units);
}
