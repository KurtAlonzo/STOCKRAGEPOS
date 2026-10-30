import type { SVGAttributes } from 'react';
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 128 128" role="img" aria-label="STOCKRAGE">
            <image href="/images/stockrage-logo.png" width="128" height="128" />
        </svg>
    );
}
