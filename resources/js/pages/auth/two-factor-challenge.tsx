import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/two-factor/login';

export default function TwoFactorChallenge() {
    const { t } = useTranslations();
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    setLayoutProps(
        showRecoveryInput
            ? {
                  title: t('auth.two_factor.recovery_title'),
                  description: t('auth.two_factor.recovery_description'),
              }
            : {
                  title: t('auth.two_factor.code_title'),
                  description: t('auth.two_factor.code_description'),
              },
    );

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <>
            <Head title={t('auth.two_factor.title')} />

            <Form
                {...store.form()}
                className="flex flex-col gap-5"
                resetOnError
                resetOnSuccess={!showRecoveryInput}
            >
                {({ errors, processing, clearErrors }) => (
                    <>
                        {showRecoveryInput ? (
                            <div className="grid gap-2">
                                <Input
                                    name="recovery_code"
                                    type="text"
                                    dir="ltr"
                                    aria-label={t(
                                        'auth.two_factor.recovery_placeholder',
                                    )}
                                    placeholder={t(
                                        'auth.two_factor.recovery_placeholder',
                                    )}
                                    className="h-10 text-start font-mono"
                                    autoFocus={showRecoveryInput}
                                    required
                                />
                                <InputError message={errors.recovery_code} />
                            </div>
                        ) : (
                            <div className="flex flex-col items-center gap-3">
                                {/* Digits are entered left to right in both languages. */}
                                <div dir="ltr">
                                    <InputOTP
                                        name="code"
                                        maxLength={OTP_MAX_LENGTH}
                                        value={code}
                                        onChange={(value) => setCode(value)}
                                        disabled={processing}
                                        pattern={REGEXP_ONLY_DIGITS}
                                        autoFocus
                                    >
                                        <InputOTPGroup>
                                            {Array.from(
                                                { length: OTP_MAX_LENGTH },
                                                (_, index) => (
                                                    <InputOTPSlot
                                                        key={index}
                                                        index={index}
                                                    />
                                                ),
                                            )}
                                        </InputOTPGroup>
                                    </InputOTP>
                                </div>
                                <InputError message={errors.code} />
                            </div>
                        )}

                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
                            disabled={processing}
                        >
                            {t('auth.two_factor.submit')}
                        </Button>

                        <button
                            type="button"
                            className="self-center text-body-sm text-primary underline-offset-4 hover:underline"
                            onClick={() => toggleRecoveryMode(clearErrors)}
                        >
                            {showRecoveryInput
                                ? t('auth.two_factor.recovery_toggle')
                                : t('auth.two_factor.code_toggle')}
                        </button>
                    </>
                )}
            </Form>
        </>
    );
}
