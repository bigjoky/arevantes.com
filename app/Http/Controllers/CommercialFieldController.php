<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Mobile\V1\CommercialLeadController;
use App\Models\CommercialFieldSession;
use App\Models\CommercialRoutePlan;
use App\Models\CommercialVisit;
use App\Models\User;
use App\Services\Commercial\FieldActivity;
use App\Support\BusinessLineCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CommercialFieldController extends Controller
{
    public function index(Request $request, FieldActivity $activity)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $supervisor = Gate::allows('supervise-commercial-field');
        $userId = $supervisor ? ($data['user_id'] ?? null) : $request->user()->id;
        $from = CarbonImmutable::parse($data['from'] ?? today()->startOfMonth())->startOfDay();
        $to = CarbonImmutable::parse($data['to'] ?? today())->endOfDay();
        abort_if($from > $to || $from->diffInDays($to) > 366, 422, 'Selecciona un período de hasta 366 días.');
        $visits = CommercialVisit::whereNotNull('prospect_type')->whereBetween('visited_at', [$from, $to])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->with(['commercial:id,name', 'routePlan:id,sector_label'])->latest('visited_at')->paginate(30)->withQueryString();
        $payload = [
            'visits' => $visits,
            'report' => $activity->report($from, $to, $userId),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'user_id' => $userId],
            'supervisor' => $supervisor,
            'canRecord' => Gate::allows('record-commercial-field'),
            'canConvert' => Gate::allows('record-commercial-field') && Gate::allows('manage-customers'),
            'recorders' => $supervisor ? User::orderBy('name')->get(['id', 'name', 'role', 'permission_profile_id'])->filter(fn ($user) => Gate::forUser($user)->allows('record-commercial-field'))->map(fn ($user) => $user->only(['id', 'name']))->values() : [],
            'assignedRoutes' => $supervisor ? CommercialRoutePlan::whereDate('route_date', '>=', $from->toDateString())->whereDate('route_date', '<=', $to->toDateString())->when($userId, fn ($query) => $query->where('user_id', $userId))->with('commercial:id,name')->latest('route_date')->limit(50)->get(['id', 'user_id', 'route_date', 'sector_label', 'instructions', 'status']) : [],
            'currentUserId' => $request->user()->id,
            'activeSession' => CommercialFieldSession::where('user_id', $request->user()->id)->whereNull('ended_at')->first(),
            'recentSessions' => CommercialFieldSession::where('user_id', $request->user()->id)->latest('started_at')->limit(10)->get(),
            'routes' => CommercialRoutePlan::where('user_id', $request->user()->id)->whereDate('route_date', today())->get(['id', 'route_date', 'status', 'sector_label', 'instructions']),
            'types' => FieldActivity::TYPES, 'outcomes' => FieldActivity::OUTCOMES,
            'serviceInterests' => BusinessLineCatalog::SERVICE_INTERESTS,
        ];

        return $request->is('api/*') ? response()->json(['data' => $payload]) : Inertia::render('commercial-field/index', $payload);
    }

    public function assignRoute(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'route_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addDays(30)->toDateString()],
            'sector_label' => ['required', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:500'],
        ]);
        $user = User::findOrFail($data['user_id']);
        abort_unless(Gate::forUser($user)->allows('record-commercial-field'), 422, 'El usuario no tiene permiso para registrar actividad de campo.');
        $route = DB::transaction(function () use ($data, $user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $route = CommercialRoutePlan::where('user_id', $user->id)->whereDate('route_date', $data['route_date'])->lockForUpdate()->first()
                ?? CommercialRoutePlan::create(['user_id' => $user->id, 'route_date' => $data['route_date'], 'status' => 'planned']);
            $route->update(['sector_label' => $data['sector_label'], 'instructions' => $data['instructions'] ?? null]);

            return $route;
        });

        return $this->respond($request, $route);
    }

    public function store(Request $request, FieldActivity $activity)
    {
        $visit = $activity->record($request);

        return $this->respond($request, $visit);
    }

    public function start(Request $request, FieldActivity $activity)
    {
        return $this->respond($request, $activity->start($request->user()));
    }

    public function stop(Request $request, CommercialFieldSession $session, FieldActivity $activity)
    {
        return $this->respond($request, $activity->stop($request, $session));
    }

    public function convert(Request $request, CommercialVisit $visit, CommercialLeadController $leads)
    {
        abort_unless($visit->user_id === $request->user()->id && $visit->prospect_type !== null, 404);
        $response = $leads->convertVisit($request, $visit);

        return $request->is('api/*') ? $response : redirect()->back()->with('toast', ['type' => 'success', 'message' => 'Oportunidad creada.']);
    }

    private function respond(Request $request, $model)
    {
        return $request->is('api/*') ? response()->json(['data' => $model->only(['id', 'client_uuid', 'prospect_type', 'outcome', 'commercial_route_plan_id', 'commercial_field_session_id', 'person_id', 'opportunity_id', 'started_at', 'ended_at'])], 201) : redirect()->back()->with('toast', ['type' => 'success', 'message' => 'Actividad guardada.']);
    }
}
