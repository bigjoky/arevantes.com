import { router } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { translate } from '@/i18n';
import type { Locale } from '@/i18n';

const translatedAttributes = ['aria-label', 'alt', 'placeholder', 'title'];
const ignoredElements = new Set(['CODE', 'SCRIPT', 'STYLE']);

export function LocalizedDocument({ children }: { children: ReactNode }) {
    const [locale, setLocale] = useState<Locale>(() =>
        document.documentElement.lang === 'es' ? 'es' : 'en',
    );
    const [revision, setRevision] = useState(0);

    useEffect(() => {
        const updateLocale = (page: { props: Record<string, unknown> }) => {
            const localization = page.props.localization as
                | { current?: string }
                | undefined;

            setLocale(localization?.current === 'es' ? 'es' : 'en');
            setRevision((current) => current + 1);
        };
        const removeSuccessListener = router.on('success', (event) =>
            updateLocale(event.detail.page),
        );
        const removeNavigateListener = router.on('navigate', (event) =>
            updateLocale(event.detail.page),
        );

        return () => {
            removeSuccessListener();
            removeNavigateListener();
        };
    }, []);

    useLayoutEffect(() => {
        document.documentElement.lang = locale;

        const localizeText = (node: Text) => {
            const parent = node.parentElement;

            if (
                !parent ||
                ignoredElements.has(parent.tagName) ||
                parent.closest(
                    '[data-localize="off"], [contenteditable="true"]',
                )
            ) {
                return;
            }

            const value = node.nodeValue ?? '';
            const content = value.trim();

            if (!content) {
                return;
            }

            const translated = translate(content, locale);

            if (translated === content) {
                return;
            }

            const leading = value.match(/^\s*/)?.[0] ?? '';
            const trailing = value.match(/\s*$/)?.[0] ?? '';
            node.nodeValue = `${leading}${translated}${trailing}`;
        };

        const localizeElement = (element: Element) => {
            if (element.closest('[data-localize="off"]')) {
                return;
            }

            for (const attribute of translatedAttributes) {
                const value = element.getAttribute(attribute);

                if (!value) {
                    continue;
                }

                const translated = translate(value, locale);

                if (translated !== value) {
                    element.setAttribute(attribute, translated);
                }
            }
        };

        const localizeTree = (root: Node) => {
            if (root.nodeType === Node.TEXT_NODE) {
                localizeText(root as Text);

                return;
            }

            if (root.nodeType !== Node.ELEMENT_NODE) {
                return;
            }

            const element = root as Element;
            localizeElement(element);
            const walker = document.createTreeWalker(
                element,
                NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT,
            );
            let current = walker.nextNode();

            while (current) {
                if (current.nodeType === Node.TEXT_NODE) {
                    localizeText(current as Text);
                } else {
                    localizeElement(current as Element);
                }

                current = walker.nextNode();
            }
        };

        localizeTree(document.head);
        localizeTree(document.body);
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (mutation.type === 'characterData') {
                    localizeText(mutation.target as Text);
                    continue;
                }

                for (const node of mutation.addedNodes) {
                    localizeTree(node);
                }
            }
        });
        observer.observe(document.documentElement, {
            childList: true,
            characterData: true,
            subtree: true,
        });

        return () => observer.disconnect();
    }, [locale, revision]);

    return children;
}
