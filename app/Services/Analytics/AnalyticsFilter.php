<?php

namespace App\Services\Analytics;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class AnalyticsFilter
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $period,
        public ?int $commercialId,
        public ?int $projectId,
        public string $area,
        public User $user,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $allowedAreas = self::allowedAreas($request->user());
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['month', 'quarter', 'year', 'last_12_months', 'custom'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'commercial_id' => ['nullable', 'integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:installations,id'],
            'area' => ['nullable', Rule::in($allowedAreas)],
        ]);
        $period = $data['period'] ?? 'year';
        $today = CarbonImmutable::today();
        [$from, $to] = match ($period) {
            'month' => [$today->startOfMonth(), $today->endOfMonth()],
            'quarter' => [$today->startOfQuarter(), $today->endOfQuarter()],
            'last_12_months' => [$today->subMonths(11)->startOfMonth(), $today->endOfMonth()],
            'custom' => [CarbonImmutable::parse($data['from'] ?? $today->startOfYear()), CarbonImmutable::parse($data['to'] ?? $today)],
            default => [$today->startOfYear(), $today->endOfYear()],
        };

        return new self(
            $from->startOfDay(),
            $to->endOfDay(),
            $period,
            isset($data['commercial_id']) ? (int) $data['commercial_id'] : null,
            isset($data['project_id']) ? (int) $data['project_id'] : null,
            $data['area'] ?? $allowedAreas[0],
            $request->user(),
        );
    }

    /** @return list<string> */
    public static function allowedAreas(User $user): array
    {
        return $user->hasRole('technician')
            ? ['operations']
            : ['executive', 'sales', 'operations', 'supply', 'finance'];
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    public function previousPeriod(): array
    {
        $days = $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;

        return [$this->from->subDays($days), $this->from->subDay()->endOfDay()];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'commercial_id' => $this->commercialId,
            'project_id' => $this->projectId,
            'area' => $this->area,
        ];
    }
}
