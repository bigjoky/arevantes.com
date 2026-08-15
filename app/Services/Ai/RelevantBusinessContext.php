<?php

namespace App\Services\Ai;

use App\Models\BankTransaction;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class RelevantBusinessContext
{
    public function search(User $user, string $query, ?string $pageUrl = null): string
    {
        $tokens = collect(preg_split('/[^\pL\pN@._-]+/u', Str::lower($query)))->filter(fn ($token) => mb_strlen($token) >= 3)->unique()->take(8)->values();
        $sections = collect();

        if (Gate::forUser($user)->allows('view-installations')) {
            $installationId = preg_match('#/installations/(\d+)#', (string) $pageUrl, $match) ? (int) $match[1] : null;
            $installations = Installation::with('customer:id,first_name,last_name,company_name')->when($installationId, fn ($builder) => $builder->whereKey($installationId), fn ($builder) => $this->whereTokens($builder, $tokens, ['project_reference', 'site_name', 'site_address_line_1', 'site_city', 'site_postal_code']))->limit(5)->get();
            if ($installations->isNotEmpty()) {
                $sections->push("Relevant installations:\n".$installations->map(fn ($item) => sprintf('- #%d %s · %s · %s · %.2f kW · customer %s · monitoring %s · today %.3f kWh', $item->id, $item->project_reference ?: $item->site_name ?: 'unnamed', $item->service_type, $item->status, (float) $item->system_power_kw, $item->customer?->company_name ?: trim(($item->customer?->first_name ?? '').' '.($item->customer?->last_name ?? '')) ?: 'unassigned', $item->huawei_status ?: $item->deye_status ?: 'not linked', (float) ($item->huawei_today_energy_kwh ?: $item->deye_today_energy_kwh)))->implode("\n"));
            }
        }

        if (Gate::forUser($user)->allows('manage-customers')) {
            $customers = Person::where('category', 'customer')->tap(fn ($builder) => $this->whereTokens($builder, $tokens, ['first_name', 'last_name', 'company_name', 'email', 'phone', 'tax_id']))->limit(5)->get();
            if ($customers->isNotEmpty()) {
                $sections->push("Relevant customers:\n".$customers->map(fn ($person) => sprintf('- #%d %s · %s · %s · %s · %d installations', $person->id, $person->company_name ?: trim($person->first_name.' '.$person->last_name), $person->email ?: 'no email', $person->phone ?: 'no phone', collect([$person->city, $person->province_state])->filter()->implode(', ') ?: 'no location', $person->installations()->count()))->implode("\n"));
            }

            $opportunities = Opportunity::with(['customer:id,first_name,last_name,company_name', 'owner:id,name'])->tap(fn ($builder) => $this->whereTokens($builder, $tokens, ['code', 'title', 'stage', 'source', 'notes']))->latest()->limit(5)->get();
            if ($opportunities->isNotEmpty()) {
                $sections->push("Relevant sales opportunities:\n".$opportunities->map(fn ($item) => sprintf('- %s %s · %s · %.2f EUR · %d%% · close %s · customer %s · owner %s', $item->code, $item->title, $item->stage, (float) $item->estimated_value, $item->probability, $item->expected_close_on?->format('Y-m-d') ?: 'not set', $item->customer?->company_name ?: trim(($item->customer?->first_name ?? '').' '.($item->customer?->last_name ?? '')) ?: 'unassigned', $item->owner?->name ?: 'unassigned'))->implode("\n"));
            }
        }

        if (Gate::forUser($user)->allows('manage-catalog')) {
            $sales = SalesDocument::with('customer:id,first_name,last_name,company_name')->tap(fn ($builder) => $this->whereTokens($builder, $tokens, ['number', 'status', 'notes']))->latest('issue_date')->limit(5)->get();
            if ($sales->isNotEmpty()) {
                $sections->push("Relevant sales documents:\n".$sales->map(fn ($doc) => sprintf('- %s %s · %s · %.2f EUR · paid %.2f EUR · due %s · customer %s', $doc->type, $doc->number, $doc->status, (float) $doc->amount, (float) $doc->paid_amount, $doc->due_date?->format('Y-m-d') ?: 'not set', $doc->customer?->company_name ?: trim(($doc->customer?->first_name ?? '').' '.($doc->customer?->last_name ?? '')) ?: 'unassigned'))->implode("\n"));
            }
            $purchases = PurchaseDocument::with(['supplier:id,legal_name,trade_name', 'attachment:id,purchase_document_id,extraction_status'])->tap(fn ($builder) => $this->whereTokens($builder, $tokens, ['number', 'supplier_number', 'status', 'notes']))->latest('issue_date')->limit(5)->get();
            if ($purchases->isNotEmpty()) {
                $sections->push("Relevant purchase documents:\n".$purchases->map(fn ($doc) => sprintf('- %s %s · %s · %.2f EUR · paid %.2f EUR · supplier %s · original document %s', $doc->type, $doc->number, $doc->status, (float) $doc->amount, (float) $doc->paid_amount, $doc->supplier?->trade_name ?: $doc->supplier?->legal_name ?: 'unassigned', $doc->attachment?->extraction_status ?: 'not attached'))->implode("\n"));
            }
            $movements = BankTransaction::with('account:id,name')->tap(fn ($builder) => $this->whereTokens($builder, $tokens, ['description', 'reference', 'status']))->latest('booked_on')->limit(5)->get();
            if ($movements->isNotEmpty()) {
                $sections->push("Relevant bank movements:\n".$movements->map(fn ($row) => sprintf('- %s · %s · %.2f %s · %s · %s', $row->booked_on->format('Y-m-d'), Str::limit($row->description, 100), (float) $row->amount, $row->currency, $row->status, $row->account?->name))->implode("\n"));
            }
        }

        return $sections->implode("\n\n") ?: 'No specific CRM records matched this request.';
    }

    private function whereTokens(Builder $builder, $tokens, array $columns): void
    {
        if ($tokens->isEmpty()) {
            $builder->whereRaw('1 = 0');

            return;
        }
        $builder->where(function ($query) use ($tokens, $columns) {
            foreach ($tokens as $token) {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', '%'.$token.'%');
                }
            }
        });
    }
}
