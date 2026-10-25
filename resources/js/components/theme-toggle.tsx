import { Moon, Sun } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslations } from '@/hooks/use-translations';

/** One press between light and dark; "follow the device" lives in Settings. */
export function ThemeToggle() {
    const { t } = useTranslations();
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const next = resolvedAppearance === 'dark' ? 'light' : 'dark';

    return (
        <Button
            variant="ghost"
            size="icon-sm"
            className="rounded-full"
            onClick={() => updateAppearance(next)}
            aria-label={`${t('appearance.label')}: ${t(`appearance.${next}`)}`}
            title={t(`appearance.${next}`)}
        >
            {next === 'dark' ? <Moon /> : <Sun />}
        </Button>
    );
}
