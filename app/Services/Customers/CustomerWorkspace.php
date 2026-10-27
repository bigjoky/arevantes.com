<?php

namespace App\Services\Customers;

use App\Models\CrmActivity;
use App\Models\EngineeringDossier;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\OpportunityEvent;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Support\Time\InstantSql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Read-only integration: every domain retains its own workflows and authorization. */
class CustomerWorkspace
{
    public function permissions(User $user): array
    {
        return [
            'opportunities' => $user->can('manage-customers'),
            'quotes' => $user->can('manage-quotes'),
            'projects' => $user->can('view-installations'),
            'installations' => $user->can('view-installations'),
            'invoices' => $user->can('manage-installations'),
            'documents' => $user->can('manage-operations'),
            'activity' => $user->can('manage-customers'),
        ];
    }

    public function listing(Request $request): array
    {
        $permissions = $this->permissions($request->user());
        $query = Person::query()->where('category', 'customer')
            ->when($request->boolean('archived'), fn ($q) => $q->onlyTrashed())
            ->with('tags:id,name,color')->withCount('opportunities');
        if ($permissions['activity']) {
            $query->addSelect(['last_activity_at' => CrmActivity::query()->selectRaw('MAX(created_at)')->whereColumn('person_id', 'people.id')->whereNull('installation_id')->whereNull('work_order_id')]);
        }
        if ($permissions['quotes']) {
            $query->addSelect(['quotes_count' => Quote::query()->selectRaw('COUNT(*)')->whereColumn('person_id', 'people.id')]);
        }
        if ($permissions['invoices']) {
            $query->addSelect(['volume' => $this->invoices()->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('person_id', 'people.id')]);
        }
        $search = trim((string) $request->input('search'));
        $query->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
            $q->orWhereRaw("LOWER(first_name || ' ' || COALESCE(last_name, '')) LIKE ?", ['%'.mb_strtolower($search).'%']);
            foreach (['first_name', 'last_name', 'company_name', 'tax_id', 'document_number', 'email', 'phone'] as $field) {
                $q->orWhereRaw('LOWER('.$field.') LIKE ?', ['%'.mb_strtolower($search).'%']);
            }
        }));
        foreach (['customer_type', 'lifecycle_status', 'lead_source', 'province_state'] as $field) {
            $query->when($request->filled($field), fn ($q) => $q->where($field, $request->input($field)));
        }
        $query->when($request->filled('tag'), fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey($request->integer('tag'))));
        $query->when($request->boolean('has_opportunities'), fn ($q) => $q->has('opportunities'));
        if ($permissions['quotes'] && $request->boolean('has_quotes')) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('quotes')->whereColumn('quotes.person_id', 'people.id'));
        }
        $sort = in_array($request->input('sort'), ['name', 'created_at', 'last_activity_at', 'volume'], true) ? $request->input('sort') : 'created_at';
        if (($sort === 'volume' && ! $permissions['invoices']) || ($sort === 'last_activity_at' && ! $permissions['activity'])) {
            $sort = 'created_at';
        }
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $sort === 'name'
            ? $query->orderByRaw("LOWER(COALESCE(NULLIF(company_name, ''), first_name || ' ' || COALESCE(last_name, ''))) ".$direction)
            : $query->orderByRaw($sort.' '.$direction.' NULLS LAST');
        $perPage = in_array($request->integer('per_page'), [15, 30, 50], true) ? $request->integer('per_page') : 15;

        return [
            'people' => $query->orderBy('id')->paginate($perPage)->withQueryString()->through(fn (Person $p) => [
                'id' => $p->id, 'name' => $this->name($p), 'tax_id' => $p->tax_id ?: $p->document_number,
                'customer_type' => $p->customer_type, 'lifecycle_status' => $p->lifecycle_status,
                'location' => collect([$p->city ?: $p->locality, $p->province_state])->filter()->unique()->implode(' · '),
                'opportunities_count' => $p->opportunities_count, 'quotes_count' => $permissions['quotes'] ? (int) $p->quotes_count : null,
                'volume' => $permissions['invoices'] ? (float) $p->volume : null,
                'last_activity_at' => $p->last_activity_at ? Carbon::parse($p->last_activity_at)->toIso8601String() : null, 'email' => $p->email, 'phone' => $p->phone,
            ]),
            'customerPermissions' => $permissions,
            'provinces' => Person::query()->where('category', 'customer')->whereNotNull('province_state')->distinct()->orderBy('province_state')->pluck('province_state'),
        ];
    }

    public function detail(Person $person, Request $request): array
    {
        $permissions = $this->permissions($request->user());
        $tab = $request->input('tab', 'summary');
        abort_unless($tab === 'summary' || array_key_exists($tab, $permissions), 422);
        abort_if($tab !== 'summary' && ! $permissions[$tab], 403);
        $domains = [];
        foreach ($permissions as $domain => $allowed) {
            if (! $allowed) {
                $domains[$domain] = null;

                continue;
            }
            $query = $this->domainQuery($domain, $person);
            if ($tab === 'summary') {
                $domains[$domain] = ['total' => (clone $query)->count(), 'data' => $query->limit(3)->get()->map(fn ($row) => $this->row($domain, $row))->all(), 'links' => []];
            } elseif ($domain === $tab) {
                $domains[$domain] = $query->paginate(15, ['*'], 'relation_page')->withQueryString()->through(fn ($row) => $this->row($domain, $row))->toArray();
            }
        }
        $financial = $permissions['invoices'] ? [
            'billed' => (float) $this->invoices()->where('person_id', $person->id)->sum('amount'),
            'outstanding' => (float) $this->invoices()->where('person_id', $person->id)->whereIn('status', ['issued', 'partial', 'overdue'])->selectRaw('COALESCE(SUM(amount - paid_amount), 0) AS total')->value('total'),
        ] : null;

        $duplicates = $tab === 'summary' ? Person::query()->where('category', 'customer')->whereKeyNot($person->id)
            ->where(function ($q) use ($person) {
                $q->whereRaw('1 = 0');
                foreach (['tax_id', 'email', 'phone_normalized'] as $field) {
                    if (filled($person->{$field})) {
                        $q->orWhereRaw('LOWER('.$field.') = ?', [mb_strtolower($person->{$field})]);
                    }
                }
            })->limit(5)->get(['id', 'first_name', 'last_name', 'company_name'])->map(fn ($p) => ['id' => $p->id, 'name' => $this->name($p)])->all() : [];

        return ['tab' => $tab, 'permissions' => $permissions, 'domains' => $domains, 'financial' => $financial, 'possibleDuplicates' => $duplicates];
    }

    public function name(Person $person): string
    {
        return $person->company_name ?: trim($person->first_name.' '.$person->last_name);
    }

    private function invoices(): Builder
    {
        // Issued invoices, including VAT, excluding drafts and cancelled documents.
        return SalesDocument::query()->where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'paid', 'overdue']);
    }

    private function installations(Person $person): Builder
    {
        return Installation::query()->where(fn ($q) => $q->where('person_id', $person->id)->orWhereHas('people', fn ($p) => $p->where('people.id', $person->id)));
    }

    private function domainQuery(string $domain, Person $person): Builder|\Illuminate\Database\Query\Builder
    {
        return match ($domain) {
            'opportunities' => Opportunity::query()->where('person_id', $person->id)->with('owner:id,name')->latest(),
            'quotes' => Quote::query()->where('person_id', $person->id)->latest('issue_date')->latest('id'),
            'invoices' => SalesDocument::query()->where('person_id', $person->id)->where('type', 'invoice')->latest('issue_date')->latest('id'),
            'installations' => $this->installations($person)->with('commercial:id,name')->latest(),
            'projects' => EngineeringDossier::query()->where(fn ($q) => $q->whereHas('opportunity', fn ($o) => $o->where('person_id', $person->id))->orWhereHas('installation', fn ($i) => $i->whereIn('installations.id', $this->installations($person)->select('installations.id'))))->latest(),
            'documents' => WorkOrderAttachment::query()->where('kind', 'document')->when(auth()->user()?->hasRole('technician'), fn ($q) => $q->whereHas('workOrder', fn ($o) => $o->where('assigned_user_id', auth()->id())))->with('workOrder:id,installation_id,number')->whereHas('workOrder.installation', fn ($i) => $i->whereIn('installations.id', $this->installations($person)->select('installations.id')))->latest(),
            'activity' => $this->activities($person),
        };
    }

    private function activities(Person $person): \Illuminate\Database\Query\Builder
    {
        $activityAt = InstantSql::iso(CrmActivity::class, 'completed_at');
        $activityCreated = InstantSql::legacyIso('created_at');
        $workCompleted = InstantSql::iso(WorkOrder::class, 'completed_at');
        $workStart = InstantSql::iso(WorkOrder::class, 'scheduled_start');
        $eventAt = InstantSql::legacyIso('occurred_at');
        $quoteSent = InstantSql::legacyIso('sent_at');
        $issued = InstantSql::legacyIso('issued_at');
        $activities = CrmActivity::query()->selectRaw("id, title, status, notes AS note, COALESCE($activityAt, $activityCreated) AS happened_at, opportunity_id, 'activity' AS source")
            ->where(fn ($q) => $q->where('person_id', $person->id)->orWhereHas('opportunity', fn ($o) => $o->where('person_id', $person->id))->orWhereHas('installation', fn ($i) => $i->whereIn('installations.id', $this->installations($person)->select('installations.id'))))
            ->when(auth()->user()?->cannot('manage-operations'), fn ($q) => $q->whereNull('installation_id')->whereNull('work_order_id'))
            ->when(auth()->user()?->hasRole('technician'), fn ($q) => $q->where(fn ($a) => $a->whereNull('installation_id')->orWhere('assigned_user_id', auth()->id())));
        $events = OpportunityEvent::query()->selectRaw("id, summary AS title, event_type AS status, note, $eventAt AS happened_at, opportunity_id, 'event' AS source")
            ->whereHas('opportunity', fn ($o) => $o->where('person_id', $person->id));

        $activities->unionAll($events);
        if (auth()->user()?->can('manage-quotes')) {
            $activities->unionAll(Quote::query()->where('person_id', $person->id)->selectRaw("id, 'Presupuesto ' || number AS title, status, NULL AS note, COALESCE($quoteSent, $activityCreated) AS happened_at, NULL AS opportunity_id, 'quote' AS source"));
        }
        if (auth()->user()?->can('manage-installations')) {
            $activities->unionAll(SalesDocument::query()->where('person_id', $person->id)->where('type', 'invoice')->selectRaw("id, 'Factura ' || number AS title, status, NULL AS note, COALESCE($issued, $activityCreated) AS happened_at, NULL AS opportunity_id, 'invoice' AS source"));
        }
        if (auth()->user()?->can('manage-operations')) {
            $activities->unionAll(WorkOrder::query()->whereHas('installation', fn ($i) => $i->whereIn('installations.id', $this->installations($person)->select('installations.id')))
                ->when(auth()->user()?->hasRole('technician'), fn ($q) => $q->where('assigned_user_id', auth()->id()))
                ->selectRaw("id, number || ' · ' || title AS title, status, NULL AS note, COALESCE($workCompleted, $workStart, $activityCreated) AS happened_at, NULL AS opportunity_id, 'work_order' AS source"));
        }

        return DB::query()->fromSub($activities->toBase(), 'customer_activity')->orderByDesc('happened_at')->orderByDesc('id');
    }

    private function row(string $domain, $row): array
    {
        return match ($domain) {
            'opportunities' => ['id' => $row->id, 'title' => $row->title, 'reference' => $row->code, 'status' => $row->stage, 'amount' => (float) $row->estimated_value, 'date' => $row->created_at, 'subtitle' => $row->owner?->name, 'href' => route('opportunities.show', $row, false)],
            'quotes' => ['id' => $row->id, 'title' => $row->number, 'status' => $row->status, 'amount' => (float) $row->amount, 'date' => $row->issue_date, 'href' => route('quotes.show', $row, false)],
            'invoices' => ['id' => $row->id, 'title' => $row->number, 'status' => $row->status, 'amount' => (float) $row->amount, 'date' => $row->issue_date, 'href' => route('sales-documents.show', $row, false)],
            'installations' => ['id' => $row->id, 'title' => $row->project_reference ?: $row->site_name ?: 'Instalación #'.$row->id, 'status' => $row->status, 'date' => $row->created_at, 'subtitle' => collect([$row->site_city, $row->commercial?->name])->filter()->implode(' · '), 'href' => route('installations.show', $row, false)],
            'projects' => ['id' => $row->id, 'title' => $row->code.' · '.$row->title, 'status' => $row->status, 'date' => $row->created_at, 'subtitle' => 'Expediente técnico', 'href' => route('engineering.index', [], false).'#dossier-'.$row->id],
            'documents' => ['id' => $row->id, 'title' => $row->original_name, 'status' => $row->publication_status, 'date' => $row->created_at, 'subtitle' => 'Orden '.$row->workOrder?->number.' · '.round($row->size / 1024).' KB', 'href' => route('work-orders.attachments.download', [$row->work_order_id, $row->id], false)],
            'activity' => ['id' => $row->source.'-'.$row->id, 'title' => $row->title, 'status' => $row->status, 'date' => $row->happened_at ? Carbon::parse($row->happened_at)->toIso8601String() : null, 'subtitle' => $row->note, 'href' => match ($row->source) {
                'event' => route('opportunities.show', $row->opportunity_id, false), 'quote' => route('quotes.show', $row->id, false), 'invoice' => route('sales-documents.show', $row->id, false), 'work_order' => route('work-orders.index', ['work_order' => $row->id], false), default => auth()->user()?->can('manage-operations') ? route('operations.index', ['focus' => $row->id], false) : null
            }],
        };
    }
}
