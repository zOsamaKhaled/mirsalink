import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { useTranslations } from '@/hooks/use-translations';
import { show } from '@/routes/inbox';
import type { IncomingMessage } from '@/types/inbox';

type Permission = NotificationPermission | 'unsupported';

function currentPermission(): Permission {
    return typeof window !== 'undefined' && 'Notification' in window
        ? Notification.permission
        : 'unsupported';
}

/**
 * A browser notification for each new customer message in the member's
 * own conversations and the unassigned, unless they are already looking at
 * that conversation. What the page showed when it opened counts as seen.
 */
export function useInboxNotifications(
    incoming: IncomingMessage[],
    openConversationId: number | null,
) {
    const { t } = useTranslations();
    const [permission, setPermission] = useState<Permission>(currentPermission);
    const lastSeen = useRef<number | null>(null);

    useEffect(() => {
        const newest = incoming.reduce(
            (highest, message) => Math.max(highest, message.id),
            0,
        );

        if (lastSeen.current === null) {
            lastSeen.current = newest;

            return;
        }

        const fresh = incoming
            .filter((message) => message.id > (lastSeen.current ?? 0))
            .reverse();
        lastSeen.current = Math.max(lastSeen.current, newest);

        if (permission !== 'granted') {
            return;
        }

        for (const message of fresh) {
            const looking =
                message.conversation_id === openConversationId &&
                document.visibilityState === 'visible';

            if (looking) {
                continue;
            }

            const notification = new Notification(
                t('inbox.notifications.new_message', {
                    name: message.name ?? '',
                }),
                {
                    body: message.preview,
                    tag: `conversation-${message.conversation_id}`,
                },
            );

            notification.onclick = () => {
                window.focus();
                router.visit(show.url(message.conversation_id));
                notification.close();
            };
        }
    }, [incoming, openConversationId, permission, t]);

    const request = useCallback(() => {
        if (permission === 'unsupported') {
            return;
        }

        void Notification.requestPermission().then(setPermission);
    }, [permission]);

    return { permission, request };
}
