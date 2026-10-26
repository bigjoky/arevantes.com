import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';
import {
    sameCommercialHistoryEntry,
} from '../resources/js/lib/mobile/commercial-history.ts';
import {
    commercialDetailUrl,
    commercialListUrl,
} from '../resources/js/lib/mobile/commercial-navigation.ts';

for (const base of ['/opportunities', '/people', '/quotes']) {
    test(`${base}: list context survives detail and contextual return`, () => {
        const filters = {
            search: 'Solar & familia',
            page: '3',
            owner: '8',
            customer: '21',
            view: 'table',
            mobile: 'menu',
            tab: 'history',
        };
        const detail = commercialDetailUrl(base, 42, filters);
        const params = new URL(detail, 'https://local.invalid').searchParams;
        assert.equal(params.get('search'), filters.search);
        assert.equal(params.get('page'), '3');
        assert.equal(params.get('customer'), '21');
        assert.equal(params.has('mobile'), false);
        assert.equal(params.has('tab'), false);
        assert.equal(
            commercialListUrl(detail, base),
            detail.replace(`${base}/42`, base),
        );
    });
}

test('return does not reopen editor commands or route to external destinations', () => {
    assert.equal(
        commercialListUrl(
            'https://outside.invalid/quotes/4?create=1&opportunity=9&search=solar',
            '/quotes',
        ),
        '/quotes?search=solar',
    );
});

// Exercise the actual unchanged editor calculation functions, without importing
// React, rebuilding its engine or invoking any document/PDF endpoint.
const source = ts.createSourceFile(
    'quote-form.tsx',
    readFileSync(
        new URL('../resources/js/pages/quotes/form.tsx', import.meta.url),
        'utf8',
    ),
    ts.ScriptTarget.Latest,
    true,
    ts.ScriptKind.TSX,
);
const names = ['roundMoney', 'lineAmounts', 'calculate'];
const functions = source.statements
    .filter(
        (node) =>
            ts.isFunctionDeclaration(node) && names.includes(node.name?.text),
    )
    .map((node) => node.getText(source));
assert.equal(functions.length, names.length);
const js = ts.transpileModule(
    functions.join('\n') + '\nglobalThis.quoteCalculation = calculate;',
    {
        compilerOptions: {
            target: ts.ScriptTarget.ES2022,
            module: ts.ModuleKind.None,
        },
    },
).outputText;
const sandbox = {};
vm.runInNewContext(js, sandbox);
const calculate = (lines) =>
    JSON.parse(JSON.stringify(sandbox.quoteCalculation(lines)));
const line = (changes = {}) => ({
    quantity: '2',
    unit_price: '100',
    discount_percent: '10',
    tax_percent: '21',
    total_with_tax: '',
    is_selected: true,
    ...changes,
});

test('quote calculation preserves quantity, discount and VAT', () => {
    assert.deepEqual(calculate([line()]), {
        subtotal: 200,
        discount: 20,
        tax: 37.8,
        total: 217.8,
    });
});
test('unselected options do not enter commercial totals', () => {
    assert.deepEqual(
        calculate([line(), line({ is_selected: false, unit_price: '900' })]),
        calculate([line()]),
    );
});
test('inclusive total retains existing rounding and discount reconstruction', () => {
    assert.deepEqual(calculate([line({ total_with_tax: '121' })]), {
        subtotal: 111.11,
        discount: 11.11,
        tax: 21,
        total: 121,
    });
});
test('fully discounted and zero VAT lines preserve existing behavior', () => {
    assert.deepEqual(
        calculate([
            line({ discount_percent: '100' }),
            line({
                quantity: '1',
                unit_price: '20',
                discount_percent: '0',
                tax_percent: '0',
            }),
        ]),
        { subtotal: 220, discount: 200, tax: 0, total: 20 },
    );
});
test('empty quote has zero totals without changing document numbering or state', () => {
    assert.deepEqual(calculate([]), {
        subtotal: 0,
        discount: 0,
        tax: 0,
        total: 0,
    });
});

test('commercial history guard runs before router, delegates when inactive and unregisters', async () => {
    const listeners = [];
    const originalWindow = globalThis.window;
    globalThis.window = {
        addEventListener: (_name, listener) => listeners.push(listener),
    };

    try {
        const {
            installCommercialHistoryProtection,
            registerCommercialHistoryGuard,
        } = await import('../resources/js/lib/mobile/commercial-history.ts');
        installCommercialHistoryProtection();
        installCommercialHistoryProtection();
        assert.equal(listeners.length, 1);
        let routerVisits = 0;
        listeners.push(() => routerVisits++);
        const dispatch = () => {
            const event = {
                cancelBubble: false,
                stopImmediatePropagation() {
                    this.cancelBubble = true;
                },
            };

            for (const listener of listeners) {
                listener(event);

                if (event.cancelBubble) {
                    break;
                }
            }
        };
        dispatch();
        assert.equal(routerVisits, 1);
        const stop = registerCommercialHistoryGuard((event) =>
            event.stopImmediatePropagation(),
        );
        dispatch();
        assert.equal(routerVisits, 1);
        stop();
        stop();

        for (let index = 0; index < 20; index++) {
            const obsolete = registerCommercialHistoryGuard(() => assert.fail('obsolete form guard retained'));
            obsolete();
            obsolete();
        }

        dispatch();
        assert.equal(routerVisits, 2);
    } finally {
        if (originalWindow === undefined) {
            delete globalThis.window;
        } else {
            globalThis.window = originalWindow;
        }
    }
});

// Different history entries must never be mistaken for a screen layered over
// this form: doing so would silently skip the discard decision.
test('encrypted cloned entry matches only the same history payload', () => {
    const entry = { page: new Uint8Array([3, 12, 84, 6]).buffer };
    assert.equal(
        sameCommercialHistoryEntry(entry, structuredClone(entry)),
        true,
    );
    assert.equal(
        sameCommercialHistoryEntry(entry, {
            page: new Uint8Array([3, 12, 84, 7]).buffer,
        }),
        false,
    );
    assert.equal(
        sameCommercialHistoryEntry(entry, {
            page: new Uint8Array([3, 12]).buffer,
        }),
        false,
    );
    assert.equal(sameCommercialHistoryEntry(entry, { page: {} }), false);
});
test('plain entries preserve context and missing entries do not qualify as overlay return', () => {
    const entry = {
        page: {
            url: '/people/create?search=solar&page=3',
            component: 'people/create',
        },
    };
    assert.equal(
        sameCommercialHistoryEntry(entry, structuredClone(entry)),
        true,
    );
    assert.equal(
        sameCommercialHistoryEntry(entry, {
            page: { url: '/quotes', component: 'quotes/index' },
        }),
        false,
    );
    assert.equal(sameCommercialHistoryEntry(null, null), false);
    assert.equal(sameCommercialHistoryEntry({}, {}), false);
});

test('unexpected cyclic history state falls back to the discard guard safely', () => {
    const cyclic = {};
    cyclic.self = cyclic;
    assert.equal(sameCommercialHistoryEntry({page:cyclic}, {page:cyclic}), false);
});
