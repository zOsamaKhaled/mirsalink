import { Form, Head, setLayoutProps } from '@inertiajs/react';

import { BackArrow } from '@/components/direction-icons';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTranslations();

    setLayoutProps({
        title: t('auth.forgot.title'),
        description: t('auth.forgot.description'),
    });

    return (
        <>
            <Head title={t('auth.forgot.title')} />

            {status && (
                <p className="rounded-lg bg-success-soft px-3 py-2 text-body-sm text-success">
                    {status}
                </p>
            )}

            <div className="flex flex-col gap-6">
                <Form {...email.form()} className="flex flex-col gap-5">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('auth.login.email')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    dir="ltr"
                                    autoComplete="email"
                                    autoFocus
                                    placeholder="name@company.com"
                                    className="h-10 text-start"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <Button
                                size="lg"
                                className="w-full"
                                disabled={processing}
                                data-test="email-password-reset-link-button"
                            >
                                {processing && <Spinner />}
                                {t('auth.forgot.submit')}
                            </Button>
                        </>
                    )}
                </Form>

                <TextLink
                    href={login()}
                    className="inline-flex items-center gap-1.5 self-center text-body-sm"
                >
                    <BackArrow className="size-4" />
                    {t('auth.forgot.back')}
                </TextLink>
            </div>
        </>
    );
}
