import type { TagTone } from '@/components/inbox/category-tag';
import type { DeliveryStatus } from '@/components/inbox/message-status';
import type { Availability } from '@/types/auth';

export type InboxTab = 'mine' | 'unassigned' | 'waiting' | 'all' | 'closed';

export type InboxTag = {
    id: number;
    name: string;
    color: TagTone;
    by_bot?: boolean;
};

export type InboxMember = {
    id: number;
    name: string;
    availability: Availability;
};

export type ConversationSummary = {
    id: number;
    name: string | null;
    phone: string | null;
    preview: string;
    preview_from_team: boolean;
    last_message_at: string | null;
    unread: number;
    closed: boolean;
    waiting_since: string | null;
    waiting_long: boolean;
    tags: InboxTag[];
    assignee: { id: number; name: string } | null;
};

export type SelectedConversation = ConversationSummary & {
    contact: {
        id: number;
        name: string | null;
        phone: string;
        email: string | null;
        company: string | null;
        notes: string | null;
        opted_out: boolean;
        deleted: boolean;
        since: string | null;
    };
    contact_tags: InboxTag[];
    window_closes_at: string | null;
    window_open: boolean;
    first_response_at: string | null;
};

export type ThreadMessage = {
    kind: 'message';
    id: number;
    at: string;
    direction: 'inbound' | 'outbound';
    sender: 'contact' | 'agent' | 'bot' | 'system';
    type:
        | 'text'
        | 'image'
        | 'document'
        | 'audio'
        | 'video'
        | 'location'
        | 'template'
        | 'note';
    body: string | null;
    author: string | null;
    media: {
        url: string;
        name: string | null;
        mime: string | null;
        size: number | null;
    } | null;
    status: DeliveryStatus | null;
    error: string | null;
    template: {
        name: string;
        header: string | null;
        footer: string | null;
        buttons: string[];
    } | null;
};

export type ThreadEvent = {
    kind: 'event';
    id: string;
    at: string;
    type: string;
    text: string;
};

export type ThreadItem = ThreadMessage | ThreadEvent;

export type IncomingMessage = {
    id: number;
    conversation_id: number;
    name: string | null;
    preview: string;
};
