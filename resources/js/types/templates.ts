export type TemplateStatus = 'draft' | 'pending' | 'approved' | 'rejected';

export type TemplateCategory = 'marketing' | 'utility';

export type TemplateHeaderType =
    | 'none'
    | 'text'
    | 'image'
    | 'document'
    | 'video';

export type TemplateButton = {
    type: 'quick_reply' | 'url' | 'phone';
    text: string;
    url?: string | null;
    example?: string | null;
    phone?: string | null;
};

export type WhatsAppTemplate = {
    id: number;
    name: string;
    language: 'ar' | 'en';
    category: TemplateCategory;
    header_type: TemplateHeaderType;
    header_text: string | null;
    header_example: string | null;
    body: string;
    body_examples: string[];
    footer: string | null;
    buttons: TemplateButton[];
    status: TemplateStatus;
    rejection_reason: string | null;
    submitted_at: string | null;
    decided_at: string | null;
    updated_at: string | null;
    variables: { body: number; header: boolean; button: boolean };
};

export type QuickReply = {
    id: number;
    shortcut: string;
    title: string;
    body: string;
    shared: boolean;
    can_manage?: boolean;
};

/** The numbers of the {{n}} variables in a text, as Template::variablesIn reads them. */
export function variablesIn(text: string | null | undefined): number[] {
    return Array.from((text ?? '').matchAll(/\{\{\s*(\d+)\s*\}\}/g), (match) =>
        Number(match[1]),
    );
}

/** How many distinct body variables a text holds. */
export function variableCount(text: string | null | undefined): number {
    return new Set(variablesIn(text)).size;
}
