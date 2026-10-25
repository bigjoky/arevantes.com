import { usePage } from '@inertiajs/react';
import type { ImgHTMLAttributes } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import { resolveCompanyLogo } from '@/lib/company-brand';
import type { CompanyBranding, BrandSurface } from '@/lib/company-brand';
import { cn } from '@/lib/utils';

export function useCompanyBrand() {
    return usePage<{ branding: CompanyBranding }>().props.branding;
}

export default function BrandLogo({
    className,
    compact = false,
    surface = 'auto',
    ...props
}: ImgHTMLAttributes<HTMLImageElement> & {
    compact?: boolean;
    surface?: BrandSurface;
}) {
    const brand = useCompanyBrand();
    const { resolvedAppearance } = useAppearance();
    const logo = resolveCompanyLogo(brand, surface, resolvedAppearance);

    return (
        <img
            {...props}
            src={logo.src}
            alt={brand.name}
            onError={(event) => {
                if (event.currentTarget.getAttribute('src') !== logo.fallback) {
                    event.currentTarget.src = logo.fallback;
                }
            }}
            className={cn(
                'inline-block h-10 w-auto max-w-full object-contain',
                compact && 'size-9',
                className,
            )}
        />
    );
}
