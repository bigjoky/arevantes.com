import assert from 'node:assert/strict';
import test from 'node:test';
import { TaskDraftGuard } from '../resources/js/lib/mobile/task-draft-guard.ts';

function fixture(dirty = false) {
    const state = {
        dirty,
        processing: false,
        values: {
            title: 'Borrador',
            due: '2026-10-24T12:30',
            priority: 'high',
        },
        errors: {},
    };
    const calls = { closed: 0, reset: 0, dialog: false };
    const guard = new TaskDraftGuard(
        () => state,
        (open) => {
            calls.dialog = open;
        },
        () => {
            calls.reset++;
            state.values = { title: '', due: '', priority: 'normal' };
            state.errors = {};
            state.dirty = false;
        },
    );

    return {
        state,
        calls,
        guard,
        close: () => {
            calls.closed++;
        },
    };
}

test('unchanged task closes without asking', () => {
    const { guard, close, calls } = fixture();
    assert.equal(guard.requestClose(close), 'closed');
    assert.equal(calls.closed, 1);
    assert.equal(calls.dialog, false);
});

for (const source of [
    'close button',
    'Escape',
    'back',
    'outside',
    'GET navigation',
]) {
    test(`${source}: dirty close asks; continuing retains every field and validation error`, () => {
        const { state, guard, close, calls } = fixture(true);
        state.errors = { title: 'Validación del servidor' };
        const before = structuredClone(state);
        assert.equal(guard.requestClose(close), 'confirm');
        assert.equal(calls.closed, 0);
        assert.equal(calls.dialog, true);
        guard.keepEditing();
        assert.deepEqual(state, before);
        assert.equal(calls.closed, 0);
        assert.equal(calls.dialog, false);
    });
}

test('confirmed discard clears fields and errors once before closing', () => {
    const { state, guard, close, calls } = fixture(true);
    guard.requestClose(close);
    guard.requestClose(close);
    guard.discard();
    guard.discard();
    assert.equal(calls.closed, 1);
    assert.equal(calls.reset, 1);
    assert.equal(state.values.title, '');
    assert.equal(state.values.priority, 'normal');
});

test('busy request blocks every close and duplicate submission synchronously', () => {
    const { guard, close, calls, state } = fixture(true);
    assert.equal(guard.beginSubmit(), true);
    assert.equal(guard.beginSubmit(), false);
    assert.equal(guard.requestClose(close), 'busy');
    guard.discard();
    assert.equal(calls.closed, 0);
    guard.finishSubmit();
    state.processing = true;
    assert.equal(guard.beginSubmit(), false);
    assert.equal(guard.requestClose(close), 'busy');
});

test('server validation failure unlocks retry without clearing values or errors', () => {
    const { state, guard, calls } = fixture(true);
    guard.beginSubmit();
    state.errors = { due: 'Fecha no válida' };
    guard.finishSubmit();
    assert.equal(calls.reset, 0);
    assert.equal(state.values.title, 'Borrador');
    assert.equal(state.errors.due, 'Fecha no válida');
    assert.equal(guard.beginSubmit(), true);
});

test('successful save resets the draft then closes without discard confirmation', () => {
    const { state, guard, close, calls } = fixture(true);
    guard.beginSubmit();
    guard.saved(close);
    assert.equal(calls.reset, 1);
    assert.equal(calls.closed, 1);
    assert.equal(calls.dialog, false);
    assert.equal(state.dirty, false);
});
