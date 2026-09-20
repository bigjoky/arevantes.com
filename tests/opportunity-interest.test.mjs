import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    interestLabel,
    interestOptions,
    probabilityAfterStageChange,
} from '../resources/js/lib/opportunity-interest.ts';

test('interest levels retain the numeric forecast contract', () => {
    assert.deepEqual(
        interestOptions('10').map(([value]) => Number(value)),
        [10, 25, 40, 60, 80],
    );
    assert.equal(interestLabel(60, 'proposal'), 'Very interested');
});
test('legacy assessments are preserved without rounding', () => {
    assert.deepEqual(interestOptions('53')[0], ['53', 'Previous assessment']);
    assert.equal(probabilityAfterStageChange(53, 'qualified', 'proposal'), 53);
});
test('closing overrides interest and reopening asks for a fresh assessment', () => {
    assert.equal(probabilityAfterStageChange(60, 'proposal', 'won'), 100);
    assert.equal(probabilityAfterStageChange(60, 'proposal', 'lost'), 0);
    assert.equal(probabilityAfterStageChange(100, 'won', 'qualified'), 10);
    assert.equal(interestLabel(100, 'won'), 'Won');
    assert.equal(interestLabel(0, 'lost'), 'Lost');
});
