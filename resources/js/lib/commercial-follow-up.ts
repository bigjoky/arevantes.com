export function appendOutcome(notes: string, outcome: string): string {
    if (notes.split('\n').some((line) => line.trim() === outcome)) {
        return notes;
    }

    return notes.trim() ? `${notes.trimEnd()}\n${outcome}` : outcome;
}

export function followUpDateTime(days: number, now = new Date()): string {
    const value = new Date(now);
    value.setDate(value.getDate() + days);
    value.setHours(
        days ? 9 : value.getHours(),
        days ? 0 : value.getMinutes(),
        0,
        0,
    );

    return new Date(value.getTime() - value.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 16);
}
