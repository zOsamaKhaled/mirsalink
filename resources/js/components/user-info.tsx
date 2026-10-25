import { AgentStatusDot } from '@/components/inbox/agent-status';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    showAvailability = false,
}: {
    user: User;
    showEmail?: boolean;
    /** The member's status as a dot on their avatar, as the team sees it. */
    showAvailability?: boolean;
}) {
    const getInitials = useInitials();

    return (
        <>
            <span className="relative shrink-0">
                <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                    <AvatarImage src={user.avatar} alt={user.name} />
                    <AvatarFallback className="rounded-full bg-handover font-bold text-handover-foreground">
                        {getInitials(user.name)}
                    </AvatarFallback>
                </Avatar>
                {showAvailability && (
                    <AgentStatusDot
                        status={user.availability}
                        className="absolute -end-0.5 -bottom-0.5 ring-sidebar"
                    />
                )}
            </span>
            <div className="grid flex-1 text-start text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {showEmail && (
                    <span className="truncate text-xs text-muted-foreground">
                        <bdi dir="ltr">{user.email}</bdi>
                    </span>
                )}
            </div>
        </>
    );
}
