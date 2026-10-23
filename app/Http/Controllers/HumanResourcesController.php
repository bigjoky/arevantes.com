<?php

namespace App\Http\Controllers;

use App\Models\HrDepartment;
use App\Models\HrEmployeeTask;
use App\Models\HrEmploymentProfile;
use App\Models\HrLeaveRequest;
use App\Models\Person;
use App\Services\Planning\WorkforceCapacityForecast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HumanResourcesController extends Controller
{
    /**
     * Show the human resources overview.
     */
    public function __invoke(WorkforceCapacityForecast $capacityForecast): Response
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
                'activeEmployment' => HrEmploymentProfile::where('status', 'active')->count(),
                'pendingLeave' => HrLeaveRequest::where('status', 'pending')->count(),
                'departments' => HrDepartment::where('is_active', true)->count(),
                'overdueTasks' => HrEmployeeTask::query()
                    ->whereNot('status', 'completed')
                    ->whereDate('due_date', '<', today())
                    ->count(),
            ],
            'roleDistribution' => $this->roleDistribution($staff),
            'recentStaff' => $staff
                ->take(6)
                ->map(fn (Person $person) => [
                    'id' => $person->id,
                    'name' => $this->displayName($person),
                    'email' => Gate::allows('view-sensitive-hr') ? $person->email : null,
                    'staffRole' => $person->staff_role,
                    'accessRole' => $person->user?->role,
                    'createdAt' => $person->created_at?->toDateString(),
                ])
                ->values(),
            'capacityForecast' => $capacityForecast->forecast(today()->startOfWeek(), 6),
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
