import { router, usePage } from '@inertiajs/react';
import { ChevronDown, Hand, UserRound, UserX } from 'lucide-react';

import { AgentStatusDot } from '@/components/inbox/agent-status';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/hooks/use-translations';
import { update as assignee } from '@/routes/inbox/assignee';
import type { InboxMember, SelectedConversation } from '@/types/inbox';

/**
 * Who has the conversation, and what this member may do about it: anyone
 * takes an unassigned one; its own member or a supervisor passes it on or
 * puts it back.
 */
export function AssignMenu({
    conversation,
    members,
}: {
    conversation: SelectedConversation;
    members: InboxMember[];
}) {
    const { t } = useTranslations();
    const { auth } = usePage().props;
    const current = conversation.assignee;
    const mine = current?.id === auth.user.id;
    const canChoose = auth.can.manageTeam || mine;

    const assign = (userId: number | null) =>
        router.put(
            assignee.url(conversation.id),
            { user_id: userId },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    size="sm"
                    variant="outline"
                    className="max-w-44"
                    aria-label={t('inbox.thread.assignee')}
                >
                    <UserRound />
                    <span className="truncate">
                        {current?.name ?? t('inbox.unassigned_label')}
                    </span>
                    <ChevronDown className="opacity-60" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60">
                {!mine && (
                    <DropdownMenuItem onSelect={() => assign(auth.user.id)}>
                        <Hand />
                        {t('inbox.thread.take')}
                    </DropdownMenuItem>
                )}
                {canChoose && (
                    <>
                        {!mine && <DropdownMenuSeparator />}
                        <DropdownMenuLabel className="text-caption text-muted-foreground">
                            {t('inbox.thread.assign_to')}
                        </DropdownMenuLabel>
                        {members
                            .filter(
                                (member) =>
                                    member.id !== current?.id &&
                                    member.id !== auth.user.id,
                            )
                            .map((member) => (
                                <DropdownMenuItem
                                    key={member.id}
                                    onSelect={() => assign(member.id)}
                                >
                                    <AgentStatusDot
                                        status={member.availability}
                                        className="ring-0"
                                    />
                                    <span className="min-w-0 flex-1 truncate">
                                        {member.name}
                                    </span>
                                    <span className="text-caption text-muted-foreground">
                                        {t(
                                            `availability.${member.availability}`,
                                        )}
                                    </span>
                                </DropdownMenuItem>
                            ))}
                        {current && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onSelect={() => assign(null)}>
                                    <UserX />
                                    {t('inbox.thread.unassign')}
                                </DropdownMenuItem>
                            </>
                        )}
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
