import assert from 'node:assert/strict';
import { test } from 'node:test';
import { requestGuardedClose } from '../resources/js/pages/human-resources/close-guards.ts';

test('HR inline form closes immediately when no form is dirty', () => {
    let closed = 0;
    requestGuardedClose([], () => closed++);
    assert.equal(closed, 1);
});

test('HR inline form remains open until discard is confirmed', () => {
    let closed = 0;
    let confirm;
    requestGuardedClose(
        [
            (next) => {
                confirm = next;
            },
        ],
        () => closed++,
    );
    assert.equal(closed, 0);
    confirm();
    assert.equal(closed, 1);
});

test('all mounted HR forms must allow closing', () => {
    const prompts = [];
    let closed = false;
    requestGuardedClose(
        [(next) => prompts.push(next), (next) => prompts.push(next)],
        () => {
            closed = true;
        },
    );
    assert.equal(prompts.length, 1);
    prompts[0]();
    assert.equal(prompts.length, 2);
    assert.equal(closed, false);
    prompts[1]();
    assert.equal(closed, true);
});
