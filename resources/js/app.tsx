import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

// The tab title carries the name in the page's language.
const appName = () =>
    document.documentElement.lang === 'ar' ? 'ميرسالينك' : 'Mirsalink';

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName()}` : appName()),
    layout: (name) => {
        switch (true) {
            case name.startsWith('design-system'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#FB7185',
    },
});

// This will set light / dark mode on load...
initializeTheme();
