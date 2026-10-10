import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import { LocalizedDocument } from '@/components/localized-document';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { WebVitalsReporter } from '@/components/web-vitals-reporter';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

if (import.meta.env.VITE_REVERB_APP_KEY) {
    configureEcho({
        broadcaster: 'reverb',
    });
}

const appName = (import.meta.env.VITE_APP_NAME || 'Arevantes').replace(
    /\s+CRM$/i,
    '',
);

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name.startsWith('legal/'):
            case name.startsWith('projects/'):
            case name.startsWith('services/'):
            case name.startsWith('shared/'):
            case name.startsWith('portal/'):
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
    withApp(app, { page }) {
        return (
            <LocalizedDocument>
                <TooltipProvider delayDuration={0}>
                    <WebVitalsReporter
                        initialRouteName={page.component}
                        sampleRate={
                            page.props.performance?.webVitalsSampleRate ?? 0
                        }
                    />
                    {app}
                    <Toaster />
                </TooltipProvider>
            </LocalizedDocument>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Public offline notice only; authenticated pages and writes always use the network.
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/commercial-field-sw.js', { updateViaCache: 'none' })
            .catch(() => {
                // Installation is optional: the online ERP remains available.
            });
    });
}
