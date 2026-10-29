import assert from 'node:assert/strict';
import { Buffer } from 'node:buffer';
import fs from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';

const catalog = JSON.parse(
    fs.readFileSync('resources/js/i18n/catalog.json', 'utf8'),
);
const review = JSON.parse(
    fs.readFileSync('docs/deploy-02-i18n-review.json', 'utf8'),
);
const exclusions = JSON.parse(
    fs.readFileSync('scripts/i18n/reviewed-exclusions.json', 'utf8'),
);
const source = fs
    .readFileSync('resources/js/i18n/index.ts', 'utf8')
    .replace(
        "import catalog from '@/i18n/catalog.json';",
        `const catalog = ${JSON.stringify(catalog)};`,
    );
const compiled = ts.transpileModule(source, {
    compilerOptions: {
        module: ts.ModuleKind.ESNext,
        target: ts.ScriptTarget.ES2022,
    },
}).outputText;
const { translate } = await import(
    `data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`
);

test('all reviewed visible strings have actual Spanish and English translations', () => {
    const visible = review.items.filter((item) => item.en);
    assert.equal(visible.length, 201);
    const mismatches = [];

    for (const item of visible) {
        assert.deepEqual(catalog[item.es], { en: item.en, es: item.es });

        if (
            translate(item.es, 'en') !== item.en ||
            translate(item.es, 'es') !== item.es
        ) {
            mismatches.push({
                key: item.es,
                actual: translate(item.es, 'en'),
                expected: item.en,
            });
        }
    }

    assert.deepEqual(mismatches, []);
});

// Explicit review boundary: adding an exclusion requires reviewing its semantics here,
// rather than merely increasing a total. Original review: 498a2b4; banking: d77adcc.
const reviewedContract = {
    'resources/js/components/project-journey.tsx': [
        'set_required_tasks',
        'close',
    ],
    'resources/js/components/public/site-header.tsx': [
        '/empresa',
        '/proceso',
        '/contacto',
    ],
    'resources/js/pages/public/contact.tsx': ['name'],
    'resources/js/pages/public/page.tsx': [
        'servicios',
        'empresa',
        'proceso',
        'redes',
    ],
    'resources/js/components/banking/reconciliation-workspace.tsx': [
        'no_erp_operation',
        'no_bank_movement',
        'amount_difference',
        'reference_difference',
        'possible_duplicate',
        'voided_operation',
        'already_reconciled',
        'closed_period',
        'bank_fee',
        'accounted',
        'bank_match_confirmed',
        'bank_match_corrected',
    ],
};
const contractKeys = Object.entries(reviewedContract)
    .flatMap(([file, values]) => values.map((value) => `${file}:${value}`))
    .sort();
const sourceFile = (file) =>
    ts.createSourceFile(
        file,
        fs.readFileSync(file, 'utf8'),
        ts.ScriptTarget.Latest,
        true,
        ts.ScriptKind.TSX,
    );
const propertyName = (node) => node.name?.getText().replace(/^['"]|['"]$/g, '');

function isTechnicalLiteral(node) {
    const parent = node.parent;

    if (ts.isJsxAttribute(parent)) {
        return ['value', 'href', 'name', 'autoComplete'].includes(
            parent.name.getText(),
        );
    }

    if (ts.isPropertyAssignment(parent)) {
        return ['action', 'category', 'name', 'autoComplete'].includes(
            propertyName(parent),
        );
    }

    if (ts.isBinaryExpression(parent)) {
        return [
            ts.SyntaxKind.EqualsEqualsEqualsToken,
            ts.SyntaxKind.ExclamationEqualsEqualsToken,
        ].includes(parent.operatorToken.kind);
    }

    if (ts.isConditionalExpression(parent)) {
        return (
            ts.isPropertyAssignment(parent.parent) &&
            propertyName(parent.parent) === 'action'
        );
    }

    if (ts.isArrayLiteralExpression(parent)) {
        const owner = parent.parent;

        // URL half of a [translated label, route] navigation tuple.
        if (
            parent.elements.length === 2 &&
            parent.elements[1] === node &&
            node.text.startsWith('/')
        ) {
            return true;
        }

        // Enum membership tests do not render their literals.
        return (
            ts.isPropertyAccessExpression(owner) &&
            owner.name.text === 'includes' &&
            ts.isCallExpression(owner.parent) &&
            owner.parent.expression === owner
        );
    }

    return false;
}

function validateExclusions(
    entries,
    loadSource = sourceFile,
    translations = catalog,
) {
    const keys = entries.map(({ file, value }) => `${file}:${value}`);
    assert.equal(
        new Set(keys).size,
        keys.length,
        'Duplicate exclusions are forbidden',
    );
    assert.deepEqual(
        [...keys].sort(),
        contractKeys,
        'Exclusions must match the individually reviewed file/value contract',
    );

    for (const item of entries) {
        assert.deepEqual(Object.keys(item).sort(), ['file', 'reason', 'value']);
        assert.ok(
            typeof item.reason === 'string' && item.reason.trim().length >= 20,
            'Each exclusion needs a substantive review reason',
        );
        assert.equal(
            translations[item.value],
            undefined,
            `Translated value must not be excluded: ${item.value}`,
        );
        const ast = loadSource(item.file);
        let occurrences = 0;
        const visit = (node) => {
            if (ts.isJsxText(node) && node.text.trim() === item.value) {
                assert.fail(
                    `Excluded identifier rendered as JSX text: ${item.value}`,
                );
            }

            if (ts.isStringLiteralLike(node) && node.text === item.value) {
                occurrences++;
                assert.ok(
                    isTechnicalLiteral(node),
                    `Excluded identifier used outside a technical context: ${item.file}:${item.value}`,
                );
            }

            ts.forEachChild(node, visit);
        };
        visit(ast);
        assert.ok(
            occurrences > 0,
            `Stale exclusion: ${item.file}:${item.value}`,
        );
    }
}

test('technical exclusions retain original identifiers and are scoped to reviewed source files', () => {
    validateExclusions(exclusions);

    for (const item of exclusions) {
        assert.equal(translate(item.value, 'es'), item.value);
        assert.equal(translate(item.value, 'en'), item.value);
    }
});

test('exclusion contract rejects unreviewed, duplicate, missing and unjustified entries', () => {
    assert.throws(() =>
        validateExclusions([
            ...exclusions,
            {
                file: exclusions[0].file,
                value: 'unreviewed',
                reason: 'Unreviewed technical identifier',
            },
        ]),
    );
    assert.throws(() => validateExclusions([...exclusions, exclusions[0]]));
    assert.throws(() => validateExclusions(exclusions.slice(1)));
    assert.throws(() =>
        validateExclusions(
            exclusions.map((item, i) =>
                i === 0 ? { ...item, reason: '' } : item,
            ),
        ),
    );
    assert.throws(() =>
        validateExclusions(exclusions, sourceFile, {
            ...catalog,
            close: { es: 'Cerrar', en: 'Close' },
        }),
    );
});

test('exclusion contract rejects obsolete and directly rendered identifiers', () => {
    const changedSource = (replacement) => (file) =>
        file === exclusions[0].file
            ? ts.createSourceFile(
                  file,
                  replacement,
                  ts.ScriptTarget.Latest,
                  true,
                  ts.ScriptKind.TSX,
              )
            : sourceFile(file);
    assert.throws(
        () =>
            validateExclusions(
                exclusions,
                changedSource('const removed = true;'),
            ),
        /Stale exclusion/,
    );
    assert.throws(
        () =>
            validateExclusions(
                exclusions,
                changedSource(
                    "const element = <span>{'set_required_tasks'}</span>;",
                ),
            ),
        /outside a technical context/,
    );
    assert.throws(
        () =>
            validateExclusions(
                exclusions,
                changedSource(
                    'const element = <span>set_required_tasks</span>;',
                ),
            ),
        /rendered as JSX text/,
    );
});

test('banking technical codes resolve to translated human-readable labels', () => {
    const ast = sourceFile(
        'resources/js/components/banking/reconciliation-workspace.tsx',
    );
    const labels = new Map();
    const visit = (node) => {
        if (
            ts.isPropertyAssignment(node) &&
            ts.isStringLiteralLike(node.initializer)
        ) {
            labels.set(propertyName(node), node.initializer.text);
        }

        if (
            ts.isConditionalExpression(node) &&
            ts.isBinaryExpression(node.condition) &&
            ts.isStringLiteralLike(node.condition.right) &&
            ts.isStringLiteralLike(node.whenTrue)
        ) {
            labels.set(node.condition.right.text, node.whenTrue.text);
        }

        if (
            ts.isJsxElement(node) &&
            node.openingElement.tagName.getText() === 'option'
        ) {
            const value = node.openingElement.attributes.properties.find(
                (attribute) =>
                    ts.isJsxAttribute(attribute) &&
                    attribute.name.getText() === 'value',
            );

            if (value?.initializer && ts.isStringLiteral(value.initializer)) {
                labels.set(
                    value.initializer.text,
                    node.children
                        .filter(ts.isJsxText)
                        .map((child) => child.text)
                        .join('')
                        .trim(),
                );
            }
        }

        ts.forEachChild(node, visit);
    };
    visit(ast);

    for (const code of reviewedContract[
        'resources/js/components/banking/reconciliation-workspace.tsx'
    ]) {
        const label = labels.get(code);
        assert.ok(label && label !== code, `Missing visible label for ${code}`);
        assert.ok(
            catalog[label]?.es?.trim() && catalog[label]?.en?.trim(),
            `Missing bilingual label for ${code}: ${label}`,
        );
        // The translator also applies the existing terminology overrides/reverse map.
        assert.notEqual(translate(label, 'en'), label);
        assert.notEqual(translate(label, 'en'), code);
        assert.ok(translate(label, 'en').trim());
        assert.equal(translate(label, 'es'), catalog[label].es);
    }
});

test('translations retain tokens, quantities and explicit manual billing policy', () => {
    assert.match(
        translate(
            'Registra la firma sin enviar correo. La aceptación no crea una visita ni emite una factura.',
            'en',
        ),
        /does not create a visit or issue an invoice/,
    );
    assert.match(
        translate('Justificante firmado (PDF, JPG o PNG; máximo 10 MB)', 'en'),
        /PDF, JPG or PNG; maximum 10 MB/,
    );
    assert.equal(translate('AREVANTES', 'en'), 'AREVANTES');
});
