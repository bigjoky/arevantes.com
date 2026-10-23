export function localDateTime(value: Date): string {
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}T${pad(value.getHours())}:${pad(value.getMinutes())}`;
}

export function visitPayload<T extends { visited_at: string }>(data: T) {
    return { ...data, visited_at: new Date(data.visited_at).toISOString() };
}

export function canConvertVisit(
    visit: { outcome: string; opportunity_id: number | null; user_id: number },
    userId: number,
): boolean {
    return (
        visit.user_id === userId &&
        !visit.opportunity_id &&
        ['interested', 'follow_up'].includes(visit.outcome)
    );
}
