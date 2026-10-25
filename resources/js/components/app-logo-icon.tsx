import type { ImgHTMLAttributes } from 'react';
import BrandLogo from '@/components/brand-logo';

export default function AppLogoIcon(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return <BrandLogo {...props} />;
}
