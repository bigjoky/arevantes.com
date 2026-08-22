<?php

namespace App\Services\Analytics;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        abort_if($allowedAreas === [], 403, __('No tienes acceso a ningún panel de analítica.'));
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['month', 'quarter', 'year', 'last_12_months', 'custom'])],
            'from' => ['nullable', 'required_if:period,custom', 'date'],
            'to' => ['nullable', 'required_if:period,custom', 'date', 'after_or_equal:from'],
            'commercial_id' => ['nullable', 'integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:installations,id'],
            'area' => ['nullable', Rule::in($allowedAreas)],
        ]);
        $period = $data['period'] ?? 'year';
        [$from, $to] = self::resolveDates($period, $data);

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

    /** @param array<string, mixed> $stored */
    public static function fromStored(User $user, string $area, array $stored): self
    {
        abort_unless(in_array($area, self::allowedAreas($user), true), 403);
        $period = in_array($stored['period'] ?? null, ['month', 'quarter', 'year', 'last_12_months', 'custom'], true)
            ? $stored['period']
            : 'year';
        if ($period === 'custom' && (empty($stored['from']) || empty($stored['to']))) {
            $period = 'year';
        }
        [$from, $to] = self::resolveDates($period, $stored);

        return new self(
            $from->startOfDay(),
            $to->endOfDay(),
            $period,
            isset($stored['commercial_id']) ? (int) $stored['commercial_id'] : null,
            isset($stored['project_id']) ? (int) $stored['project_id'] : null,
            $area,
            $user,
        );
    }

    /** @return list<string> */
    public static function allowedAreas(User $user): array
    {
        return collect(['executive', 'sales', 'operations', 'supply', 'finance'])
            ->filter(fn (string $area) => Gate::forUser($user)->allows("view-analytics-{$area}"))
            ->values()
            ->all();
    }

    public function restrictsToOwnCommercialScope(): bool
    {
        return ! $this->user->hasRole('admin') && $this->area === 'sales';
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    public function previousPeriod(): array
    {
        if ($this->period === 'month') {
            return [$this->from->subMonth(), $this->to->subMonth()->endOfDay()];
        }
        if ($this->period === 'quarter') {
            return [$this->from->subMonths(3), $this->to->subMonths(3)->endOfDay()];
        }
        if ($this->period === 'year') {
            return [$this->from->subYear(), $this->to->subYear()->endOfDay()];
        }

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

    /** @param array<string, mixed> $values
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private static function resolveDates(string $period, array $values): array
    {
        $today = CarbonImmutable::today();

        return match ($period) {
            'month' => [$today->startOfMonth(), $today],
            'quarter' => [$today->startOfQuarter(), $today],
            'last_12_months' => [$today->subMonths(11)->startOfMonth(), $today],
            'custom' => [CarbonImmutable::parse($values['from']), CarbonImmutable::parse($values['to'])],
            default => [$today->startOfYear(), $today],
        };
    }
}
