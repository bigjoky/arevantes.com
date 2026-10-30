import assert from 'node:assert/strict';
import { Buffer } from 'node:buffer';
import { readFile } from 'node:fs/promises';
import ts from 'typescript';
const source = await readFile(
    new URL('../../resources/js/lib/workspace-navigation.ts', import.meta.url),
    'utf8',
);
const { outputText } = ts.transpileModule(source, {
    compilerOptions: {
        module: ts.ModuleKind.ESNext,
        target: ts.ScriptTarget.ES2022,
    },
});
const { activeNavigation, navigationScore, contextualHref } = await import(
    `data:text/javascript;base64,${Buffer.from(outputText).toString('base64')}`
);
const items = [
    {
        id: 'commercial',
        href: '/commercial',
        navigable: true,
        children: [
            { id: 'commercial.crm', href: '/opportunities', links: [] },
            { id: 'commercial.customers', href: '/people', links: [] },
            { id: 'commercial.quotes', href: '/quotes', links: [] },
        ],
    },
    {
        id: 'accounting',
        href: '/accounting?area=overview',
        navigable: true,
        children: [
            {
                id: 'accounting.entries',
                href: '/workspace/modules/accounting/entries',
                links: [{ href: '/accounting?view=journal&area=entries' }],
            },
            {
                id: 'accounting.reports',
                href: '/workspace/modules/accounting/reports',
                links: [{ href: '/accounting?view=journal&area=reports' }],
            },
        ],
    },
];
assert.equal(
    activeNavigation(items, '/people/42/edit'),
    'commercial.customers',
);
assert.equal(activeNavigation(items, '/peoples'), undefined);
assert.equal(
    activeNavigation(items, '/workspace/modules/commercial/customers'),
    'commercial.customers',
);
assert.equal(
    activeNavigation(items, '/accounting?view=journal&area=reports&year=2025'),
    'accounting.reports',
);
assert.equal(
    activeNavigation(items, '/accounting?view=journal&entry=42'),
    'accounting.entries',
);
assert.equal(activeNavigation([], '/accounting?view=journal'), undefined);
assert.equal(navigationScore('/people', '/people?year=2026'), 7);
assert.equal(
    navigationScore('/accounting?view=ledger', '/accounting?view=journal'),
    -1,
);
assert.equal(
    contextualHref('/accounting?view=ledger#lines', 2025),
    '/accounting?view=ledger&year=2025#lines',
);
assert.equal(
    contextualHref('/workspace/modules/accounting/reports', 2025),
    '/workspace/modules/accounting/reports?year=2025',
);
assert.equal(contextualHref('/people', 2025), '/people');
assert.equal(activeNavigation(items, '/commercial?period=month'), 'commercial');
assert.equal(
    activeNavigation(items, '/opportunities?view=kanban'),
    'commercial.crm',
);
assert.equal(activeNavigation(items, '/opportunities/42'), 'commercial.crm');
assert.equal(activeNavigation(items, '/quotes/42'), 'commercial.quotes');
assert.equal(
    activeNavigation(
        items.map((item) => ({ ...item, navigable: false })),
        '/commercial',
    ),
    undefined,
);

const fiscalItems = [
    {
        id: 'tax',
        href: '/fiscal',
        navigable: true,
        children: [
            {
                id: 'tax.taxes',
                href: '/fiscal/impuestos',
                links: [{ href: '/tax' }],
            },
            {
                id: 'tax.models',
                href: '/fiscal/modelos',
                links: [
                    { href: '/tax/models/303' },
                    { href: '/corporate-tax' },
                ],
            },
            { id: 'tax.periods', href: '/fiscal/periodos', links: [] },
        ],
    },
];
assert.equal(activeNavigation(fiscalItems, '/fiscal'), 'tax');
assert.equal(
    activeNavigation(fiscalItems, '/fiscal/impuestos?year=2025'),
    'tax.taxes',
);
assert.equal(
    activeNavigation(fiscalItems, '/fiscal/declaraciones/303/4'),
    'tax.models',
);
assert.equal(
    activeNavigation(fiscalItems, '/tax/models/303?year=2026'),
    'tax.models',
);
assert.equal(activeNavigation([], '/fiscal/declaraciones/303/4'), undefined);
assert.equal(
    contextualHref('/fiscal/periodos?tab=vat', 2025),
    '/fiscal/periodos?tab=vat&year=2025',
);

assert.equal(
    activeNavigation(items, '/accounting/entries/4'),
    'accounting.entries',
);
assert.equal(activeNavigation([], '/accounting/entries/4'), undefined);
assert.equal(
    contextualHref('/accounting/accounts?group=4', 2025),
    '/accounting/accounts?group=4&year=2025',
);
const companyItems = [
    {
        id: 'company',
        href: '/company',
        navigable: true,
        children: [
            { id: 'company.staff', href: '/company/staff' },
            { id: 'company.documents', href: '/company/documents' },
            { id: 'company.settings', href: '/company/settings' },
        ],
    },
];
assert.equal(activeNavigation(companyItems, '/company'), 'company');
assert.equal(
    activeNavigation(companyItems, '/company/staff/5?tab=history'),
    'company.staff',
);
assert.equal(
    activeNavigation(companyItems, '/company/documents/5?page=2'),
    'company.documents',
);
assert.equal(
    activeNavigation(companyItems, '/company/settings?tab=security'),
    'company.settings',
);
assert.equal(activeNavigation([], '/company/staff/5'), undefined);

assert.equal(activeNavigation(items, '/accounting?area=overview&year=2026'), 'accounting');
assert.equal(activeNavigation(items, '/accounting?view=mappings'), 'accounting');
assert.equal(activeNavigation(items, '/accounting?view=mappings&area=overview'), 'accounting');
assert.equal(activeNavigation(items, '/accounting/entries/42'), 'accounting.entries');
assert.equal(activeNavigation(items, '/accounting?view=journal&area=entries'), 'accounting.entries');

console.log('Workspace navigation assertions passed.');
