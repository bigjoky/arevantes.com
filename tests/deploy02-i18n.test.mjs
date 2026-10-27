import assert from 'node:assert/strict';
import { Buffer } from 'node:buffer';
import fs from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';

const catalog = JSON.parse(fs.readFileSync('resources/js/i18n/catalog.json', 'utf8'));
const review = JSON.parse(fs.readFileSync('docs/deploy-02-i18n-review.json', 'utf8'));
const exclusions = JSON.parse(fs.readFileSync('scripts/i18n/reviewed-exclusions.json', 'utf8'));
const source = fs.readFileSync('resources/js/i18n/index.ts', 'utf8').replace("import catalog from '@/i18n/catalog.json';", `const catalog = ${JSON.stringify(catalog)};`);
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText;
const { translate } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);

test('all reviewed visible strings have actual Spanish and English translations', () => {
    const visible = review.items.filter((item) => item.en);
    assert.equal(visible.length, 201);
    const mismatches = [];

    for (const item of visible) {
        assert.deepEqual(catalog[item.es], { en: item.en, es: item.es });

        if (translate(item.es, 'en') !== item.en || translate(item.es, 'es') !== item.es) {
mismatches.push({ key: item.es, actual: translate(item.es, 'en'), expected: item.en });
}
    }

    assert.deepEqual(mismatches, []);
});

test('technical exclusions retain original identifiers and are scoped to reviewed source files', () => {
    assert.equal(exclusions.length, 10);

    for (const item of exclusions) {
        assert.ok(fs.readFileSync(item.file, 'utf8').includes(item.value));
        assert.equal(catalog[item.value], undefined);
        assert.equal(translate(item.value, 'es'), item.value);
        assert.equal(translate(item.value, 'en'), item.value);
    }
});

test('translations retain tokens, quantities and explicit manual billing policy', () => {
    assert.match(translate('Registra la firma sin enviar correo. La aceptación no crea una visita ni emite una factura.', 'en'), /does not create a visit or issue an invoice/);
    assert.match(translate('Justificante firmado (PDF, JPG o PNG; máximo 10 MB)', 'en'), /PDF, JPG or PNG; maximum 10 MB/);
    assert.equal(translate('AREVANTES', 'en'), 'AREVANTES');
});
