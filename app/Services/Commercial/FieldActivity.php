<?php

namespace App\Services\Commercial;

use App\Models\CommercialFieldSession;
use App\Models\CommercialRoutePlan;
use App\Models\CommercialVisit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FieldActivity
{
    public const TYPES = ['residential' => 'Vivienda', 'business' => 'Empresa', 'community' => 'Comunidad'];

    public const OUTCOMES = [
        'no_answer' => 'Sin respuesta', 'not_interested' => 'Sin interés',
        'interested' => 'Interesado', 'follow_up' => 'Volver a visitar',
        'not_accessible' => 'Sin acceso', 'do_not_contact' => 'No contactar',
    ];

    public function record(Request $request): CommercialVisit
    {
        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'prospect_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'outcome' => ['required', Rule::in(array_keys(self::OUTCOMES))],
            'visited_at' => ['required', 'date', 'before_or_equal:now', 'after_or_equal:'.now()->subDays(30)->toISOString()],
            'commercial_route_plan_id' => ['nullable', 'integer', Rule::exists('commercial_route_plans', 'id')->where('user_id', $request->user()->id)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:100000', 'required_with:latitude'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        return DB::transaction(function () use ($request, $data): CommercialVisit {
            // Serialize timers and visits for this user, including client UUID retries.
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = CommercialVisit::where('client_uuid', $data['client_uuid'])->first();
            if ($existing) {
                abort_unless($existing->user_id === $request->user()->id && $existing->prospect_type !== null, 409);

                return $existing;
            }
            if ($routeId = $data['commercial_route_plan_id'] ?? null) {
                $route = CommercialRoutePlan::whereKey($routeId)->lockForUpdate()->firstOrFail();
                abort_unless($route->user_id === $request->user()->id, 404);
                if ($route->route_date->toDateString() !== CarbonImmutable::parse($data['visited_at'])->timezone(config('app.timezone'))->toDateString()) {
                    throw ValidationException::withMessages(['commercial_route_plan_id' => 'La ruta debe corresponder al día de la visita.']);
                }
            }
            $at = CarbonImmutable::parse($data['visited_at'])->timezone(config('app.timezone'));
            $data['visited_at'] = $at;
            $session = CommercialFieldSession::where('user_id', $request->user()->id)
                ->where('started_at', '<=', $at->utc())
                ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $at->utc()))
                ->latest('started_at')->first();

            return CommercialVisit::create([
                ...Arr::only($data, ['client_uuid', 'prospect_type', 'outcome', 'visited_at', 'commercial_route_plan_id', 'latitude', 'longitude', 'accuracy_meters', 'address', 'notes']),
                'user_id' => $request->user()->id,
                'commercial_field_session_id' => $session?->id,
            ]);
        });
    }

    public function start(User $user): CommercialFieldSession
    {
        return DB::transaction(function () use ($user): CommercialFieldSession {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (CommercialFieldSession::where('user_id', $user->id)->whereNull('ended_at')->exists()) {
                throw ValidationException::withMessages(['session' => 'Ya hay una sesión abierta. Finalízala antes de iniciar otra.']);
            }

            return CommercialFieldSession::create(['user_id' => $user->id, 'started_at' => now()]);
        });
    }

    public function stop(Request $request, CommercialFieldSession $session): CommercialFieldSession
    {
        abort_unless($session->user_id === $request->user()->id, 404);
        $data = $request->validate(['ended_at' => ['nullable', 'date', 'after:'.$session->started_at->toISOString(), 'before_or_equal:now']]);

        return DB::transaction(function () use ($request, $session, $data): CommercialFieldSession {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $session = CommercialFieldSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->ended_at) {
                return $session;
            }
            $end = CarbonImmutable::parse($data['ended_at'] ?? now())->timezone(config('app.timezone'));
            if (CommercialVisit::where('commercial_field_session_id', $session->id)->where('visited_at', '>', $end)->exists()) {
                throw ValidationException::withMessages(['ended_at' => 'El fin no puede preceder a las visitas de la sesión.']);
            }
            $session->update(['ended_at' => $end]);

            return $session;
        });
    }

    public function report(CarbonImmutable $from, CarbonImmutable $to, ?int $userId): array
    {
        $visits = CommercialVisit::whereNotNull('prospect_type')->whereBetween('visited_at', [$from, $to])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))->get();
        $sessions = CommercialFieldSession::where('started_at', '<', $to->utc())
            ->whereNotNull('ended_at')->where('ended_at', '>', $from->utc())
            ->when($userId, fn ($query) => $query->where('user_id', $userId))->get();
        $open = CommercialFieldSession::whereNull('ended_at')->when($userId, fn ($query) => $query->where('user_id', $userId))->get(['id', 'user_id']);
        $ids = $visits->pluck('user_id')->merge($sessions->pluck('user_id'))->merge($open->pluck('user_id'))->unique();
        $names = User::whereIn('id', $ids)->pluck('name', 'id');

        return $ids->map(function ($id) use ($visits, $sessions, $open, $names, $from, $to): array {
            $items = $visits->where('user_id', $id);
            $closed = $sessions->where('user_id', $id);
            $seconds = $closed->sum(fn ($session) => max(0, $session->started_at->max($from)->diffInSeconds($session->ended_at->min($to))));
            $measured = $items->filter(fn ($visit) => $closed->contains('id', $visit->commercial_field_session_id));
            $hours = $seconds / 3600;

            return [
                'user_id' => $id, 'name' => $names[$id] ?? 'Usuario eliminado',
                'visits' => $items->count(), 'measured_visits' => $measured->count(),
                'open_sessions' => $open->where('user_id', $id)->count(),
                'unmeasured_visits' => $items->count() - $measured->count(),
                'hours' => round($hours, 2), 'visits_per_hour' => $hours > 0 ? round($measured->count() / $hours, 2) : null,
                'interested' => $items->where('outcome', 'interested')->count(),
                'converted' => $items->whereNotNull('opportunity_id')->count(),
                'outcomes' => $items->countBy('outcome')->all(),
            ];
        })->values()->all();
    }
}
