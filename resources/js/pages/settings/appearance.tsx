import { Head, setLayoutProps } from '@inertiajs/react';

import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';

export default function Appearance() {
    const { t } = useTranslations();

    setLayoutProps({
        breadcrumbs: [
            { title: t('settings.title'), href: editProfile() },
            { title: t('settings.nav.appearance'), href: editAppearance() },
        ],
    });

    return (
        <>
            <Head title={t('settings.nav.appearance')} />

            <div className="flex flex-col gap-6">
                <Heading
                    variant="small"
                    title={t('settings.appearance.heading')}
                    description={t('settings.appearance.description')}
                />
                <AppearanceTabs />
            </div>
        </>
    );
}
