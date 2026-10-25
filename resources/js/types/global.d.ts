import type { Auth } from '@/types/auth';
import type { TranslationNode } from '@/types/translations';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            locale: string;
            dir: 'rtl' | 'ltr';
            supportedLocales: string[];
            translations: Record<string, TranslationNode>;
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
