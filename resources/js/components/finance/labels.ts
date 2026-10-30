import { translate } from '@/i18n';
export const financeLabel = (value: string) =>
    translate(
        value,
        typeof document !== 'undefined' &&
            document.documentElement.lang === 'en'
            ? 'en'
            : 'es',
    );
