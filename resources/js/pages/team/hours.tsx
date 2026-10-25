import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { MessageBubble } from '@/components/inbox/message-bubble';
import InputError from '@/components/input-error';
import { TeamHeader } from '@/components/team/team-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { index as members } from '@/routes/team/members';
import { edit, update } from '@/routes/team/hours';

type Day = { open: boolean; from: string; to: string };

type Hours = {
    timezone: string;
    days: Day[];
    auto_reply_enabled: boolean;
    auto_reply_message: string;
};

export default function TeamHours({
    hours,
    timezones,
}: {
    hours: Hours;
    timezones: string[];
}) {
    const { t } = useTranslations();
    const form = useForm<Hours>(hours);
    const dayNames = Array.from({ length: 7 }, (_, day) =>
        t(`team.hours.days.${day}`),
    );

    setLayoutProps({
        breadcrumbs: [
            { title: t('team.title'), href: members() },
            { title: t('team.tabs.hours'), href: edit() },
        ],
    });

    const setDay = (day: number, change: Partial<Day>) =>
        form.setData(
            'days',
            form.data.days.map((value, index) =>
                index === day ? { ...value, ...change } : value,
            ),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(update(), { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('team.tabs.hours')} />

            <form
                onSubmit={submit}
                className="flex flex-col gap-6 px-4 py-8 sm:px-6"
            >
                <TeamHeader />

                <section className="flex max-w-3xl flex-col gap-5 rounded-2xl border bg-card p-5 sm:p-6">
                    <div className="flex flex-col gap-1">
                        <h2 className="text-title font-bold">
                            {t('team.hours.title')}
                        </h2>
                        <p className="text-body-sm text-muted-foreground">
                            {t('team.hours.description')}
                        </p>
                    </div>

                    <div className="grid max-w-xs gap-2">
                        <Label htmlFor="timezone">
                            {t('team.hours.timezone')}
                        </Label>
                        <select
                            id="timezone"
                            value={form.data.timezone}
                            onChange={(event) =>
                                form.setData('timezone', event.target.value)
                            }
                            className="h-10 rounded-md border border-input bg-card px-3 text-body-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            {timezones.map((zone) => (
                                <option key={zone} value={zone}>
                                    {t(`team.hours.timezones.${zone}`)}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.timezone} />
                    </div>

                    <fieldset className="flex flex-col">
                        <legend className="mb-2 text-body-sm font-bold">
                            {t('team.hours.week')}
                        </legend>
                        {form.data.days.map((day, index) => (
                            <div
                                key={index}
                                className="flex flex-col gap-1 border-t py-3 first:border-t-0"
                            >
                                <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                                    <div className="flex w-40 items-center gap-3">
                                        <Switch
                                            checked={day.open}
                                            onCheckedChange={(open) =>
                                                setDay(index, { open })
                                            }
                                            aria-label={`${dayNames[index]}: ${t('team.hours.open')}`}
                                        />
                                        <span
                                            className={cn(
                                                'text-body-sm font-bold',
                                                !day.open &&
                                                    'text-muted-foreground',
                                            )}
                                        >
                                            {dayNames[index]}
                                        </span>
                                    </div>

                                    {day.open ? (
                                        <div className="flex items-center gap-2 text-body-sm">
                                            <span className="text-muted-foreground">
                                                {t('team.hours.from')}
                                            </span>
                                            <Input
                                                type="time"
                                                dir="ltr"
                                                value={day.from}
                                                onChange={(event) =>
                                                    setDay(index, {
                                                        from: event.target
                                                            .value,
                                                    })
                                                }
                                                aria-label={`${dayNames[index]}: ${t('team.hours.from')}`}
                                                className="h-9 w-32 font-mono"
                                            />
                                            <span className="text-muted-foreground">
                                                {t('team.hours.to')}
                                            </span>
                                            <Input
                                                type="time"
                                                dir="ltr"
                                                value={day.to}
                                                onChange={(event) =>
                                                    setDay(index, {
                                                        to: event.target.value,
                                                    })
                                                }
                                                aria-label={`${dayNames[index]}: ${t('team.hours.to')}`}
                                                className="h-9 w-32 font-mono"
                                            />
                                        </div>
                                    ) : (
                                        <span className="text-body-sm text-muted-foreground">
                                            {t('team.hours.closed')}
                                        </span>
                                    )}
                                </div>
                                <InputError
                                    message={
                                        (form.errors as Record<string, string>)[
                                            `days.${index}.to`
                                        ]
                                    }
                                />
                            </div>
                        ))}
                    </fieldset>
                </section>

                <section className="flex max-w-3xl flex-col gap-5 rounded-2xl border bg-card p-5 sm:p-6">
                    <div className="flex flex-col gap-1">
                        <h2 className="text-title font-bold">
                            {t('team.hours.reply_title')}
                        </h2>
                        <p className="text-body-sm text-muted-foreground">
                            {t('team.hours.reply_description')}
                        </p>
                    </div>

                    <label className="flex items-center gap-3 text-body-sm font-bold">
                        <Switch
                            checked={form.data.auto_reply_enabled}
                            onCheckedChange={(enabled) =>
                                form.setData('auto_reply_enabled', enabled)
                            }
                        />
                        {t('team.hours.reply_toggle')}
                    </label>

                    <div className="grid gap-5 lg:grid-cols-2">
                        <div className="grid content-start gap-2">
                            <Label htmlFor="auto_reply_message">
                                {t('team.hours.reply_label')}
                            </Label>
                            <Textarea
                                id="auto_reply_message"
                                dir="auto"
                                rows={5}
                                maxLength={1000}
                                value={form.data.auto_reply_message}
                                disabled={!form.data.auto_reply_enabled}
                                onChange={(event) =>
                                    form.setData(
                                        'auto_reply_message',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.auto_reply_message}
                            />
                            <p className="text-caption text-muted-foreground">
                                {t('team.hours.pending_inbox')}
                            </p>
                        </div>

                        <div className="flex flex-col gap-2">
                            <span className="text-sm font-medium">
                                {t('team.hours.preview')}
                            </span>
                            <div
                                className={cn(
                                    'flex flex-1 flex-col justify-end rounded-xl bg-sunken p-4 transition-opacity',
                                    !form.data.auto_reply_enabled &&
                                        'opacity-50',
                                )}
                            >
                                <MessageBubble
                                    kind="bot"
                                    author={t('team.hours.preview_author')}
                                    time="22:14"
                                >
                                    {form.data.auto_reply_message}
                                </MessageBubble>
                            </div>
                        </div>
                    </div>
                </section>

                <div>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {t('team.hours.save')}
                    </Button>
                </div>
            </form>
        </>
    );
}
