import { usePage } from '@inertiajs/react';

import type { TranslationNode } from '@/types/translations';

function resolve(
    translations: Record<string, TranslationNode>,
    key: string,
): TranslationNode | undefined {
    return key
        .split('.')
        .reduce<TranslationNode | undefined>(
            (node, segment) =>
                node && !Array.isArray(node) && typeof node === 'object'
                    ? node[segment]
                    : undefined,
            translations,
        );
}

/**
 * Reads the `lang/{locale}/app.php` strings shared by HandleInertiaRequests.
 * A missing key returns the key itself, so a typo shows instead of a blank.
 */
export function useTranslations() {
    const { translations, locale, dir, supportedLocales } = usePage().props;

    /**
     * `replace` fills the `:name` placeholders Laravel strings carry, so a
     * name or a count stays inside the sentence: word order differs between
     * Arabic and English. Each `:word` is matched once, so a token that is a
     * prefix of another (`:to`, `:total`) can't eat its neighbour.
     */
    const t = (key: string, replace?: Record<string, string>): string => {
        const value = resolve(translations, key);

        if (typeof value !== 'string') {
            return key;
        }

        if (!replace) {
            return value;
        }

        return value.replace(
            /:([A-Za-z_][A-Za-z0-9_]*)/g,
            (token, name: string) => replace[name] ?? token,
        );
    };

    return { t, locale, dir, supportedLocales };
}
