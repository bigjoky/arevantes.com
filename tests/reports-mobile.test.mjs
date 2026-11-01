import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const analytics = readFileSync(
    new URL('../resources/js/pages/analytics/index.tsx', import.meta.url),
    'utf8',
);
const supply = readFileSync(
    new URL(
        '../resources/js/pages/purchases/analytics/index.tsx',
        import.meta.url,
    ),
    'utf8',
);

test('mobile report details preserve all fields and source links alongside desktop tables', () => {
    const section = analytics.slice(
        analytics.indexOf('function Drilldown('),
        analytics.indexOf('function Dictionary('),
    );

    for (const value of [
        'row.reference',
        'row.url',
        'row.date',
        'row.project',
        'row.status',
        'row.value',
    ]) {
        assert.ok(section.includes(value), value);
    }

    assert.match(section, /divide-y lg:hidden/);
    assert.match(section, /hidden overflow-x-auto lg:block/);
    assert.match(section, /No hay registros para estos filtros/);
});

test('mobile purchase commitments retain status, supplier, date, amount and order link', () => {
    for (const value of [
        'commitment.number',
        'commitment.id',
        'commitment.supplier',
        'commitment.status',
        'commitment.eta',
        'commitment.amount',
    ]) {
        assert.ok(supply.includes(value), value);
    }

    assert.match(supply, /grid gap-3 lg:hidden/);
    assert.match(supply, /hidden overflow-x-auto lg:block/);
});

test('filter submission prevents concurrent requests and releases the lock', () => {
    assert.match(analytics, /if \(filterRequest\.current\)\s*\{\s*return;/);
    assert.match(analytics, /onFinish: \(\) => \{/);
    assert.match(analytics, /filterRequest\.current = false/);
});

test('details and exports use the filters applied to the displayed report', () => {
    assert.match(analytics, /exportUrl\('pdf', props\.filters\)/);
    assert.match(analytics, /exportUrl\('excel', props\.filters\)/);
    assert.match(analytics, /\.\.\.props\.filters, metric: key/);
});
