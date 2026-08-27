import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import ts from 'typescript';

const root = process.cwd();
const sourceRoot = path.join(root, 'resources/js');
const catalog = JSON.parse(
    fs.readFileSync(path.join(sourceRoot, 'i18n/catalog.json'), 'utf8'),
);
const visibleAttributes = new Set([
    'alt',
    'aria-label',
    'description',
    'label',
    'placeholder',
    'title',
]);
const visibleContentProperties = new Set([
    'answer',
    'audiences',
    'body',
    'caption',
    'cardTitle',
    'deliverables',
    'description',
    'detail',
    'emptyText',
    'eyebrow',
    'features',
    'helper',
    'highlights',
    'intro',
    'items',
    'label',
    'name',
    'question',
    'services',
    'short_name',
    'subtitle',
    'summary',
    'text',
    'title',
    'imageAlt',
    'accent',
]);
const discovered = new Map();

const normalize = (value) =>
    value
        .replace(/&#10;/g, ' ')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&')
        .replace(/\s+/g, ' ')
        .trim();
const register = (value, file) => {
    const key = normalize(value);

    if (key.length > 1 && /\p{L}/u.test(key) && !discovered.has(key)) {
        discovered.set(key, path.relative(root, file));
    }
};

const visit = (node, file) => {
    if (ts.isJsxText(node)) {
        register(node.getText(), file);
    }

    if (
        ts.isJsxAttribute(node) &&
        visibleAttributes.has(node.name.getText()) &&
        node.initializer &&
        ts.isStringLiteral(node.initializer)
    ) {
        register(node.initializer.text, file);
    }

    if (ts.isStringLiteralLike(node)) {
        let current = node.parent;

        while (current && !ts.isSourceFile(current)) {
            if (ts.isJsxAttribute(current)) {
                if (visibleAttributes.has(current.name.getText())) {
                    register(node.text, file);
                }

                break;
            }

            if (ts.isJsxExpression(current)) {
                const jsxParent = current.parent;

                if (!ts.isJsxAttribute(jsxParent)) {
                    register(node.text, file);
                }

                break;
            }

            if (ts.isPropertyAssignment(current)) {
                const property = current.name
                    .getText()
                    .replace(/^['"]|['"]$/g, '');

                if (visibleContentProperties.has(property)) {
                    register(node.text, file);
                }

                break;
            }

            if (
                ts.isImportDeclaration(current) ||
                ts.isExportDeclaration(current)
            ) {
                break;
            }

            current = current.parent;
        }
    }

    ts.forEachChild(node, (child) => visit(child, file));
};

const walk = (directory) => {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const target = path.join(directory, entry.name);

        if (entry.isDirectory()) {
            walk(target);
        } else if (entry.name.endsWith('.tsx')) {
            const source = ts.createSourceFile(
                target,
                fs.readFileSync(target, 'utf8'),
                ts.ScriptTarget.Latest,
                true,
                ts.ScriptKind.TSX,
            );
            visit(source, target);
        }
    }
};

walk(sourceRoot);

const missing = [...discovered].filter(([key]) => !catalog[key]);
const malformed = Object.entries(catalog).filter(
    ([, value]) =>
        typeof value !== 'object' ||
        typeof value.en !== 'string' ||
        typeof value.es !== 'string' ||
        !value.en.trim() ||
        !value.es.trim(),
);

if (process.env.I18N_REPORT_PATH) {
    fs.writeFileSync(
        process.env.I18N_REPORT_PATH,
        `${JSON.stringify(
            {
                discovered: Object.fromEntries(discovered),
                missing: Object.fromEntries(missing),
            },
            null,
            2,
        )}\n`,
    );
}

if (missing.length || malformed.length) {
    if (missing.length) {
        console.error(
            `Visible strings missing from the translation catalog: ${missing.length}`,
        );

        for (const [key, file] of missing.slice(0, 100)) {
            console.error(`- ${file}: ${key}`);
        }
    }

    if (malformed.length) {
        console.error(`Malformed catalog entries: ${malformed.length}`);
    }

    process.exit(1);
}

console.log(
    `Translation catalog complete: ${discovered.size} visible strings, ${Object.keys(catalog).length} bilingual entries.`,
);
