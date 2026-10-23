import assert from 'node:assert/strict';
import { Buffer } from 'node:buffer';
import { readFile } from 'node:fs/promises';
import ts from 'typescript';
const source = await readFile(
    new URL(
        '../../resources/js/components/customers/types.ts',
        import.meta.url,
    ),
    'utf8',
);
const { outputText } = ts.transpileModule(source, {
    compilerOptions: {
        module: ts.ModuleKind.ESNext,
        target: ts.ScriptTarget.ES2022,
    },
});
const { customerUrl, customerTypes, customerStatuses, money, date } =
    await import(
        `data:text/javascript;base64,${Buffer.from(outputText).toString('base64')}`
    );
const filters = {
    search: 'Ana & García',
    province_state: 'Córdoba',
    page: '3',
    sort: 'name',
    direction: 'asc',
};
const detail = new URL(
    customerUrl(filters, 42, 'quotes'),
    'https://example.test',
);
assert.equal(detail.pathname, '/people/42');
assert.equal(detail.searchParams.get('search'), 'Ana & García');
assert.equal(detail.searchParams.get('page'), '3');
assert.equal(detail.searchParams.get('tab'), 'quotes');
assert.equal(detail.searchParams.get('province_state'), 'Córdoba');
const back = new URL(customerUrl(filters), 'https://example.test');
assert.equal(back.pathname, '/people');
assert.equal(back.searchParams.get('search'), 'Ana & García');
assert.equal(back.searchParams.get('page'), '3');
assert.equal(back.searchParams.has('tab'), false);
assert.equal(customerUrl({}, 42, 'summary'), '/people/42');
assert.equal(customerUrl({ search: '', page: '' }), '/people');
assert.equal(customerTypes.business, 'Empresa');
assert.equal(customerStatuses.lead, 'Potencial');
assert.match(money(12345.56), /12\.345,56/);
assert.equal(date(null), '—');
console.log('15 customer navigation and formatting assertions passed.');
