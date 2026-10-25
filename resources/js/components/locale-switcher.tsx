import { Languages } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

/**
 * Switches to the other language with a full page load, because the page's
 * direction is set on <html> by the server. `?lang=` makes the choice and
 * the SetLocale middleware keeps it in a cookie.
 */
export function LocaleSwitcher({ className }: { className?: string }) {
    const { t, locale, supportedLocales } = useTranslations();
    const other = supportedLocales.find((code) => code !== locale) ?? locale;

    const switchTo = () => {
        const url = new URL(window.location.href);
        url.searchParams.set('lang', other);
        window.location.assign(url.toString());
    };

    return (
        <Button
            variant="ghost"
            size="sm"
            onClick={switchTo}
            lang={other}
            aria-label={`${t('locale.label')}: ${t(`locale.${other}`)}`}
            className={cn('gap-1.5 rounded-full', className)}
        >
            <Languages />
            {t(`locale.${other}`)}
        </Button>
    );
}
