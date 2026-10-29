import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';
import { operationsReturnUrl } from '../resources/js/lib/operations/navigation.ts';
import { projectReturnUrl } from '../resources/js/lib/projects/navigation.ts';
import {
    supplyEntityUrl,
    supplyReturnUrl,
    supplyViewUrl,
} from '../resources/js/lib/supply/navigation.ts';

for (const context of [
    '/inventory?view=reservations&warehouse=2&installation=9&search=QA&page=2',
    '/purchases?status=partially_received&supplier=3',
    '/catalog?search=panel&type=product',
]) {
    test(`supply detail retains safe context ${context}`, () => {
        const link = new URL(
            supplyEntityUrl('/catalog/4', context),
            'https://local.invalid',
        );
        assert.equal(
            supplyReturnUrl(link.searchParams.get('return_to')),
            context,
        );
    });
}

test('inventory tabs retain warehouse and installation but reset pagination', () => {
    assert.equal(
        supplyViewUrl(
            '/inventory?view=stock&warehouse=3&installation=8&page=2',
            'reservations',
        ),
        '/inventory?view=reservations&warehouse=3&installation=8',
    );
    assert.equal(
        operationsReturnUrl(
            '/inventory?view=reservations&warehouse=3&installation=8&mobile=menu',
        ),
        '/inventory?view=reservations&warehouse=3&installation=8',
    );
    assert.equal(
        projectReturnUrl('/inventory?view=needs&installation=8'),
        '/inventory?view=needs&installation=8',
    );
});
test('unsafe return addresses and overlays cannot escape supply navigation', () => {
    for (const path of [
        'https://evil.invalid',
        '//evil.invalid',
        '/\\evil.invalid',
        '/admin',
        '/inventory/transport/create',
    ]) {
        assert.equal(supplyReturnUrl(path), '/supply');
    }

    assert.equal(
        supplyReturnUrl(
            '/inventory?view=stock&mobile=create&return_to=https://evil.invalid',
        ),
        '/inventory?view=stock',
    );
});
test('one confirmation authorizes one invocation, even if clicked twice before render', () => {
    const source = readFileSync(
        new URL(
            '../resources/js/components/supply/confirm-operation.tsx',
            import.meta.url,
        ),
        'utf8',
    );
    const compiled = ts.transpileModule(
        source.replace(/^import[\s\S]*?;\n/gm, ''),
        {
            compilerOptions: {
                module: ts.ModuleKind.CommonJS,
                target: ts.ScriptTarget.ES2022,
                jsx: ts.JsxEmit.ReactJSX,
            },
        },
    ).outputText;
    const exports = {};
    const jsx = (type, props) => ({ type, props });
    vm.runInNewContext(compiled, {
        exports,
        require: () => ({ jsx, jsxs: jsx }),
        useRef: (current) => ({ current }),
        useState: () => [false, () => {}],
        Button: 'Button',
        Dialog: 'Dialog',
        DialogContent: 'Content',
        DialogHeader: 'Header',
        DialogTitle: 'Title',
        DialogDescription: 'Description',
    });
    const hook = exports.useSupplyConfirmation();
    let writes = 0;
    hook.confirm(() => {
        writes++;
    });
    const buttons = hook.dialog.props.children.props.children.filter(
        (node) => node.type === 'Button',
    );
    buttons[1].props.onClick();
    buttons[1].props.onClick();
    assert.equal(writes, 1);
    hook.confirm(() => {
        writes++;
    });
    buttons[0].props.onClick();
    buttons[1].props.onClick();
    assert.equal(writes, 1);
});

test('inventory keeps a real installation origin when switching its tab', () => {
    const result = new URL(
        supplyViewUrl(
            '/inventory?view=needs&installation=8&return_to=%2Finstallations%2F8',
            'reservations',
        ),
        'https://local.invalid',
    );
    assert.equal(result.searchParams.get('return_to'), '/installations/8');
    assert.equal(result.searchParams.get('installation'), '8');
});
