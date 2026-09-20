import { usePage } from '@inertiajs/react';
import { useId } from 'react';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

/** Business date only: never changes the technical audit timestamps. */
export default function HistoricalDocumentDate() {
    const id = useId();
    const { locale } = useLocale();
    const { auth, errors } = usePage<{
        auth: { user: { role: string } };
        errors: Record<string, string>;
    }>().props;

    if (auth.user.role !== 'admin') {
        return null;
    }

    const now = new Date();
    const today = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 10);

    return (
        <div className="grid gap-2">
            <label htmlFor={id}>{translate('Issue date', locale)}</label>
            <Input
                id={id}
                name="issue_date"
                type="date"
                defaultValue={today}
                required
                aria-invalid={!!errors.issue_date}
            />
            {errors.issue_date && (
                <p role="alert" className="text-sm text-destructive">
                    {errors.issue_date}
                </p>
            )}
        </div>
    );
}
