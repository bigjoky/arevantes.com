import type { ComponentProps } from 'react';
import BrandLogo from '@/components/brand-logo';

export default function AppLogoIcon(props: ComponentProps<typeof BrandLogo>) {
    return <BrandLogo {...props} />;
}
