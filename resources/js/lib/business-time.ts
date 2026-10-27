/** Calendar dates are not instants. Never derive them with UTC slicing or device-local parsing. */
export const BUSINESS_TIME_ZONE = 'Europe/Madrid';

export function calendarDate(value: string | null): string | null {
    if (!value) {
        return null;
    }

    const day = value.slice(0, 10);

    if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) {
        return null;
    }

    const parsed = new Date(`${day}T12:00:00Z`);

    return Number.isFinite(parsed.getTime()) &&
        parsed.toISOString().slice(0, 10) === day
        ? day
        : null;
}

export function businessDateTimeInput(
    value: string | Date | null,
    zone = BUSINESS_TIME_ZONE,
): string {
    if (!value) {
        return '';
    }

    const date = typeof value === 'string' ? new Date(value) : value;

    if (!Number.isFinite(date.getTime())) {
        return '';
    }

    // Wall times without offsets are already business inputs, not browser-zone instants.
    if (
        typeof value === 'string' &&
        /^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2})?$/.test(value)
    ) {
        return value.replace(' ', 'T').slice(0, 16);
    }

    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: zone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(date);
    const part = (type: string) =>
        parts.find((item) => item.type === type)?.value ?? '';

    return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
}

/** Preserve an unchanged precise instant through an edit of a datetime-local field. */
export function scheduleInput(
    value: string,
    original?: string | null,
    zone = BUSINESS_TIME_ZONE,
): string {
    return original && value === businessDateTimeInput(original, zone)
        ? original
        : value;
}

export function compareInstants(left: string, right: string): number {
    return new Date(left).getTime() - new Date(right).getTime();
}

/** Civil arithmetic uses UTC only as a calendar, never as an installation instant. */
export function addCalendarDays(day: string, days: number): string {
    const date = new Date(`${day}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);

    return date.toISOString().slice(0, 10);
}

/** Find a unique Madrid instant. Ambiguities stay wall inputs for server validation. */
export function businessInstant(value: string): string {
    if (/[zZ]|[+-]\d\d:?\d\d$/.test(value)) {
        return value;
    }

    const wall = value.slice(0, 16);
    const reference = Date.parse(`${wall}:00Z`);

    if (!Number.isFinite(reference)) {
        return value;
    }

    const offsets = new Set<number>();

    for (const hours of [-48, 0, 48]) {
        const sample = new Date(reference + hours * 3600000);
        const local = businessDateTimeInput(sample);
        offsets.add(Date.parse(`${local}:00Z`) - sample.getTime());
    }

    const candidates = [...offsets]
        .map((offset) => new Date(reference - offset))
        .filter((date) => businessDateTimeInput(date) === wall);

    return candidates.length === 1 ? candidates[0].toISOString() : value;
}

export function addScheduleMinutes(start: string, minutes: number): string {
    const instant = businessInstant(start);

    if (instant === start && !/[zZ]|[+-]\d\d:?\d\d$/.test(start)) {
        return new Date(
            Date.parse(`${start.slice(0, 16)}:00Z`) + minutes * 60000,
        )
            .toISOString()
            .slice(0, 16);
    }

    return new Date(Date.parse(instant) + minutes * 60000).toISOString();
}
