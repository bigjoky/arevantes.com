<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Country;
use App\Models\FiscalEntity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FiscalEntityController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = trim($filters['q'] ?? '');

        return Inertia::render('fiscal-entities/index', [
            'entities' => FiscalEntity::query()
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('tax_id_key', FiscalEntity::taxKey($search))
                    ->orWhereLike('legal_name', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%')))
                ->orderBy('legal_name')->paginate(25, ['id', 'country', 'tax_id', 'legal_name', 'is_active'])->withQueryString(),
            'filters' => ['q' => $search],
            'countries' => Country::orderBy('name')->get(['code', 'name']),
        ]);
    }

    public function show(FiscalEntity $fiscalEntity): Response
    {
        return Inertia::render('fiscal-entities/show', [
            'entity' => $fiscalEntity, 'revision' => $fiscalEntity->revision(),
            'countries' => Country::orderBy('name')->get(['code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        try {
            $entity = DB::transaction(function () use ($request, $data): FiscalEntity {
                $entity = FiscalEntity::create($data);
                $this->audit($request, $entity, null);

                return $entity;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['tax_id' => __('This tax identity already exists in this country. Open its existing record.')]);
        }

        return to_route('fiscal-entities.show', $entity)->with('toast', ['type' => 'success', 'message' => __('Fiscal entity saved.')]);
    }

    public function update(Request $request, FiscalEntity $fiscalEntity): RedirectResponse
    {
        $data = $this->validated($request, true);
        DB::transaction(function () use ($request, $fiscalEntity, $data): void {
            $entity = FiscalEntity::lockForUpdate()->findOrFail($fiscalEntity->id);
            if (! hash_equals($entity->revision(), $request->string('revision')->toString())) {
                throw ValidationException::withMessages(['revision' => __('This fiscal record has changed. Reload it before saving.')]);
            }
            if ($data['country'] !== $entity->country || $data['tax_id_key'] !== $entity->tax_id_key) {
                throw ValidationException::withMessages(['tax_id' => __('The country and tax identity cannot be reassigned to another entity.')]);
            }
            $before = $entity->only($entity->getFillable());
            $entity->fill($data);
            if ($entity->isDirty()) {
                $entity->save();
                $this->audit($request, $entity, $before);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Fiscal entity saved.')]);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        $request->merge([
            'country' => is_string($request->input('country')) ? strtoupper(trim($request->input('country'))) : $request->input('country'),
            'tax_id_key' => is_string($request->input('tax_id')) ? FiscalEntity::taxKey($request->input('tax_id')) : null,
        ]);
        $data = $request->validate([
            'country' => ['required', 'string', 'size:2', Rule::exists('countries', 'code')],
            'tax_id' => ['required', 'string', 'max:32'],
            'tax_id_key' => ['required', 'regex:/^[A-Z0-9]{3,32}$/'],
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_names' => ['present', 'array', 'max:30'],
            'trade_names.*' => ['required', 'string', 'max:255'],
            'addresses' => ['present', 'array', 'max:30'],
            'addresses.*' => ['array:id,label,kind,address_line_1,address_line_2,postal_code,city,province,country'],
            'addresses.*.id' => ['nullable', 'uuid'],
            'addresses.*.label' => ['required', 'string', 'max:100'],
            'addresses.*.kind' => ['required', Rule::in(['fiscal', 'registered', 'operating'])],
            'addresses.*.address_line_1' => ['required', 'string', 'max:255'],
            'addresses.*.address_line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
            'addresses.*.city' => ['required', 'string', 'max:100'],
            'addresses.*.province' => ['nullable', 'string', 'max:100'],
            'addresses.*.country' => ['required', 'string', 'size:2', Rule::exists('countries', 'code')],
            'notes' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['required', 'boolean'],
            'revision' => [$updating ? 'required' : 'nullable', 'string', 'size:64'],
            'change_note' => [$updating ? 'required' : 'nullable', 'string', 'max:2000'],
        ]);
        if (collect($data['addresses'])->where('kind', 'fiscal')->count() > 1) {
            throw ValidationException::withMessages(['addresses' => __('Keep only one current fiscal address. Use other address types for establishments.')]);
        }
        $data['trade_names'] = collect($data['trade_names'])->map(fn ($name) => trim($name))->unique(fn ($name) => mb_strtoupper($name))->values()->all();
        $data['addresses'] = collect($data['addresses'])->map(fn ($address) => [...$address, 'id' => $address['id'] ?? (string) Str::uuid()])->all();
        if (count(array_unique(array_column($data['addresses'], 'id'))) !== count($data['addresses'])) {
            throw ValidationException::withMessages(['addresses' => __('Each address must have its own identifier.')]);
        }
        unset($data['revision'], $data['change_note']);

        return $data;
    }

    private function audit(Request $request, FiscalEntity $entity, ?array $before): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'event' => $before === null ? 'fiscal_entity_created' : 'fiscal_entity_updated',
            'auditable_type' => $entity->getMorphClass(), 'auditable_id' => $entity->id,
            'old_values' => $before,
            'new_values' => [...$entity->only($entity->getFillable()), 'change_note' => $request->input('change_note')],
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName(), 'method' => $request->method(),
        ]);
    }
}
