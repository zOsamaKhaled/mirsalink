export type BroadcastStatus =
    | 'draft'
    | 'scheduled'
    | 'sending'
    | 'paused'
    | 'completed'
    | 'cancelled';

export type AudienceType = 'all' | 'tags' | 'csv';

export type VariableSource = 'name' | 'company' | 'phone' | 'column' | 'text';

export type VariableSpec = {
    source: VariableSource;
    value?: string | null;
    fallback?: string | null;
};

export type BroadcastVariables = {
    body: VariableSpec[];
    header?: VariableSpec | null;
    button?: VariableSpec | null;
};

export type BroadcastStats = {
    total: number;
    queued: number;
    skipped: number;
    sent: number;
    delivered: number;
    read: number;
    replied: number;
    failed: number;
};

export type BroadcastQuota = {
    per_minute: number;
    daily_limit: number;
    remaining_today: number;
};
