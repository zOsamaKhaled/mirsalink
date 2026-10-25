import { Form, Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import {
    Check,
    Copy,
    MailPlus,
    MoreHorizontal,
    ShieldCheck,
    UserPlus,
    UserRound,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import { AgentStatusDot } from '@/components/inbox/agent-status';
import InputError from '@/components/input-error';
import { TeamHeader } from '@/components/team/team-header';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import {
    deactivate,
    index,
    invitation,
    reactivate,
    role as changeRole,
    store,
} from '@/routes/team/members';
import type { Availability, Role } from '@/types';

type Member = {
    id: number;
    name: string;
    email: string;
    role: Role;
    availability: Availability;
    status: 'active' | 'invited' | 'deactivated';
    invited_at: string | null;
    invited_by: string | null;
    is_me: boolean;
};

type SentInvitation = { name: string; email: string; link: string };

export default function TeamMembers({ members }: { members: Member[] }) {
    const { t } = useTranslations();
    const flash = usePage().flash as { invitation?: SentInvitation };
    const [inviteOpen, setInviteOpen] = useState(false);
    const [sent, setSent] = useState<SentInvitation | null>(null);
    const [toDeactivate, setToDeactivate] = useState<Member | null>(null);

    setLayoutProps({
        breadcrumbs: [{ title: t('team.title'), href: index() }],
    });

    // A new or resent invitation comes back as flash data: show its link.
    useEffect(() => {
        if (flash.invitation) {
            setInviteOpen(false);
            setSent(flash.invitation);
        }
    }, [flash.invitation]);

    const active = members.filter((m) => m.status === 'active').length;
    const invited = members.filter((m) => m.status === 'invited').length;

    return (
        <>
            <Head title={t('team.title')} />

            <div className="flex flex-col gap-6 px-4 py-8 sm:px-6">
                <TeamHeader
                    action={
                        <Button onClick={() => setInviteOpen(true)}>
                            <UserPlus />
                            {t('team.invite.button')}
                        </Button>
                    }
                />

                <p className="text-body-sm text-muted-foreground">
                    {t('team.summary', {
                        active: String(active),
                        invited: String(invited),
                    })}
                </p>

                <ul className="flex max-w-4xl flex-col overflow-hidden rounded-2xl border bg-card">
                    {members.map((member) => (
                        <MemberRow
                            key={member.id}
                            member={member}
                            onDeactivate={() => setToDeactivate(member)}
                        />
                    ))}
                </ul>
            </div>

            <InviteDialog open={inviteOpen} onOpenChange={setInviteOpen} />
            <InvitationLinkDialog
                invitation={sent}
                onClose={() => setSent(null)}
            />
            <DeactivateDialog
                member={toDeactivate}
                onClose={() => setToDeactivate(null)}
            />
        </>
    );
}

function MemberRow({
    member,
    onDeactivate,
}: {
    member: Member;
    onDeactivate: () => void;
}) {
    const { t } = useTranslations();
    const getInitials = useInitials();

    return (
        <li
            className={cn(
                'flex flex-wrap items-center gap-x-4 gap-y-2 border-t px-4 py-4 first:border-t-0 sm:px-5',
                member.status === 'deactivated' && 'bg-muted/40',
            )}
        >
            <div className="relative">
                <Avatar className="size-11">
                    <AvatarFallback
                        className={cn(
                            'font-bold',
                            member.status === 'active'
                                ? 'bg-secondary text-secondary-foreground'
                                : 'bg-muted text-muted-foreground',
                        )}
                    >
                        {getInitials(member.name)}
                    </AvatarFallback>
                </Avatar>
                {member.status === 'active' && (
                    <AgentStatusDot
                        status={member.availability}
                        className="absolute -end-0.5 -bottom-0.5"
                    />
                )}
            </div>

            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={cn(
                            'text-body font-bold',
                            member.status === 'deactivated' &&
                                'text-muted-foreground',
                        )}
                    >
                        {member.name}
                    </span>
                    {member.is_me && (
                        <Badge variant="neutral" className="rounded-full">
                            {t('team.you')}
                        </Badge>
                    )}
                </div>
                <span className="truncate text-body-sm text-muted-foreground">
                    <bdi dir="ltr">{member.email}</bdi>
                </span>
                {member.status === 'invited' && member.invited_by && (
                    <span className="text-caption text-muted-foreground">
                        {t('team.invited_by', { name: member.invited_by })}
                    </span>
                )}
            </div>

            {/* On phones the badges drop under the name, lined up past the avatar. */}
            <div className="flex w-full items-center gap-2 ps-15 sm:w-auto sm:ps-0">
                <Badge
                    variant={member.role === 'supervisor' ? 'info' : 'neutral'}
                    className="gap-1 rounded-full"
                >
                    {member.role === 'supervisor' ? (
                        <ShieldCheck />
                    ) : (
                        <UserRound />
                    )}
                    {t(`team.roles.${member.role}`)}
                </Badge>

                {member.status === 'active' ? (
                    <span className="hidden w-16 text-body-sm text-muted-foreground sm:inline">
                        {t(`availability.${member.availability}`)}
                    </span>
                ) : (
                    <Badge
                        variant={
                            member.status === 'invited' ? 'warning' : 'neutral'
                        }
                        className="rounded-full"
                    >
                        {t(`team.status.${member.status}`)}
                    </Badge>
                )}

                {member.is_me ? (
                    <span className="ms-auto size-8 sm:ms-0" aria-hidden />
                ) : (
                    <span className="ms-auto sm:ms-0">
                        <MemberActions
                            member={member}
                            onDeactivate={onDeactivate}
                        />
                    </span>
                )}
            </div>
        </li>
    );
}

function MemberActions({
    member,
    onDeactivate,
}: {
    member: Member;
    onDeactivate: () => void;
}) {
    const { t, dir } = useTranslations();
    const otherRole: Role =
        member.role === 'supervisor' ? 'agent' : 'supervisor';

    const post = (url: string) =>
        router.post(url, {}, { preserveScroll: true });

    return (
        <DropdownMenu dir={dir}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    aria-label={t('team.actions.menu', { name: member.name })}
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-52">
                {member.status !== 'deactivated' && (
                    <DropdownMenuItem
                        onSelect={() =>
                            router.patch(
                                changeRole.url(member.id),
                                { role: otherRole },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {otherRole === 'supervisor' ? (
                            <ShieldCheck />
                        ) : (
                            <UserRound />
                        )}
                        {t(
                            otherRole === 'supervisor'
                                ? 'team.actions.make_supervisor'
                                : 'team.actions.make_agent',
                        )}
                    </DropdownMenuItem>
                )}
                {member.status === 'invited' && (
                    <DropdownMenuItem
                        onSelect={() => post(invitation.url(member.id))}
                    >
                        <MailPlus />
                        {t('team.actions.resend')}
                    </DropdownMenuItem>
                )}
                <DropdownMenuSeparator />
                {member.status === 'deactivated' ? (
                    <DropdownMenuItem
                        onSelect={() => post(reactivate.url(member.id))}
                    >
                        {t('team.actions.reactivate')}
                    </DropdownMenuItem>
                ) : (
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={onDeactivate}
                    >
                        {t('team.actions.deactivate')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function InviteDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslations();
    const [role, setRole] = useState<Role>('agent');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>{t('team.invite.title')}</DialogTitle>
                <DialogDescription>
                    {t('team.invite.description')}
                </DialogDescription>

                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => setRole('agent')}
                    className="flex flex-col gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="invite-name">
                                    {t('team.invite.name')}
                                </Label>
                                <Input
                                    id="invite-name"
                                    name="name"
                                    required
                                    autoFocus
                                    autoComplete="off"
                                    placeholder={t(
                                        'team.invite.name_placeholder',
                                    )}
                                    className="h-10"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="invite-email">
                                    {t('team.invite.email')}
                                </Label>
                                <Input
                                    id="invite-email"
                                    name="email"
                                    type="email"
                                    dir="ltr"
                                    required
                                    autoComplete="off"
                                    placeholder="name@company.com"
                                    className="h-10 text-start"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <fieldset className="grid gap-2">
                                <legend className="mb-2 text-sm font-medium">
                                    {t('team.invite.role')}
                                </legend>
                                <input type="hidden" name="role" value={role} />
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {(['agent', 'supervisor'] as const).map(
                                        (option) => (
                                            <button
                                                key={option}
                                                type="button"
                                                role="radio"
                                                aria-checked={role === option}
                                                onClick={() => setRole(option)}
                                                className={cn(
                                                    'flex flex-col gap-1 rounded-xl border p-3 text-start transition-colors',
                                                    'focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                                    role === option
                                                        ? 'border-primary bg-accent'
                                                        : 'hover:bg-accent/50',
                                                )}
                                            >
                                                <span className="flex items-center gap-2 text-body-sm font-bold">
                                                    {option === 'supervisor' ? (
                                                        <ShieldCheck className="size-4" />
                                                    ) : (
                                                        <UserRound className="size-4" />
                                                    )}
                                                    {t(`team.roles.${option}`)}
                                                </span>
                                                <span className="text-caption text-muted-foreground">
                                                    {t(
                                                        `team.role_hints.${option}`,
                                                    )}
                                                </span>
                                            </button>
                                        ),
                                    )}
                                </div>
                                <InputError message={errors.role} />
                            </fieldset>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        {t('common.cancel')}
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {t('team.invite.submit')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function InvitationLinkDialog({
    invitation,
    onClose,
}: {
    invitation: SentInvitation | null;
    onClose: () => void;
}) {
    const { t } = useTranslations();
    const [copiedText, copy] = useClipboard();
    const copied = invitation !== null && copiedText === invitation.link;

    return (
        <Dialog
            open={invitation !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-md">
                {invitation && (
                    <>
                        <DialogTitle>
                            {t('team.link.title', { name: invitation.name })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('team.link.description', {
                                email: invitation.email,
                            })}
                        </DialogDescription>
                        <div className="flex gap-2" dir="ltr">
                            <Input
                                readOnly
                                value={invitation.link}
                                aria-label={t('team.link.copy')}
                                className="h-10 font-mono text-caption"
                                onFocus={(event) => event.target.select()}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                className="h-10 shrink-0"
                                onClick={() => void copy(invitation.link)}
                            >
                                {copied ? <Check /> : <Copy />}
                                {copied
                                    ? t('team.link.copied')
                                    : t('team.link.copy')}
                            </Button>
                        </div>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button type="button">
                                    {t('team.link.done')}
                                </Button>
                            </DialogClose>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

function DeactivateDialog({
    member,
    onClose,
}: {
    member: Member | null;
    onClose: () => void;
}) {
    const { t } = useTranslations();
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        if (!member) {
            return;
        }

        router.post(
            deactivate.url(member.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    onClose();
                },
            },
        );
    };

    return (
        <Dialog
            open={member !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-md">
                {member && (
                    <>
                        <DialogTitle>
                            {t('team.deactivate_dialog.title', {
                                name: member.name,
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('team.deactivate_dialog.body')}
                        </DialogDescription>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    {t('common.cancel')}
                                </Button>
                            </DialogClose>
                            <Button
                                type="button"
                                variant="destructive"
                                disabled={processing}
                                onClick={confirm}
                            >
                                {processing && <Spinner />}
                                {t('team.deactivate_dialog.confirm')}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
