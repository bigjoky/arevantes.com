import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';
import {
    operationsOrderUrl,
    operationsReturnUrl,
} from '../resources/js/lib/operations/navigation.ts';
import {
    projectEntityUrl,
    projectReturnUrl,
} from '../resources/js/lib/projects/navigation.ts';

for (const context of [
    '/execution/projects?search=solar&status=in_progress&page=3',
    '/installations?service_type=electrical&technician=2',
    '/after-sales?view=installations&project=3',
    '/execution/projects/3?tab=planning&section_page=2',
]) {
    test(`projects preserve recognized context: ${context}`, () => {
        const target = new URL(
            projectEntityUrl('/installations/3', context),
            'https://test.invalid',
        );
        assert.equal(target.pathname, '/installations/3');
        assert.equal(
            projectReturnUrl(target.searchParams.get('return_to')),
            context,
        );
    });
}

for (const value of [
    'https://evil.invalid/',
    '//evil.invalid/',
    '/\\evil.invalid',
    '/installations/3/edit',
    '/after-sales/tickets/1',
    'javascript:alert(1)',
]) {
    test(`reject unsafe return context ${value}`, () =>
        assert.equal(projectReturnUrl(value), '/execution/projects'));
}

test('order can return to the originating project tab or ticket scope without overlay replay', () => {
    const value =
        '/execution/projects/3?tab=planning&mobile=menu&section_page=2&return_to=https://evil.invalid';
    const target = new URL(
        operationsOrderUrl(7, value, 'reports'),
        'https://test.invalid',
    );
    assert.equal(
        operationsReturnUrl(target.searchParams.get('return_to')),
        '/execution/projects/3?tab=planning&section_page=2',
    );
    assert.equal(
        operationsReturnUrl('/after-sales?installation=3&mobile=create'),
        '/after-sales?installation=3',
    );
});

// Exercise the real scoped Dialog adapter with React hooks stubbed at its boundary.
// No implementation-shaped copy of the close orchestration is used in the test.

test('a dialog closes only after its mounted form authorizes the close', () => {
    const source = readFileSync(
        new URL(
            '../resources/js/components/projects/protected-dialog.tsx',
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
    const state = [];
    const jsx = (type, props) => ({ type, props });
    vm.runInNewContext(compiled, {
        exports,
        require: () => ({ jsx, jsxs: jsx }),
        Dialog: 'Dialog',
        DialogContent: 'Content',
        createContext: () => ({ Provider: 'Provider' }),
        useCallback: (fn) => fn,
        useRef: (current) => ({ current }),
        useState: () => [true, (value) => state.push(value)],
    });
    const tree = exports.default({});
    let finish;
    const unregister = tree.props.value((close) => {
        finish = close;
    });
    tree.props.children.props.onOpenChange(false);
    assert.deepEqual(state, []);
    finish();
    assert.deepEqual(state, [false]);
    unregister();
    tree.props.children.props.onOpenChange(true);
    tree.props.children.props.onOpenChange(false);
    assert.deepEqual(state, [false, true, false]);
});
