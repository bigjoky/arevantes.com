// Keep the persisted probability contract for forecasts and existing API clients.
export const interestLevels = [
    ['10', 'Not assessed'],
    ['25', 'Slightly interested'],
    ['40', 'Interested'],
    ['60', 'Very interested'],
    ['80', 'Ready to decide'],
];

export function interestLabel(probability: number, stage: string): string {
    if (stage === 'won') {
        return 'Won';
    }

    if (stage === 'lost') {
        return 'Lost';
    }

    return (
        interestLevels.find(([value]) => Number(value) === probability)?.[1] ??
        'Previous assessment'
    );
}

export function interestOptions(probability: string): string[][] {
    return interestLevels.some(([value]) => value === probability)
        ? interestLevels
        : [[probability, 'Previous assessment'], ...interestLevels];
}

export function probabilityAfterStageChange(
    probability: number,
    previous: string,
    next: string,
): number {
    if (next === 'won') {
        return 100;
    }

    if (next === 'lost') {
        return 0;
    }

    // Reopening needs a fresh assessment rather than retaining a terminal certainty.
    return previous === 'won' || previous === 'lost' ? 10 : probability;
}
