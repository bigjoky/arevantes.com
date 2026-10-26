import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';
import {
    operationsOrderUrl,
    operationsReturnUrl,
} from '../resources/js/lib/operations/navigation.ts';

for (const context of [
    '/work-orders?q=Panel&status=planned&date_from=2026-10-01&mine=1&page=2',
    '/operations/agenda?view=week&week=2026-10-26',
    '/operations/planning?owner=3&week=2026-10-26',
]) {
    test(`order and tab retain authorized context: ${context}`, () => {
        const link = operationsOrderUrl(42, context, 'reports');
        const url = new URL(link, 'https://local.invalid');
        assert.equal(url.pathname, '/work-orders/42');
        assert.equal(url.searchParams.get('tab'), 'reports');
        assert.equal(
            operationsReturnUrl(url.searchParams.get('return_to')),
            context,
        );
    });
}

test('return URL cannot leave the recognized application lists or execute commands', () => {
    for (const url of [
        'https://outside.invalid/work-orders',
        '//outside.invalid/work-orders',
        '/work-orders/1',
        '/quotes',
        'javascript:alert(1)',
    ]) {
        assert.equal(operationsReturnUrl(url), '/work-orders');
    }

    assert.equal(
        operationsReturnUrl(
            '/work-orders?q=solar&mobile=create&return_to=https://evil.invalid&status=planned',
        ),
        '/work-orders?q=solar&status=planned',
    );
});

const source = readFileSync(
    new URL(
        '../resources/js/hooks/use-operations-form-protection.ts',
        import.meta.url,
    ),
    'utf8',
);
const compiled = ts.transpileModule(source.replace(/^import .*;\n/gm, ''), {
    compilerOptions: {
        module: ts.ModuleKind.CommonJS,
        target: ts.ScriptTarget.ES2022,
    },
}).outputText;

for (const mobile of [true, false]) {
    test(`existing guard reuse, duplicate submit and error retry (${mobile ? 'mobile' : 'desktop'})`, () => {
        let reset = 0;
        let finish = 0;
        let saved = 0;
        const context = {
            exports: {},
            useRef: (v) => ({ current: v }),
            useCommercialFormProtection: () => ({
                mobile,
                beginSubmit: () => true,
                finishSubmit: () => finish++,
                saved: (callback) => {
                    saved++;

                    if (mobile) {
                        reset++;
                    }

                    callback();
                },
            }),
        };
        vm.runInNewContext(compiled, context);
        const draft = {
            isDirty: true,
            processing: false,
            resetAndClearErrors: () => reset++,
        };
        const guard = context.exports.useOperationsFormProtection(() => draft);
        assert.equal(guard.beginSubmit(), true);
        assert.equal(guard.beginSubmit(), false);
        guard.finishSubmit(); // validation error: release latch without clearing fields
        assert.equal(reset, 0);
        assert.equal(finish, 1);
        assert.equal(guard.beginSubmit(), true);
        draft.processing = true;
        assert.equal(guard.beginSubmit(), false);
        guard.saved();
        assert.equal(reset, 1);
        assert.equal(saved, 1);
    });
}

test('blank travel distance uses the existing non-null zero contract without changing notes', () => {
    const file = ts.createSourceFile(
        'execution.tsx',
        readFileSync(
            new URL(
                '../resources/js/components/operations/mobile-execution.tsx',
                import.meta.url,
            ),
            'utf8',
        ),
        ts.ScriptTarget.Latest,
        true,
        ts.ScriptKind.TSX,
    );
    let transform;
    const visit = (node) => {
        if (
            ts.isCallExpression(node) &&
            node.expression.getText(file) === 'form.transform'
        ) {
            transform = node.arguments[0].getText(file);
        }

        ts.forEachChild(node, visit);
    };
    visit(file);
    assert.ok(transform);
    const context = {};
    vm.runInNewContext(
        ts.transpileModule(`globalThis.payload = ${transform};`, {
            compilerOptions: { target: ts.ScriptTarget.ES2022 },
        }).outputText,
        context,
    );
    assert.equal(
        context.payload({ notes: 'Conservar', travel_km: '' }).travel_km,
        '0',
    );
    assert.equal(
        context.payload({ notes: 'Conservar', travel_km: '' }).notes,
        'Conservar',
    );
    assert.equal(
        context.payload({ notes: '', travel_km: '12.5' }).travel_km,
        '12.5',
    );
    assert.equal(
        context.payload({ notes: '', travel_km: '-1' }).travel_km,
        '-1',
    ); // server must reject, never silently clamp
});
