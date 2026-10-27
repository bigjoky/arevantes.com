import assert from 'node:assert/strict';
import process from 'node:process';
import { test } from 'node:test';
import { businessDateTimeInput, calendarDate, compareInstants, scheduleInput } from '../resources/js/lib/business-time.ts';

test('Madrid inputs preserve both folds regardless of the browser time zone', () => {
    for (const zone of ['UTC', 'America/Los_Angeles', 'Asia/Tokyo']) {
        process.env.TZ = zone;
        assert.equal(businessDateTimeInput('2026-10-25T00:30:00Z'), '2026-10-25T02:30');
        assert.equal(businessDateTimeInput('2026-10-25T01:30:00Z'), '2026-10-25T02:30');
        assert.equal(scheduleInput('2026-10-25T02:30', '2026-10-25T01:30:00Z'), '2026-10-25T01:30:00Z');
        assert.equal(scheduleInput('2026-10-25T03:30', '2026-10-25T01:30:00Z'), '2026-10-25T03:30');
    }
});
test('calendar dates retain their day and invalid dates are not normalized silently', () => {
    assert.equal(calendarDate('2026-10-25'), '2026-10-25');
    assert.equal(calendarDate('2026-02-30'), null);
    assert.equal(calendarDate(null), null);
});
test('spring conversion and mixed-offset ordering use real instants', () => {
    assert.equal(businessDateTimeInput('2026-03-29T01:30:00Z'), '2026-03-29T03:30');
    assert.ok(compareInstants('2026-10-25T02:50:00+02:00', '2026-10-25T02:10:00+01:00') < 0);
    assert.equal(businessDateTimeInput('not-a-date'), '');
});

test('calendar navigation and rescheduling do not depend on device zone', async () => {
    const { addCalendarDays, businessInstant, addScheduleMinutes } = await import('../resources/js/lib/business-time.ts');
    const previous = process.env.TZ;

    try {
        for (const zone of ['UTC', 'America/Los_Angeles', 'Asia/Tokyo', 'Pacific/Kiritimati']) {
            process.env.TZ = zone;
            assert.equal(addCalendarDays('2026-03-28', 1), '2026-03-29');
            assert.equal(businessInstant('2026-10-25T02:30'), '2026-10-25T02:30');
            assert.equal(businessInstant('2026-03-29T02:30'), '2026-03-29T02:30');
            assert.equal(businessInstant('2026-10-25T08:00'), '2026-10-25T07:00:00.000Z');
            assert.equal(addScheduleMinutes('2026-03-29T01:30', 60), '2026-03-29T01:30:00.000Z');
            assert.equal(businessDateTimeInput(addScheduleMinutes('2026-03-29T01:30', 60)), '2026-03-29T03:30');
        }
    } finally {
        if (previous === undefined) {
 delete process.env.TZ; 
} else {
 process.env.TZ = previous; 
}
    }
});

test('unchanged repeated-hour rescheduling retains the real one-hour duration', async () => {
    const { addScheduleMinutes } = await import('../resources/js/lib/business-time.ts');
    const start = scheduleInput('2026-10-25T02:30', '2026-10-25T00:30:00Z');
    const end = addScheduleMinutes(start, 60);
    assert.equal(end, '2026-10-25T01:30:00.000Z');
    assert.equal(businessDateTimeInput(end), '2026-10-25T02:30');
    assert.equal(scheduleInput(businessDateTimeInput(end), '2026-10-25T01:30:00Z'), '2026-10-25T01:30:00Z');
});
