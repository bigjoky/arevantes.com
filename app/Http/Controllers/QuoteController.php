<?php

namespace App\Http\Controllers;

use App\Jobs\GeocodeInstallation;
use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Documents\DocumentNumber;
use App\Services\Documents\QuoteBillingPlan;
use App\Services\Inventory\ProjectMaterialPlanner;
use App\Services\Quotes\QuoteCalculator;
use App\Services\Quotes\QuoteStatusWorkflow;
use App\Services\Work\ChecklistTemplate;
use App\Services\Work\TechnicianSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function create(Request $request): Response
    {
        $opportunity = Opportunity::with('quote:id,opportunity_id')->whereKey($request->integer('opportunity'))->first();
        abort_if($opportunity?->quote, 422, __('This opportunity already has a quote.'));
        $customerId = $opportunity?->person_id ?: Person::where('category', 'customer')->whereKey($request->integer('customer'))->value('id');
        $taxRate = TaxRate::where('is_active', true)->orderByRaw('abs(rate - 21)')->first(['id', 'rate']);

        return Inertia::render('quotes/form', [
            ...$this->formData(),
            'defaultCustomerId' => $customerId,
            'opportunityDraft' => $opportunity ? [
                'id' => $opportunity->id,
                'code' => $opportunity->code,
                'installation_id' => $opportunity->installation_id,
                'title' => $opportunity->title,
                'estimated_value' => (float) $opportunity->estimated_value,
                'notes' => $opportunity->notes,
                'tax_rate_id' => $taxRate?->id,
                'tax_percent' => (float) ($taxRate?->rate ?? 0),
            ] : null,
        ]);
    }

    public function show(Quote $quote, QuoteBillingPlan $billing): Response
    {
        $quote->load(['customer', 'installation', 'opportunity:id,code,title', 'lines', 'deliveries:id,quote_id,recipient_email,status,sent_at,first_viewed_at,responded_at']);
        [$street, $streetNumber] = $this->splitStreetAddress($quote->customer?->address_line_1);

        return Inertia::render('quotes/show', [
            'quote' => $quote,
            'billing' => $billing->summary($quote),
            'technicians' => User::whereIn('role', ['technician', 'admin'])->orderBy('name')->get(['id', 'name']),
            'projectDefaults' => [
                'site_name' => $quote->customer?->company_name ?: trim(($quote->customer?->first_name ?? '').' '.($quote->customer?->last_name ?? '')),
                'site_address_line_1' => $street,
                'site_street_number' => $streetNumber,
                'site_address_line_2' => $quote->customer?->address_line_2,
                'site_city' => $quote->customer?->city,
                'site_locality' => $quote->customer?->locality,
                'site_province_state' => $quote->customer?->province_state,
                'site_postal_code' => $quote->customer?->postal_code,
                'site_country' => $quote->customer?->country ?: 'Spain',
            ],
        ]);
    }

    public function edit(Quote $quote): Response
    {
        abort_unless($quote->status === 'draft', 422, __('Only draft quotes can be edited.'));

        return Inertia::render('quotes/form', [...$this->formData(), 'quote' => $quote->load('lines')]);
    }

    public function store(Request $request, QuoteCalculator $calculator, DocumentNumber $numbers): RedirectResponse
    {
        $data = $this->validateDocument($request);
        $quote = DB::transaction(function () use ($request, $data, $calculator, $numbers) {
            $issued = $numbers->next('quote', $data['issue_date']);
            $quote = $this->persist(new Quote, $data, $calculator, ['number' => $issued['number'], 'document_series_id' => $issued['series']->id, 'created_by' => $request->user()->id]);
            $this->syncOpportunity($quote, $request);

            return $quote;
        });

        return to_route('quotes.show', $quote)->with('toast', ['type' => 'success', 'message' => __('Quote created.')]);
    }

    public function updateDocument(Request $request, Quote $quote, QuoteCalculator $calculator): RedirectResponse
    {
        abort_unless($quote->status === 'draft', 422, __('Only draft quotes can be edited.'));
        DB::transaction(fn () => $this->persist($quote, $this->validateDocument($request, $quote), $calculator));

        return to_route('quotes.show', $quote)->with('toast', ['type' => 'success', 'message' => __('Quote updated.')]);
    }

    public function updateStatus(Request $request, Quote $quote, QuoteStatusWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'sent', 'accepted', 'rejected', 'expired'])]]);
        $workflow->apply($quote, $data['status']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Quote updated.')]);
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        $quote->delete();

        return to_route('operations.index')->with('toast', ['type' => 'success', 'message' => __('Quote deleted.')]);
    }

    public function startProject(Request $request, Quote $quote, ChecklistTemplate $template, TechnicianSchedule $schedule, ProjectMaterialPlanner $materials): RedirectResponse
    {
        abort_unless($quote->status === 'accepted', 422, __('Only accepted quotes can start a project.'));
        abort_if($quote->installation_id, 422, __('This quote already has an installation.'));

        $data = $request->validate([
            'service_type' => ['required', Rule::in(['solar', 'ev_charger', 'hvac', 'electrical'])],
            'technician_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['technician', 'admin']))],
            'scheduled_at' => ['required', 'date', 'after_or_equal:today'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_address_line_1' => ['required', 'string', 'max:255'],
            'site_street_number' => ['nullable', 'string', 'max:30'],
            'site_address_line_2' => ['nullable', 'string', 'max:255'],
            'site_city' => ['nullable', 'string', 'max:255'],
            'site_locality' => ['nullable', 'string', 'max:255'],
            'site_province_state' => ['nullable', 'string', 'max:255'],
            'site_postal_code' => ['nullable', 'string', 'max:20'],
            'site_country' => ['required', 'string', 'max:255'],
        ]);
        $scheduledAt = Carbon::parse($data['scheduled_at']);
        $schedule->assertAvailable(isset($data['technician_user_id']) ? (int) $data['technician_user_id'] : null, $scheduledAt, $scheduledAt->copy()->addHours(2));

        $installation = DB::transaction(function () use ($request, $quote, $data, $template, $scheduledAt, $materials): Installation {
            $lockedQuote = Quote::lockForUpdate()->findOrFail($quote->id);
            abort_if($lockedQuote->installation_id, 422, __('This quote already has an installation.'));
            $opportunity = $lockedQuote->opportunity;

            $installation = Installation::create([
                'person_id' => $lockedQuote->person_id,
                'commercial_user_id' => $opportunity?->owner_user_id,
                'technician_user_id' => $data['technician_user_id'] ?? null,
                'service_type' => $data['service_type'],
                'status' => 'survey_booked',
                'site_name' => $data['site_name'] ?? null,
                'site_address_line_1' => $data['site_address_line_1'],
                'site_street_number' => $data['site_street_number'] ?? null,
                'site_address_line_2' => $data['site_address_line_2'] ?? null,
                'site_city' => $data['site_city'] ?? null,
                'site_locality' => $data['site_locality'] ?? null,
                'site_province_state' => $data['site_province_state'] ?? null,
                'site_postal_code' => $data['site_postal_code'] ?? null,
                'site_country' => $data['site_country'],
                'survey_date' => $scheduledAt->toDateString(),
                'estimated_budget' => $lockedQuote->amount,
                'site_notes' => __('Created from accepted quote :number.', ['number' => $lockedQuote->number]),
            ]);
            $installation->update(['project_reference' => 'PRJ-'.now()->format('Y').'-'.str_pad((string) $installation->id, 5, '0', STR_PAD_LEFT)]);

            $order = WorkOrder::create([
                'installation_id' => $installation->id,
                'assigned_user_id' => $data['technician_user_id'] ?? null,
                'created_by' => $request->user()->id,
                'number' => 'TMP-'.str()->uuid(),
                'title' => __('Initial technical survey'),
                'kind' => 'survey',
                'status' => 'planned',
                'scheduled_start' => $scheduledAt,
                'scheduled_end' => $scheduledAt->copy()->addHours(2),
                'instructions' => __('Survey generated from accepted quote :number. Validate the site before installation planning.', ['number' => $lockedQuote->number]),
            ]);
            $order->update(['number' => 'WO-'.now()->format('Y').'-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT)]);
            $order->checklistItems()->createMany(collect($template->for($installation->service_type))->map(fn ($item, $position) => [...$item, 'position' => $position + 1])->all());

            CrmActivity::create([
                'installation_id' => $installation->id,
                'work_order_id' => $order->id,
                'person_id' => $lockedQuote->person_id,
                'opportunity_id' => $opportunity?->id,
                'assigned_user_id' => $data['technician_user_id'] ?? null,
                'created_by' => $request->user()->id,
                'type' => 'visit',
                'title' => __('Initial technical survey'),
                'notes' => __('Linked to work order :number.', ['number' => $order->number]),
                'priority' => 'high',
                'status' => 'pending',
                'starts_at' => $scheduledAt,
                'due_at' => $scheduledAt->copy()->addHours(2),
            ]);

            $lockedQuote->update(['installation_id' => $installation->id]);
            $opportunity?->update(['installation_id' => $installation->id]);
            $materials->syncFromQuote($lockedQuote, $installation);

            return $installation;
        });
        GeocodeInstallation::dispatch($installation->id)->afterCommit();

        return to_route('installations.show', $installation)->with('toast', ['type' => 'success', 'message' => __('Project, survey and work order created.')]);
    }

    private function formData(): array
    {
        return [
            'customers' => Person::where('category', 'customer')->orderBy('company_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name']),
            'installations' => Installation::orderBy('project_reference')->get(['id', 'person_id', 'project_reference', 'site_name']),
            'products' => Product::with(['unit:id,symbol', 'taxRate:id,rate'])->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function validateDocument(Request $request, ?Quote $quote = null): array
    {
        return $request->validate([
            'opportunity_id' => ['nullable', 'exists:opportunities,id', Rule::unique('quotes', 'opportunity_id')->ignore($quote?->id)],
            'person_id' => ['required', 'exists:people,id'], 'installation_id' => ['nullable', 'exists:installations,id'],
            'issue_date' => ['required', 'date'], 'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'], 'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['nullable', 'exists:products,id'], 'lines.*.unit_id' => ['nullable', 'exists:units,id'],
            'lines.*.tax_rate_id' => ['nullable', 'exists:tax_rates,id'], 'lines.*.description' => ['required', 'string'], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'], 'lines.*.discount_percent' => ['required', 'numeric', 'between:0,100'], 'lines.*.tax_percent' => ['required', 'numeric', 'between:0,100'],
        ]);
    }

    private function persist(Quote $quote, array $data, QuoteCalculator $calculator, array $extra = []): Quote
    {
        $installation = isset($data['installation_id']) ? Installation::find($data['installation_id']) : null;
        abort_if($installation?->person_id && $installation->person_id !== (int) $data['person_id'], 422, __('The installation does not belong to the selected customer.'));
        $customer = Person::findOrFail($data['person_id']);
        $opportunity = isset($data['opportunity_id']) ? Opportunity::find($data['opportunity_id']) : null;
        abort_if($opportunity && ((int) $opportunity->person_id !== (int) $data['person_id'] || ($opportunity->installation_id && (int) $opportunity->installation_id !== (int) ($data['installation_id'] ?? 0))), 422, __('The quote does not match the selected opportunity.'));
        $totals = $calculator->calculate($data['lines']);
        unset($data['lines']);
        $quote->fill([...$data, ...$extra, ...array_diff_key($totals, ['lines' => true]), 'customer_snapshot' => $customer->only(['first_name', 'last_name', 'company_name', 'tax_id', 'document_number', 'billing_email', 'email', 'address_line_1', 'address_line_2', 'postal_code', 'city', 'province_state', 'country'])])->save();
        $quote->lines()->delete();
        $quote->lines()->createMany($totals['lines']);

        return $quote;
    }

    private function syncOpportunity(Quote $quote, Request $request): void
    {
        $opportunity = $quote->opportunity;
        if (! $opportunity) {
            return;
        }

        if (! in_array($opportunity->stage, ['won', 'lost'], true)) {
            $opportunity->update(['stage' => 'proposal', 'probability' => max(60, $opportunity->probability)]);
        }

        CrmActivity::create([
            'person_id' => $quote->person_id,
            'installation_id' => $quote->installation_id,
            'opportunity_id' => $opportunity->id,
            'assigned_user_id' => $opportunity->owner_user_id,
            'created_by' => $request->user()->id,
            'type' => 'reminder',
            'title' => __('Follow up quote :number', ['number' => $quote->number]),
            'notes' => __('Generated from opportunity :code.', ['code' => $opportunity->code]),
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDays(7),
        ]);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function splitStreetAddress(?string $address): array
    {
        if (! $address) {
            return [null, null];
        }

        if (preg_match('/^(.*?)[,\s]+(\d+[A-Za-z]?(?:[\s\/-].*)?)$/u', trim($address), $matches)) {
            return [trim($matches[1]), trim($matches[2])];
        }

        return [$address, null];
    }
}
