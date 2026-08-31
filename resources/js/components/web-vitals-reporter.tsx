import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import type { Metric } from 'web-vitals';

let initialized = false;
let samplingDecision: boolean | null = null;
let currentRouteName = 'unknown';

export function WebVitalsReporter() {
    const page = usePage();
    const sampleRate = page.props.performance.webVitalsSampleRate;
    const routeName = page.component;

    useEffect(() => {
        currentRouteName = routeName;
        samplingDecision ??= Math.random() <= sampleRate;

        if (
            initialized ||
            sampleRate <= 0 ||
            !samplingDecision ||
            navigator.doNotTrack === '1'
        ) {
            return;
        }

        initialized = true;

        const report = (metric: Metric) => {
            const token = document
                .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.getAttribute('content');

            if (!token) {
                return;
            }

            void fetch('/performance/web-vitals', {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    route_name: currentRouteName,
                    metric: metric.name,
                    value: metric.value,
                    delta: metric.delta,
                    device: deviceClass(),
                    navigation_type: metric.navigationType ?? 'navigate',
                }),
            }).catch(() => undefined);
        };

        void import('web-vitals')
            .then(({ onCLS, onINP, onLCP }) => {
                onCLS(report);
                onINP(report);
                onLCP(report);
            })
            .catch(() => undefined);
    }, [routeName, sampleRate]);

    return null;
}

function deviceClass(): 'mobile' | 'tablet' | 'desktop' {
    if (window.innerWidth < 768) {
        return 'mobile';
    }

    return window.innerWidth < 1280 ? 'tablet' : 'desktop';
}
