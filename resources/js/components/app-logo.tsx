import { usePage } from '@inertiajs/react';

import { MirsalinkWordmark } from '@/components/brand/mirsalink-logo';

export default function AppLogo() {
    const { locale } = usePage().props;

    return (
        <MirsalinkWordmark
            lang={locale === 'ar' ? 'ar' : 'en'}
            tone="on-dark"
            className="text-xl"
        />
    );
}
