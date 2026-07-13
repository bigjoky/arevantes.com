import { cn } from '@/lib/utils';

export default function BrandLogo({
    className,
    compact = false,
}: {
    className?: string;
    compact?: boolean;
}) {
    if (compact) {
        return (
            <span
                className={cn(
                    'inline-flex size-9 shrink-0 items-center justify-center rounded-md bg-zinc-950 p-2 shadow-sm',
                    className,
                )}
            >
                <svg
                    viewBox="140 300 165 190"
                    role="img"
                    aria-label="Arevantes"
                    className="size-full"
                >
                    <path
                        fill="#f6f6f6"
                        d="M244.22,307l54.9,176.53h-32.99l-13.35-44.83h-63.46l-13.35,44.83h-31.73l54.65-176.53h45.33ZM197.13,412.27h47.85l-11.08-37.77c-4.78-17.12-10.07-34.75-12.09-45.83h-1.01c-2.27,10.58-7.56,29.46-12.09,45.08l-11.58,38.53Z"
                    />
                </svg>
            </span>
        );
    }

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-lg bg-zinc-950 px-3 py-2 shadow-sm',
                className,
            )}
        >
            <img
                src="/brand/arevantes-logo.svg"
                alt="Arevantes"
                className="h-full w-auto"
            />
        </span>
    );
}
