import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';
import {
    financeEntity,
    financeReturn,
    financeView,
} from '../resources/js/lib/finance/navigation.ts';

for (const origin of [
    '/billing/payments?tab=due&kind=receipt&page=2',
    '/finance?area=treasury&account=3&year=2026',
    '/banking?account=4&tab=reconciliation&search=QA',
]) {
    test(`financial return retains authorized context ${origin}`, () => {
        const entity = new URL(
            financeEntity('/sales/5', origin),
            'https://local.invalid',
        );
        assert.equal(
            financeReturn(entity.searchParams.get('return_to')),
            origin,
        );
    });
}

test('financial return strips overlays and rejects external, private and mutation paths', () => {
    for (const href of [
        'https://evil.invalid',
        '//evil.invalid',
        '/\\evil.invalid',
        '/admin',
        '/banking/transactions/1/reconcile',
        '/sales/1/payments',
    ]) {
        assert.equal(financeReturn(href), null);
    }

    assert.equal(
        financeReturn(
            '/billing/payments?tab=due&page=2&mobile=menu&return_to=https://evil.invalid',
        ),
        '/billing/payments?tab=due&page=2',
    );
});
test('financial confirmation does not submit until approved and never grants a later submission', () => {
    const source = readFileSync(
        'resources/js/components/finance/protected-form.tsx',
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
    const refs = [];
    const jsx = (type, props) => ({ type, props });
    vm.runInNewContext(compiled, {
        exports,
        require: () => ({ jsx, jsxs: jsx }),
        useRef: (current) => {
            const ref = { current };
            refs.push(ref);

            return ref;
        },
        useState: () => [false, () => {}],
        ProjectForm: 'ProjectForm',
        Button: 'Button',
        Dialog: 'Dialog',
        DialogContent: 'Content',
        DialogHeader: 'Header',
        DialogTitle: 'Title',
        DialogDescription: 'Description',
    });
    let posts = 0;
    let checks = 0;
    const tree = exports.default({
        action: '/finance/forecast-items',
        onBefore: () => {
            checks++;
        },
    });
    const find = (node, type) => {
        if (!node || typeof node !== 'object') {
return [];
}

        const children = Array.isArray(node.props?.children)
            ? node.props.children
            : [node.props?.children];

        return [
            ...(node.type === type ? [node] : []),
            ...children.flatMap((child) => find(child, type)),
        ];
    };
    const form = find(tree, 'ProjectForm')[0];
    refs[0].current = {
        querySelector: () => ({
            requestSubmit: () => {
                if (form.props.onBefore({}) !== false) {
posts++;
}
            },
        }),
    };
    assert.equal(form.props.onBefore({}), false);
    assert.equal(checks, 0);
    const buttons = find(tree, 'Button');
    buttons[0].props.onClick();
    assert.equal(posts, 0);
    buttons[1].props.onClick();
    assert.equal(posts, 1);
    assert.equal(checks, 1);
    assert.equal(form.props.onBefore({}), false);
    assert.equal(posts, 1);
});

test('financial tabs preserve account and exercise while resetting pagination', () => {
    assert.equal(
        financeView('/finance?area=treasury&account=3&year=2026&page=4', {
            tab: 'position',
        }),
        '/finance?area=treasury&account=3&year=2026&tab=position',
    );
    assert.equal(
        financeView('/billing/payments?kind=receipt&from=2026-01-01&page=3', {
            tab: 'due',
        }),
        '/billing/payments?kind=receipt&from=2026-01-01&tab=due',
    );
});
