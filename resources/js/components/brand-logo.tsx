import { usePage } from '@inertiajs/react';
import type { ImgHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type Branding = {
    name: string;
    logoUrl: string;
    canonicalLogoUrl: string;
    custom: boolean;
};

export function useCompanyBrand() {
    return usePage<{ branding: Branding }>().props.branding;
}

export default function BrandLogo({
    className,
    compact = false,
    ...props
}: ImgHTMLAttributes<HTMLImageElement> & { compact?: boolean }) {
    const brand = useCompanyBrand();

    return (
        <img
            {...props}
            src={brand.logoUrl}
            alt={brand.name}
            onError={(event) => {
                if (
                    event.currentTarget.getAttribute('src') !==
                    brand.canonicalLogoUrl
                ) {
                    event.currentTarget.src = brand.canonicalLogoUrl;
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
