import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';

const parse = (path) =>
    ts.createSourceFile(
        path,
        readFileSync(new URL(`../${path}`, import.meta.url), 'utf8'),
        ts.ScriptTarget.Latest,
        true,
        ts.ScriptKind.TSX,
    );
const layout = parse('resources/js/layouts/app/app-sidebar-layout.tsx');
const mobile = parse('resources/js/components/mobile/mobile-experience.tsx');
const collect = (root, predicate) => {
    const found = [];
    const visit = (node) => {
        if (predicate(node)) {
            found.push(node);
        }

        ts.forEachChild(node, visit);
    };
    visit(root);

    return found;
};
const content = (root) =>
    collect(
        root,
        (node) =>
            ts.isJsxExpression(node) &&
            node.expression?.getText() === 'children',
    );
const assertStablePath = (node, until) => {
    for (
        let parent = node.parent;
        parent && parent !== until;
        parent = parent.parent
    ) {
        assert.equal(
            ts.isConditionalExpression(parent),
            false,
            'page identity must not depend on a responsive branch',
        );
        assert.equal(
            ts.isBinaryExpression(parent),
            false,
            'page must not be conditionally duplicated or removed',
        );
    }
};

test('Inertia page has one unconditional MobileExperience parent at every breakpoint', () => {
    const children = content(layout);
    assert.equal(
        children.length,
        1,
        'exactly one page mount, never two hidden copies',
    );
    assertStablePath(children[0], layout);
    assert.equal(
        children[0].parent.openingElement.tagName.getText(layout),
        'MobileExperience',
    );
    const opening = children[0].parent.openingElement.getText(layout);
    assert.match(opening, /active=\{isMobile\}/);
    assert.doesNotMatch(
        opening,
        /key=/,
        'responsive keys would destroy page state',
    );
});

test('page host identity is stable while shell presentation changes', () => {
    const fn = mobile.statements.find(
        (node) =>
            ts.isFunctionDeclaration(node) &&
            node.name?.text === 'MobileExperience',
    );
    const children = content(fn);
    assert.equal(children.length, 1);
    assertStablePath(children[0], fn);
    const host = children[0].parent.openingElement.getText(mobile);
    assert.match(host, /key="inertia-page-content"/);
    assert.match(host, /data-inertia-page-container/);
    assert.match(host, /active \?/);
    assert.match(host, /hidden=\{Boolean\(active/);
    assert.match(host, /inert=\{Boolean\(active/);
});

test('task sheet retains its component lifetime when desktop chrome replaces mobile chrome', () => {
    const fn = mobile.statements.find(
        (node) =>
            ts.isFunctionDeclaration(node) &&
            node.name?.text === 'MobileExperience',
    );
    const sheets = collect(
        fn,
        (node) =>
            ts.isJsxElement(node) &&
            node.openingElement.tagName.getText(mobile) === 'Sheet',
    );
    assert.equal(sheets.length, 1);
    assertStablePath(sheets[0], fn);
    assert.match(
        sheets[0].openingElement.getText(mobile),
        /open=\{createOpen \|\| view === 'create'\}/,
    );
    assert.doesNotMatch(
        sheets[0].openingElement.getText(mobile),
        /active|key=/,
    );
});

test('focus returns to a surviving opener or the replacement shell control', () => {
    const fn = mobile.statements.find(
        (node) =>
            ts.isFunctionDeclaration(node) &&
            node.name?.text === 'focusShellOpener',
    );
    assert.ok(fn);
    const js = ts.transpileModule(
        fn.getText(mobile) + '\nglobalThis.restore = focusShellOpener;',
        { compilerOptions: { target: ts.ScriptTarget.ES2022 } },
    ).outputText;
    let originalFocus = 0;
    let replacementFocus = 0;
    const sandbox = {
        document: {
            querySelector(selector) {
                assert.match(selector, /mobile-tabbar/);
                assert.match(selector, /sidebar-trigger/);

                return {
                    focus() {
                        replacementFocus++;
                    },
                };
            },
        },
    };
    vm.runInNewContext(js, sandbox);
    sandbox.restore({
        isConnected: true,
        focus() {
            originalFocus++;
        },
    });
    assert.equal(originalFocus, 1);
    assert.equal(replacementFocus, 0);
    sandbox.restore({
        isConnected: false,
        focus() {
            assert.fail('detached opener must not receive focus');
        },
    });
    assert.equal(replacementFocus, 1);
    sandbox.restore(null);
    assert.equal(replacementFocus, 2);
});

test('desktop history Forward can reactivate the existing task guard and cleanup its listeners', () => {
    const effects = collect(
        mobile,
        (node) =>
            ts.isCallExpression(node) &&
            node.expression.getText(mobile) === 'useEffect',
    );
    const effect = effects.find((node) =>
        node.arguments[0]?.getText(mobile).includes('const stopNavigate'),
    );
    assert.ok(effect);
    const source = `globalThis.install = ${effect.arguments[0].getText(mobile)};`;
    const js = ts.transpileModule(source, {
        compilerOptions: { target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const listeners = new Map();
    const browserListeners = new Set();
    let open = false;
    const sandbox = {
        active: false,
        createActive: { current: false },
        traversing: { current: false },
        taskForm: { current: null },
        taskGuard: { busy: () => false },
        finishTaskClose: () => {},
        requestTaskClose: () => {},
        setCreateOpen: (value) => {
            open = value;
        },
        setCloseNotice: () => {},
        mobileView: (url) =>
            new URL(url, 'https://local.invalid').searchParams.get('mobile'),
        router: {
            on(name, callback) {
                listeners.set(name, callback);

                return () => listeners.delete(name);
            },
        },
        window: {
            addEventListener(name) {
                browserListeners.add(name);
            },
            removeEventListener(name) {
                browserListeners.delete(name);
            },
        },
    };
    vm.runInNewContext(js, sandbox);
    const cleanup = sandbox.install();
    assert.equal(typeof listeners.get('navigate'), 'function');
    listeners.get('navigate')({
        detail: { page: { url: '/opportunities?mobile=create' } },
    });
    assert.equal(sandbox.createActive.current, true);
    assert.equal(open, true);
    cleanup();
    assert.equal(listeners.size, 0);
    assert.equal(browserListeners.size, 0);
});
