import assert from 'node:assert/strict';
import { test } from 'node:test';
import { localDateTime, visitPayload, canConvertVisit } from '../resources/js/lib/commercial-field.ts';

test('visit time converts the actual local time to an unambiguous API instant', () => {
    const date = new Date(2026, 9, 10, 9, 5);
    assert.equal(localDateTime(date), '2026-10-10T09:05');
    assert.equal(visitPayload({ visited_at: localDateTime(date), outcome: 'interested' }).visited_at, date.toISOString());
});
test('conversion only appears for an owned, actionable, unconverted visit', () => {
    const visit = { user_id: 1, opportunity_id: null, outcome: 'interested' };
    assert.equal(canConvertVisit(visit, 1), true);
    assert.equal(canConvertVisit(visit, 2), false);
    assert.equal(canConvertVisit({ ...visit, outcome: 'do_not_contact' }, 1), false);
    assert.equal(canConvertVisit({ ...visit, opportunity_id: 2 }, 1), false);
});
