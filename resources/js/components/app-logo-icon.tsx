import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="140 300 165 190"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path d="M244.22,307l54.9,176.53h-32.99l-13.35-44.83h-63.46l-13.35,44.83h-31.73l54.65-176.53h45.33ZM197.13,412.27h47.85l-11.08-37.77c-4.78-17.12-10.07-34.75-12.09-45.83h-1.01c-2.27,10.58-7.56,29.46-12.09,45.08l-11.58,38.53Z" />
        </svg>
    );
}
