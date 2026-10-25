export type CompanyBranding = {
    name: string;
    logoUrl: string;
    darkLogoUrl: string;
    canonicalLogoUrl: string;
    canonicalDarkLogoUrl: string;
    custom: boolean;
};

export type BrandSurface = 'auto' | 'light' | 'dark';

export function resolveCompanyLogo(
    brand: CompanyBranding,
    surface: BrandSurface,
    appearance: 'light' | 'dark',
) {
    const dark = (surface === 'auto' ? appearance : surface) === 'dark';

    return dark
        ? { src: brand.darkLogoUrl, fallback: brand.canonicalDarkLogoUrl }
        : { src: brand.logoUrl, fallback: brand.canonicalLogoUrl };
}
