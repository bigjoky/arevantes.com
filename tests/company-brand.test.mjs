import assert from 'node:assert/strict';
import test from 'node:test';
import { resolveCompanyLogo } from '../resources/js/lib/company-brand.ts';

const brand = {
    name: 'AREVANTES',
    logoUrl: '/brand/arevantes-logo.svg',
    darkLogoUrl: '/brand/arevantes-logo-dark.svg',
    canonicalLogoUrl: '/brand/arevantes-logo.svg',
    canonicalDarkLogoUrl: '/brand/arevantes-logo-dark.svg',
    custom: false,
};

test('theme changes select the corresponding supplied asset and fallback', () => {
    assert.deepEqual(resolveCompanyLogo(brand, 'auto', 'light'), { src: brand.logoUrl, fallback: brand.canonicalLogoUrl });
    assert.deepEqual(resolveCompanyLogo(brand, 'auto', 'dark'), { src: brand.darkLogoUrl, fallback: brand.canonicalDarkLogoUrl });
});

test('surface contrast takes precedence over the global theme', () => {
    assert.equal(resolveCompanyLogo(brand, 'dark', 'light').src, brand.darkLogoUrl);
    assert.equal(resolveCompanyLogo(brand, 'light', 'dark').src, brand.logoUrl);
});

test('a configured company resource is retained in both themes', () => {
    const custom = { ...brand, logoUrl: '/company-fiscal/logo?v=custom.svg', darkLogoUrl: '/company-fiscal/logo?v=custom.svg', custom: true };
    assert.equal(resolveCompanyLogo(custom, 'auto', 'dark').src, custom.logoUrl);
    assert.equal(resolveCompanyLogo(custom, 'auto', 'dark').fallback, brand.canonicalDarkLogoUrl);
});
