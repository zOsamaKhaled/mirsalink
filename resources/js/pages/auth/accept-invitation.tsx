import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';

type Props = {
    name: string;
    email: string;
    inviter: string | null;
};

export default function AcceptInvitation({ name, email, inviter }: Props) {
    const { t } = useTranslations();
    const form = useForm({
        name,
        password: '',
        password_confirmation: '',
    });

    setLayoutProps({
        title: t('invitation.title'),
        description: inviter
            ? t('invitation.description', { inviter })
            : t('invitation.description_anonymous'),
    });

    // The link is signed: the form goes back to the very same address,
    // signature and all, rather than to a route built afresh.
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(window.location.pathname + window.location.search, {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title={t('invitation.title')} />

            <form onSubmit={submit} className="grid gap-5">
                <div className="grid gap-2">
                    <Label htmlFor="email">{t('invitation.email')}</Label>
                    <Input
                        id="email"
                        type="email"
                        dir="ltr"
                        value={email}
                        readOnly
                        className="h-10 text-start text-muted-foreground"
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="name">{t('invitation.name')}</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        required
                        autoComplete="name"
                        className="h-10"
                    />
                    <InputError message={form.errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">{t('invitation.password')}</Label>
                    <PasswordInput
                        id="password"
                        value={form.data.password}
                        onChange={(event) =>
                            form.setData('password', event.target.value)
                        }
                        required
                        autoFocus
                        autoComplete="new-password"
                        className="h-10"
                    />
                    <InputError message={form.errors.password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">
                        {t('invitation.confirm')}
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        value={form.data.password_confirmation}
                        onChange={(event) =>
                            form.setData(
                                'password_confirmation',
                                event.target.value,
                            )
                        }
                        required
                        autoComplete="new-password"
                        className="h-10"
                    />
                </div>

                <Button
                    type="submit"
                    size="lg"
                    className="mt-2 w-full"
                    disabled={form.processing}
                >
                    {form.processing && <Spinner />}
                    {t('invitation.submit')}
                </Button>
            </form>
        </>
    );
}
