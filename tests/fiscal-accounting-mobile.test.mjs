import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const accounting = read('../resources/js/pages/accounting/workspace.tsx');
const detail = read('../resources/js/pages/accounting/entry.tsx');
const fiscal = read('../resources/js/pages/fiscal/workspace.tsx');

test('mobile accounting retains both sides and account balances', () => {
    assert.match(accounting, /Debe:\s*\{money\(e\.debit\)\}/);
    assert.match(accounting, /Haber:\s*\{money\(e\.credit\)\}/);
    assert.match(accounting, /money\(\s*l\.running\s*,?\s*\)/);
    assert.match(accounting, /money\(\s*a\.opening\s*,?\s*\)/);
    assert.match(accounting, /money\(\s*a\.net\s*,?\s*\)/);
    assert.match(accounting, /accounting-mobile-list/);
});

test('journal draft uses existing navigation and duplicate submission protection', () => {
    assert.match(detail, /useOperationsFormProtection/);
    assert.match(detail, /protection\.beginSubmit\(\)/);
    assert.match(detail, /protection\.requestClose/);
    assert.match(detail, /ConfirmSubmit/);
});

test('mobile fiscal records retain source base, tax, retention and receipt status', () => {
    assert.match(fiscal, /Base:\s*\{money\(r\.base\)\}/);
    assert.match(fiscal, /money\(r\.vat\)/);
    assert.match(fiscal, /money\(r\.withholding\)/);
    assert.match(fiscal, /Sin evidencia verificada/);
});

test('account management keeps desktop rows and mobile actions', () => {
    const source = read('../resources/js/pages/accounting/accounts.tsx');
    assert.match(source, /TableViewport className="hidden lg:block"/);
    assert.match(source, /grid gap-3 lg:hidden/);
    assert.match(source, /ProjectDialog/);
    assert.match(source, /ConfirmSubmit/);
});

test('303 source evidence retains all amounts and source actions on mobile', () => {
    const source = read('../resources/js/pages/tax/model303.tsx');
    const evidence = source.slice(source.indexOf('function EvidenceList('));

    for (const term of [
        'row.base',
        'row.vat',
        'row.deductible_vat',
        'row.number',
        'row.party',
        'row.source_id',
    ]) {
        assert.ok(evidence.includes(term), `Missing evidence field ${term}`);
    }

    assert.match(evidence, /lg:hidden/);
    assert.match(evidence, /lg:table-row/);
});

test('accounting return preserves listing context and rejects external destinations', async () => {
    const { accountingReturn, accountingDetail } =
        await import('../resources/js/lib/accounting/mobile-navigation.ts');
    const origin =
        '/accounting?area=reports&view=ledger&year=2026&account=430&from=2026-01-01&to=2026-12-31&page=2';
    const detail = accountingDetail('/accounting/entries/12', origin);
    const returned = new URL(
        detail,
        'https://arevantes.invalid',
    ).searchParams.get('return_to');
    assert.equal(accountingReturn(returned), origin);

    for (const unsafe of [
        'https://evil.example/',
        '//evil.example/',
        '/accounting/entries/1/reverse',
        '/accounting\\@evil.example',
    ]) {
        assert.equal(accountingReturn(unsafe), null);
    }

    assert.equal(
        accountingReturn(
            '/accounting?area=entries&return_to=https://evil.example',
        ),
        '/accounting?area=entries',
    );
});
