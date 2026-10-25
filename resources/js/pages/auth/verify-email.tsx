import { Form, Head, setLayoutProps } from '@inertiajs/react';

import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslations();

    setLayoutProps({
        title: t('auth.verify.title'),
        description: t('auth.verify.description'),
    });

    return (
        <>
            <Head title={t('auth.verify.title')} />

            {status === 'verification-link-sent' && (
                <p className="rounded-lg bg-success-soft px-3 py-2 text-body-sm text-success">
                    {t('auth.verify.sent')}
                </p>
            )}

            <Form {...send.form()} className="flex flex-col gap-5">
                {({ processing }) => (
                    <>
                        <Button
                            size="lg"
                            disabled={processing}
                            variant="secondary"
                        >
                            {processing && <Spinner />}
                            {t('auth.verify.resend')}
                        </Button>

                        <TextLink
                            href={logout()}
                            className="self-center text-body-sm"
                        >
                            {t('auth.verify.logout')}
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}
