import assert from 'node:assert/strict';
import { test } from 'node:test';
import { documentOperation } from '../resources/js/lib/document-operation.ts';

test('document requests retain their key across retries and validation errors until success', () => {
    let calls = 0;
    const operation = documentOperation(() => `synthetic-${++calls}`);
    const first = operation.transform({ billing_amount: '30.00' });
    assert.equal(operation.transform({ billing_amount: '30.00' }).creation_key, first.creation_key);
    assert.equal(operation.transform({ billing_amount: '31.00' }).creation_key, first.creation_key);
    assert.equal(calls, 1);
    operation.complete();
    assert.notEqual(operation.transform({ billing_amount: '30.00' }).creation_key, first.creation_key);
    assert.equal(calls, 2);
});
