<?php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class HumanResourcesController extends Controller
{
    /**
     * Show the human resources overview.
     */
    public function __invoke(): Response
    {
        $staff = Person::query()
            ->where('category', 'staff')
            ->with('user:id,person_id,role')
            ->latest()
            ->latest('id')
            ->get([
                'id',
                'first_name',
                'last_name',
                'company_name',
                'email',
                'staff_role',
                'created_at',
            ]);

        return Inertia::render('human-resources/index', [
            'summary' => [
                'total' => $staff->count(),
                'withAccess' => $staff->whereNotNull('user')->count(),
                'withoutAccess' => $staff->whereNull('user')->count(),
                'roles' => $staff->pluck('staff_role')->filter()->unique()->count(),
            ],
            'roleDistribution' => $this->roleDistribution($staff),
            'recentStaff' => $staff
                ->take(6)
                ->map(fn (Person $person) => [
                    'id' => $person->id,
                    'name' => $this->displayName($person),
                    'email' => $person->email,
                    'staffRole' => $person->staff_role,
                    'accessRole' => $person->user?->role,
                    'createdAt' => $person->created_at?->toDateString(),
                ])
                ->values(),
        ]);
    }

    /**
     * @param  Collection<int, Person>  $staff
     * @return Collection<int, array{role: string, count: int}>
     */
    private function roleDistribution(Collection $staff): Collection
    {
        return $staff
            ->groupBy(fn (Person $person) => $person->staff_role ?: __('Unassigned'))
            ->map(fn (Collection $people, string $role) => [
                'role' => $role,
                'count' => $people->count(),
            ])
            ->sortByDesc('count')
            ->values();
    }

    private function displayName(Person $person): string
    {
        return $person->company_name
            ?: trim($person->first_name.' '.($person->last_name ?? ''));
    }
}
