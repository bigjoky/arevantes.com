import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    appendOutcome,
    followUpDateTime,
} from '../resources/js/lib/commercial-follow-up.ts';

test('quick outcomes preserve existing notes and do not duplicate a selected outcome', () => {
    assert.equal(
        appendOutcome('Customer detail', 'No response.'),
        'Customer detail\nNo response.',
    );
    assert.equal(
        appendOutcome('Customer detail\nNo response.', 'No response.'),
        'Customer detail\nNo response.',
    );
    assert.equal(appendOutcome('', 'No response.'), 'No response.');
});
test('follow-up shortcuts use the local calendar and do not mutate the supplied date', () => {
    const now = new Date(2026, 11, 31, 17, 30);
    assert.equal(followUpDateTime(1, now), '2027-01-01T09:00');
    assert.equal(followUpDateTime(7, now), '2027-01-07T09:00');
    assert.equal(now.getDate(), 31);
    assert.equal(now.getHours(), 17);
});
