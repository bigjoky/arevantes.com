<?php

namespace App\Services\Quotes;

use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteStatusHistory;
use App\Models\User;
use App\Support\BusinessLineCatalog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class QuoteWorkspace
{
    public function listing(Request $request): array
    {
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(QuoteStatusWorkflow::STATUSES)],
            'approval' => ['nullable', Rule::in(['not_required', 'pending', 'approved', 'rejected'])],
            'owner' => ['nullable', 'integer', 'exists:users,id'],
            'customer' => ['nullable', 'integer', 'exists:people,id'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', Rule::when($request->filled('date_from'), 'after_or_equal:date_from')],
            'valid_from' => ['nullable', 'date'], 'valid_to' => ['nullable', 'date', Rule::when($request->filled('valid_from'), 'after_or_equal:valid_from')],
            'service_type' => ['nullable', Rule::in(BusinessLineCatalog::INSTALLATION_TYPES)],
            'min_amount' => ['nullable', 'numeric', 'min:0'], 'max_amount' => ['nullable', 'numeric', 'min:0', Rule::when($request->filled('min_amount'), 'gte:min_amount')],
            'sort' => ['nullable', Rule::in(['issue_date', 'number', 'amount', 'valid_until', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([15, 30, 50])], 'page' => ['nullable', 'integer', 'min:1'],
            'view' => ['nullable', Rule::in(['table', 'kanban', 'calendar'])],
        ]);
        abort_if($validator->fails(), 422, $validator->errors()->first());
        $filters = $validator->validated();
        $effective = "CASE WHEN status = 'sent' AND valid_until < ? THEN 'expired' ELSE status END";
        $query = Quote::query()->with(['customer:id,first_name,last_name,company_name,tax_id', 'creator:id,name', 'opportunity:id,code,title,stage']);
        if ($search = $filters['search'] ?? null) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(number) LIKE ?', [$like])->orWhereRaw('LOWER(notes) LIKE ?', [$like])
                ->orWhereHas('customer', fn ($c) => $c->whereRaw('LOWER(company_name) LIKE ?', [$like])->orWhereRaw('LOWER(first_name) LIKE ?', [$like])->orWhereRaw('LOWER(last_name) LIKE ?', [$like])->orWhereRaw('LOWER(tax_id) LIKE ?', [$like])->orWhereRaw("LOWER(first_name || ' ' || COALESCE(last_name, '')) LIKE ?", [$like])->orWhereRaw('LOWER(document_number) LIKE ?', [$like]))
                ->orWhereHas('lines', fn ($line) => $line->whereRaw('LOWER(description) LIKE ?', [$like]))
                ->orWhereHas('opportunity', fn ($o) => $o->whereRaw('LOWER(title) LIKE ?', [$like])->orWhereRaw('LOWER(code) LIKE ?', [$like])));
        }
        if ($status = $filters['status'] ?? null) {
            $query->whereRaw("$effective = ?", [today()->toDateString(), $status]);
        }
        foreach (['approval' => 'approval_status', 'owner' => 'created_by', 'customer' => 'person_id', 'service_type' => 'service_type'] as $key => $column) {
            if (filled($filters[$key] ?? null)) {
                $query->where($column, $filters[$key]);
            }
        }
        foreach (['date_from' => ['issue_date', '>='], 'date_to' => ['issue_date', '<='], 'valid_from' => ['valid_until', '>='], 'valid_to' => ['valid_until', '<='], 'min_amount' => ['amount', '>='], 'max_amount' => ['amount', '<=']] as $key => [$column, $operator]) {
            if (filled($filters[$key] ?? null)) {
                $query->where($column, $operator, $filters[$key]);
            }
        }
        $groups = (clone $query)->reorder()->selectRaw("$effective as effective_status, COUNT(*) as count, SUM(amount) as amount", [today()->toDateString()])->groupBy('effective_status')->get()->keyBy('effective_status');
        $statuses = collect(QuoteStatusWorkflow::STATUSES)->mapWithKeys(fn ($status) => [$status => ['count' => (int) ($groups[$status]->count ?? 0), 'amount' => (string) ($groups[$status]->amount ?? '0.00')]])->all();
        $ids = (clone $query)->reorder()->select('quotes.id');
        $recent = QuoteStatusHistory::with(['quote:id,number', 'changedBy:id,name'])->whereIn('quote_id', $ids)->latest('changed_at')->limit(6)->get();
        $pendingApproval = (clone $query)->where('approval_status', 'pending')->count();
        $quotes = $query->orderBy($filters['sort'] ?? 'issue_date', $filters['direction'] ?? 'desc')->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
        $quotes->getCollection()->each(fn ($q) => $q->setAttribute('effective_status', $q->status === 'sent' && $q->valid_until?->lt(today()) ? 'expired' : $q->status));

        return ['quotes' => $quotes, 'filters' => $filters, 'canApproveQuotes' => Gate::allows('approve-quotes'),
            'summary' => ['total' => array_sum(array_column($statuses, 'count')), 'statuses' => $statuses, 'pending_approval' => $pendingApproval, 'open_value' => (float) $statuses['draft']['amount'] + (float) $statuses['sent']['amount'], 'accepted_value' => (float) $statuses['accepted']['amount']],
            'owners' => User::whereIn('id', Quote::select('created_by'))->orderBy('name')->get(['id', 'name']),
            'customers' => Person::withTrashed()->whereIn('id', Quote::select('person_id'))->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name']),
            'recentActivity' => $recent, 'statuses' => QuoteStatusWorkflow::STATUSES];
    }

    public function activity(Quote $quote): array
    {
        $state = DB::table('quote_status_histories as h')->leftJoin('users as u', 'u.id', '=', 'h.changed_by')->where('h.quote_id', $quote->id)
            ->selectRaw("'status-' || h.id as id, 'status' as type, h.to_status as title, h.reason as detail, u.name as actor, h.changed_at as occurred_at");
        $versions = DB::table('quote_versions as v')->leftJoin('users as u', 'u.id', '=', 'v.created_by')->where('v.quote_id', $quote->id)
            ->selectRaw("'version-' || v.id as id, 'version' as type, CAST(v.version AS TEXT) as title, v.change_note as detail, u.name as actor, v.created_at as occurred_at");
        $deliveries = DB::table('document_deliveries as d')->leftJoin('users as u', 'u.id', '=', 'd.sent_by')->where('d.quote_id', $quote->id)
            ->selectRaw("'delivery-' || d.id as id, 'delivery' as type, d.status as title, d.recipient_email as detail, u.name as actor, COALESCE(d.sent_at, d.created_at) as occurred_at");

        $events = DB::query()->fromSub($state->unionAll($versions)->unionAll($deliveries), 'events')->orderByDesc('occurred_at')->orderByDesc('id')->paginate(15, ['*'], 'activity_page')->withQueryString();
        $events->getCollection()->each(function ($event): void {
            if ($event->occurred_at) {
                $event->occurred_at = Carbon::parse($event->occurred_at, config('app.timezone'))->toISOString();
            }
        });

        return $events->toArray();
    }
}
