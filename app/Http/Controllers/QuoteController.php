<?php

namespace App\Http\Controllers;

use App\Jobs\GeocodeInstallation;
use App\Models\CrmActivity;
use App\Models\CustomerDocumentAcceptance;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Product;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Catalog\ProductCompositionResolver;
use App\Services\Crm\OpportunitySalesCycle;
use App\Services\Documents\DocumentNumber;
use App\Services\Documents\QuoteBillingPlan;
use App\Services\Inventory\ProjectMaterialPlanner;
use App\Services\Operations\ProjectProfitability;
use App\Services\Quotes\QuoteCalculator;
use App\Services\Quotes\QuoteMaterialReadiness;
use App\Services\Quotes\QuoteOperationalPlanner;
use App\Services\Quotes\QuoteStatusWorkflow;
use App\Services\Work\TechnicianSchedule;
use App\Services\Work\WorkOrderFactory;
use App\Services\Work\WorkOrderTemplateResolver;
use App\Support\BusinessLineCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'sent', 'accepted', 'rejected', 'expired'])],
            'approval' => ['nullable', Rule::in(['not_required', 'pending', 'approved', 'rejected'])],
            'owner' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $query = Quote::query()
            ->with([
                'customer:id,first_name,last_name,company_name',
                'creator:id,name',
                'opportunity:id,code,title,stage',
            ])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query
                ->where(fn ($query) => $query
                    ->where('number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($query) => $query
                        ->where('company_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('opportunity', fn ($query) => $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['approval'] ?? null, fn ($query, string $approval) => $query->where('approval_status', $approval))
            ->when($filters['owner'] ?? null, fn ($query, int $owner) => $query->where('created_by', $owner));

        $summary = [
            'total' => (clone $query)->count(),
            'open_value' => round((float) (clone $query)->whereIn('status', ['draft', 'sent'])->sum('amount'), 2),
            'pending_approval' => (clone $query)->where('approval_status', 'pending')->count(),
            'accepted_value' => round((float) (clone $query)->where('status', 'accepted')->sum('amount'), 2),
        ];

        return Inertia::render('quotes/index', [
            'quotes' => $query->latest('updated_at')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'summary' => $summary,
            'owners' => User::query()->whereIn('role', ['admin', 'commercial'])->orderBy('name')->get(['id', 'name']),
            'canApproveQuotes' => Gate::allows('approve-quotes'),
        ]);
    }

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

    public function show(Quote $quote, QuoteBillingPlan $billing, ProjectProfitability $profitability, QuoteMaterialReadiness $materials): Response
    {
        $quote->load(['customer', 'installation', 'opportunity:id,code,title', 'creator:id,name', 'approver:id,name', 'lines.components.unit:id,symbol', 'versions.createdBy:id,name', 'statusHistory.changedBy:id,name', 'deliveries:id,quote_id,recipient_email,status,sent_at,first_viewed_at,responded_at,revoked_at']);
        [$street, $streetNumber] = $this->splitStreetAddress($quote->customer?->address_line_1);
        $projectProfitability = $quote->installation ? $profitability->for($quote->installation, $quote) : null;
        $quote->setAttribute(
            'actual_margin_amount',
            $projectProfitability && $projectProfitability['has_actuals']
                ? $projectProfitability['actual_margin']
                : null,
        );

        $availableStatuses = array_values(array_diff(
            app(QuoteStatusWorkflow::class)->availableTransitions($quote),
            ['sent'],
        ));
        $canApprove = Gate::allows('approve-quotes')
            && $quote->approval_status === 'pending'
            && (int) $quote->created_by !== (int) request()->user()->id;
        $hasTraceability = $quote->installation_id
            || $quote->salesDocuments()->exists()
            || $quote->deliveries()->exists()
            || CustomerDocumentAcceptance::where('document_type', 'quote')->where('document_id', $quote->id)->exists();

        return Inertia::render('quotes/show', [
            'quote' => $quote,
            'billing' => $billing->summary($quote),
            'materialPreview' => $materials->preview($quote),
            'profitability' => $projectProfitability,
            'capabilities' => [
                'edit' => $quote->status === 'draft',
                'changeStatus' => $availableStatuses !== [],
                'send' => in_array($quote->status, ['draft', 'sent'], true)
                    && in_array($quote->approval_status, ['not_required', 'approved'], true)
                    && ! $quote->customer?->do_not_contact,
                'delete' => $quote->status === 'draft' && ! $hasTraceability,
                'approve' => $canApprove,
                'exportWithoutWatermark' => Gate::allows('manage-admin') && $quote->status === 'draft',
                'startProject' => Gate::allows('manage-installations') && $quote->status === 'accepted' && ! $quote->installation_id,
                'convert' => Gate::allows('manage-installations') && $quote->status === 'accepted',
                'approvalBlockReason' => $quote->approval_status !== 'pending' || $canApprove
                    ? null
                    : (Gate::denies('approve-quotes')
                        ? __('Your profile cannot approve commercial proposals.')
                        : __('Separation of duties: another authorized administrator must approve a proposal you created.')),
            ],
            'availableStatuses' => $availableStatuses,
            'technicians' => User::whereIn('role', ['technician', 'admin'])->orderBy('name')->get(['id', 'name']),
            'projectDefaults' => [
                'service_type' => $quote->service_type,
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

        return Inertia::render('quotes/form', [...$this->formData(), 'quote' => $quote->load('lines.components')]);
    }

    public function store(Request $request, QuoteCalculator $calculator, DocumentNumber $numbers, QuoteStatusWorkflow $workflow, OpportunitySalesCycle $salesCycle): RedirectResponse
    {
        $data = $this->validateDocument($request);
        $quote = DB::transaction(function () use ($request, $data, $calculator, $numbers, $workflow, $salesCycle) {
            $issued = $numbers->next('quote', $data['issue_date']);
            $quote = $this->persist(new Quote, $data, $calculator, ['number' => $issued['number'], 'version' => 1, 'status' => 'draft', 'document_series_id' => $issued['series']->id, 'created_by' => $request->user()->id], $request->user()->id);
            $this->syncOpportunity($quote, $request, $salesCycle);
            $workflow->recordInitial($quote, $request->user());

            return $quote;
        });

        return to_route('quotes.show', $quote)->with('toast', ['type' => 'success', 'message' => __('Proposal saved as draft.')]);
    }

    public function updateDocument(Request $request, Quote $quote, QuoteCalculator $calculator): RedirectResponse
    {
        abort_unless($quote->status === 'draft', 422, __('Only draft quotes can be edited.'));
        DB::transaction(fn () => $this->persist($quote, $this->validateDocument($request, $quote), $calculator, ['version' => $quote->version + 1], $request->user()->id));

        return to_route('quotes.show', $quote)->with('toast', ['type' => 'success', 'message' => __('Draft changes saved.')]);
    }

    public function updateStatus(Request $request, Quote $quote, QuoteStatusWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'rejected', 'expired'])]]);
        $workflow->apply($quote, $data['status'], $request->user(), 'internal');

        return back()->with('toast', ['type' => 'success', 'message' => __('Quote updated.')]);
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        abort_unless($quote->status === 'draft', 422, __('Only draft quotes can be deleted.'));
        abort_if($quote->installation_id || $quote->salesDocuments()->exists() || $quote->deliveries()->exists(), 422, __('This quote has commercial traceability and cannot be deleted.'));
        abort_if(CustomerDocumentAcceptance::where('document_type', 'quote')->where('document_id', $quote->id)->exists(), 422, __('An accepted quote cannot be deleted.'));
        $quote->delete();

        return to_route('quotes.index')->with('toast', ['type' => 'success', 'message' => __('Quote deleted.')]);
    }

    public function approve(Request $request, Quote $quote): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'approval_notes' => ['nullable', 'string', 'max:2000']]);
        abort_unless($quote->approval_status === 'pending', 422, __('This quote is not pending approval.'));
        abort_if((int) $quote->created_by === (int) $request->user()->id, 422, __('A quote must be approved by a different user.'));

        $quote->update([
            'approval_status' => $data['decision'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approval_notes' => $data['approval_notes'] ?? null,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Approval decision recorded.')]);
    }

    public function startProject(Request $request, Quote $quote, TechnicianSchedule $schedule, ProjectMaterialPlanner $materials, QuoteOperationalPlanner $operations, WorkOrderFactory $orders, WorkOrderTemplateResolver $templates): RedirectResponse
    {
        abort_unless($quote->status === 'accepted', 422, __('Only accepted quotes can start a project.'));
        abort_if($quote->installation_id, 422, __('This quote already has an installation.'));

        $data = $request->validate([
            'service_type' => ['required', Rule::in(BusinessLineCatalog::INSTALLATION_TYPES)],
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
        abort_if($quote->service_type && $quote->service_type !== $data['service_type'], 422, __('The project business line must match the accepted quote.'));
        $scheduledAt = Carbon::parse($data['scheduled_at']);
        $surveyTemplate = $templates->resolveForServiceType($data['service_type'], 'survey');
        $duration = $surveyTemplate?->estimated_duration_minutes ?? 120;
        $scheduledEnd = $scheduledAt->copy()->addMinutes($duration);
        $schedule->assertAvailable(isset($data['technician_user_id']) ? (int) $data['technician_user_id'] : null, $scheduledAt, $scheduledEnd, null, $surveyTemplate?->required_skills ?? []);

        $installation = DB::transaction(function () use ($request, $quote, $data, $scheduledAt, $scheduledEnd, $materials, $operations, $orders, $surveyTemplate): Installation {
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
                'financial_status' => $lockedQuote->financial_status,
                'financially_cleared_at' => $lockedQuote->financially_cleared_at,
                'site_notes' => __('Created from accepted quote :number.', ['number' => $lockedQuote->number]),
            ]);
            $installation->update(['project_reference' => 'PRJ-'.now()->format('Y').'-'.str_pad((string) $installation->id, 5, '0', STR_PAD_LEFT)]);

            $order = $orders->create([
                'installation_id' => $installation->id,
                'work_order_template_id' => $surveyTemplate?->id,
                'assigned_user_id' => $data['technician_user_id'] ?? null,
                'title' => __('Initial technical survey'),
                'kind' => 'survey',
                'scheduled_start' => $scheduledAt,
                'scheduled_end' => $scheduledEnd,
                'instructions' => __('Survey generated from accepted quote :number. Validate the site before installation planning.', ['number' => $lockedQuote->number]),
            ], $request->user()->id);
            $order->activities()->update(['opportunity_id' => $opportunity?->id, 'priority' => 'high']);

            $lockedQuote->update(['installation_id' => $installation->id]);
            $opportunity?->update(['installation_id' => $installation->id]);
            SalesDocument::where('quote_id', $lockedQuote->id)->update(['installation_id' => $installation->id]);
            $materials->syncFromQuote($lockedQuote, $installation);
            $operations->sync($lockedQuote, $installation, $request->user()->id);

            return $installation;
        });
        GeocodeInstallation::dispatch($installation->id)->afterCommit();

        return to_route('installations.show', $installation)->with('toast', ['type' => 'success', 'message' => __('Project, survey and work order created.')]);
    }

    private function formData(): array
    {
        $products = Product::with(['unit:id,symbol', 'taxRate:id,rate', 'components.product.unit:id,symbol'])->where('is_active', true)->orderBy('name')->get();
        $resolver = app(ProductCompositionResolver::class);
        $products->each(fn (Product $product) => $product->setAttribute('resolved_components', $resolver->resolve($product)));

        return [
            'customers' => Person::where('category', 'customer')->orderBy('company_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name', 'payment_terms_days']),
            'installations' => Installation::orderBy('project_reference')->get(['id', 'person_id', 'project_reference', 'site_name']),
            'products' => $products,
            'acceptancePaymentDefaults' => config('crm.acceptance_payment'),
        ];
    }

    private function validateDocument(Request $request, ?Quote $quote = null): array
    {
        $request->merge([
            'lines' => collect($request->input('lines', []))->map(function (array $line, int $index): array {
                return [
                    ...$line,
                    'client_key' => filled($line['client_key'] ?? null) ? (string) $line['client_key'] : 'line-'.($index + 1),
                    'components' => collect($line['components'] ?? [])->map(fn (array $component, int $componentIndex): array => [
                        ...$component,
                        'client_key' => filled($component['client_key'] ?? null) ? (string) $component['client_key'] : 'component-'.($componentIndex + 1),
                        'parent_client_key' => filled($component['parent_client_key'] ?? null) ? (string) $component['parent_client_key'] : null,
                    ])->all(),
                ];
            })->all(),
        ]);

        $data = $request->validate([
            'opportunity_id' => ['nullable', 'exists:opportunities,id', Rule::unique('quotes', 'opportunity_id')->ignore($quote?->id)],
            'service_type' => ['nullable', Rule::in(BusinessLineCatalog::INSTALLATION_TYPES)],
            'person_id' => ['required', 'exists:people,id'], 'installation_id' => ['nullable', 'exists:installations,id'],
            'issue_date' => ['required', 'date'], 'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'], 'notes' => ['nullable', 'string'],
            'acceptance_payment_percentage' => ['sometimes', 'numeric', 'between:0,100'],
            'acceptance_payment_due_days' => ['sometimes', 'integer', 'between:0,365'],
            'change_note' => [$quote ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.client_key' => ['required', 'string', 'max:100', 'distinct'],
            'lines.*.product_id' => ['nullable', 'exists:products,id'], 'lines.*.unit_id' => ['nullable', 'exists:units,id'],
            'lines.*.tax_rate_id' => ['nullable', 'exists:tax_rates,id'], 'lines.*.description' => ['required', 'string'], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.total_with_tax' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,3'], 'lines.*.discount_percent' => ['required', 'numeric', 'between:0,100'], 'lines.*.tax_percent' => ['required', 'numeric', 'between:0,100'],
            'lines.*.selection_mode' => ['sometimes', Rule::in(['included', 'optional', 'alternative'])],
            'lines.*.option_group' => ['nullable', 'string', 'max:100'],
            'lines.*.is_selected' => ['sometimes', 'boolean'],
            'lines.*.components' => ['sometimes', 'array'],
            'lines.*.components.*.client_key' => ['required', 'string', 'max:100'],
            'lines.*.components.*.parent_client_key' => ['nullable', 'string', 'max:100'],
            'lines.*.components.*.product_id' => ['nullable', 'exists:products,id'],
            'lines.*.components.*.unit_id' => ['nullable', 'exists:units,id'],
            'lines.*.components.*.kind' => ['required', Rule::in(['material', 'labor', 'service', 'document', 'other'])],
            'lines.*.components.*.fulfillment_kind' => ['required', Rule::in(['stock', 'activity', 'recurring', 'none'])],
            'lines.*.components.*.visibility' => ['required', Rule::in(['detailed', 'summary', 'hidden'])],
            'lines.*.components.*.description' => ['required', 'string', 'max:1000'],
            'lines.*.components.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.components.*.planned_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.components.*.interval_months' => ['nullable', 'integer', 'between:1,120'],
        ]);

        foreach ($data['lines'] as $index => $line) {
            if (isset($line['total_with_tax']) && (float) $line['total_with_tax'] > 0 && (float) $line['discount_percent'] >= 100) {
                throw ValidationException::withMessages([
                    "lines.{$index}.total_with_tax" => __('Un total con IVA positivo requiere un descuento inferior al 100%.'),
                ]);
            }
        }

        return $this->normalizeComponentHierarchy($data);
    }

    private function normalizeComponentHierarchy(array $data): array
    {
        foreach ($data['lines'] as $index => &$line) {
            $components = collect($line['components'] ?? []);
            if ($components->pluck('client_key')->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages([
                    "lines.{$index}.components" => __('Component identifiers must be unique within each proposal line.'),
                ]);
            }
            $componentsByKey = $components->keyBy('client_key');

            $line['components'] ??= [];
            foreach ($line['components'] as $componentIndex => &$component) {
                $depth = 2;
                $parentKey = $component['parent_client_key'] ?? null;
                $visited = [$component['client_key'] => true];

                while ($parentKey !== null) {
                    if (! $componentsByKey->has($parentKey)) {
                        throw ValidationException::withMessages([
                            "lines.{$index}.components.{$componentIndex}.parent_client_key" => __('The parent component no longer exists.'),
                        ]);
                    }

                    if (isset($visited[$parentKey])) {
                        throw ValidationException::withMessages([
                            "lines.{$index}.components.{$componentIndex}.parent_client_key" => __('Components cannot contain a circular hierarchy.'),
                        ]);
                    }

                    $visited[$parentKey] = true;
                    $depth++;

                    if ($depth > 5) {
                        throw ValidationException::withMessages([
                            "lines.{$index}.components.{$componentIndex}.parent_client_key" => __('Proposal components can be nested up to level five.'),
                        ]);
                    }

                    $parentKey = $componentsByKey->get($parentKey)['parent_client_key'] ?? null;
                }

                $component['depth'] = $depth;
            }
            unset($component);

            $line['depth'] = 1;
            $line['parent_client_key'] = null;
        }
        unset($line);

        return $data;
    }

    /** @param array<int, array<string, mixed>> $components */
    private function componentCost(array $components): float
    {
        $componentsByKey = collect($components)->keyBy('client_key');

        return collect($components)->sum(function (array $component) use ($componentsByKey): float {
            $quantity = (float) $component['quantity'];
            $parentKey = $component['parent_client_key'] ?? null;

            while ($parentKey !== null) {
                $parent = $componentsByKey->get($parentKey);
                if (! $parent) {
                    throw ValidationException::withMessages([
                        'components' => __('The parent component no longer exists.'),
                    ]);
                }

                $quantity *= (float) $parent['quantity'];
                $parentKey = $parent['parent_client_key'] ?? null;
            }

            return $quantity * (float) $component['planned_unit_cost'];
        });
    }

    private function persist(Quote $quote, array $data, QuoteCalculator $calculator, array $extra = [], ?int $actorId = null): Quote
    {
        $changeNote = $data['change_note'] ?? ($quote->exists ? null : __('Initial quote created.'));
        unset($data['change_note']);
        $data['acceptance_payment_percentage'] ??= config('crm.acceptance_payment.percentage');
        $data['lines'] = collect($data['lines'])->map(function (array $line): array {
            $mode = $line['selection_mode'] ?? 'included';

            return [
                ...$line,
                'selection_mode' => $mode,
                'option_group' => $line['option_group'] ?? null,
                'is_selected' => $mode === 'included' ? true : (bool) ($line['is_selected'] ?? false),
            ];
        })->all();
        $alternativeLines = collect($data['lines'])->where('selection_mode', 'alternative');
        abort_if($alternativeLines->contains(fn (array $line) => blank($line['option_group'] ?? null)), 422, __('Alternative lines require a group name.'));
        abort_if($alternativeLines->filter(fn (array $line) => filter_var($line['is_selected'] ?? false, FILTER_VALIDATE_BOOL))->groupBy('option_group')->contains(fn ($lines) => $lines->count() > 1), 422, __('Only one line can be selected in each alternative group.'));
        $installation = isset($data['installation_id']) ? Installation::find($data['installation_id']) : null;
        abort_if($installation?->person_id && $installation->person_id !== (int) $data['person_id'], 422, __('The installation does not belong to the selected customer.'));
        abort_if($installation && filled($data['service_type'] ?? null) && $installation->service_type !== $data['service_type'], 422, __('The quote business line does not match the selected installation.'));
        $customer = Person::findOrFail($data['person_id']);
        $data['acceptance_payment_due_days'] ??= $customer->payment_terms_days ?? config('crm.acceptance_payment.due_days');
        $opportunity = isset($data['opportunity_id']) ? Opportunity::find($data['opportunity_id']) : null;
        abort_if($opportunity && ((int) $opportunity->person_id !== (int) $data['person_id'] || ($opportunity->installation_id && (int) $opportunity->installation_id !== (int) ($data['installation_id'] ?? 0))), 422, __('The quote does not match the selected opportunity.'));
        $productIds = collect($data['lines'])->pluck('product_id')
            ->merge(collect($data['lines'])->flatMap(fn (array $line) => collect($line['components'] ?? [])->pluck('product_id')))
            ->filter()->unique();
        if (filled($data['service_type'] ?? null)) {
            $hasMismatchedProduct = Product::whereIn('id', $productIds)->get(['id', 'business_lines'])
                ->contains(fn (Product $product) => filled($product->business_lines) && ! in_array($data['service_type'], $product->business_lines, true));
            abort_if($hasMismatchedProduct, 422, __('One or more catalog items do not belong to the selected business line.'));
        }
        $productCosts = Product::whereIn('id', $productIds)->pluck('cost_price', 'id');
        $data['lines'] = collect($data['lines'])->map(function (array $line) use ($productCosts): array {
            $line['components'] = collect($line['components'] ?? [])->map(function (array $component, int $position) use ($productCosts): array {
                return [
                    ...$component,
                    'position' => $position + 1,
                    'planned_unit_cost' => (float) ($productCosts->get((int) ($component['product_id'] ?? 0)) ?? ($component['planned_unit_cost'] ?? 0)),
                ];
            })->all();

            return $line;
        })->all();
        $totals = $calculator->calculate($data['lines']);
        $lineCost = function (array $line) use ($productCosts): float {
            $components = collect($line['components'] ?? []);
            if ($components->isNotEmpty()) {
                return (float) $line['quantity'] * $this->componentCost($components->all());
            }

            return (float) ($productCosts->get((int) ($line['product_id'] ?? 0)) ?? 0) * (float) $line['quantity'];
        };
        $cost = collect($data['lines'])->filter(fn (array $line) => filter_var($line['is_selected'] ?? true, FILTER_VALIDATE_BOOL))->sum($lineCost);
        $netRevenue = (float) $totals['subtotal'] - (float) $totals['discount_amount'];
        $margin = round($netRevenue - $cost, 2);
        $approvalCost = collect($data['lines'])->sum($lineCost);
        $approvalNetRevenue = (float) collect($totals['lines'])->sum(fn (array $line) => (float) $line['subtotal'] - (float) $line['discount_amount']);
        $approvalMarginPercent = $approvalNetRevenue > 0 ? ($approvalNetRevenue - $approvalCost) / $approvalNetRevenue * 100 : 0;
        $maxDiscount = (float) collect($data['lines'])->max('discount_percent');
        $approval = config('crm.quote_approval');
        $maxPossibleAmount = (float) collect($totals['lines'])->sum('total');
        $requiresApproval = $maxPossibleAmount >= $approval['amount_threshold']
            || $maxDiscount >= $approval['discount_threshold']
            || $approvalMarginPercent < $approval['margin_threshold'];
        unset($data['lines']);
        $quote->fill([...$data, ...$extra, ...array_diff_key($totals, ['lines' => true]), 'expected_margin_amount' => $margin, 'approval_status' => $requiresApproval ? 'pending' : 'not_required', 'approved_by' => null, 'approved_at' => null, 'approval_notes' => null, 'customer_snapshot' => $customer->customerSnapshot()])->save();
        $quote->lines()->delete();
        foreach ($totals['lines'] as $lineData) {
            $components = $lineData['components'] ?? [];
            unset($lineData['components'], $lineData['client_key'], $lineData['parent_client_key']);
            $lineData['parent_line_id'] = null;
            $lineData['depth'] = 1;
            $line = $quote->lines()->create($lineData);
            $createdComponents = [];
            foreach (collect($components)->sortBy('depth') as $componentData) {
                $componentClientKey = $componentData['client_key'];
                $parentClientKey = $componentData['parent_client_key'] ?? null;
                unset($componentData['client_key'], $componentData['parent_client_key']);
                $componentData['parent_component_id'] = $parentClientKey ? $createdComponents[$parentClientKey] : null;
                $component = $line->components()->create($componentData);
                $createdComponents[$componentClientKey] = $component->id;
            }
        }
        $quote->versions()->updateOrCreate(['version' => $quote->version], [
            'created_by' => $actorId,
            'change_note' => $changeNote,
            'service_type' => $quote->service_type,
            'subtotal' => $quote->subtotal,
            'discount_amount' => $quote->discount_amount,
            'tax_amount' => $quote->tax_amount,
            'amount' => $quote->amount,
            'expected_margin_amount' => $quote->expected_margin_amount,
            'lines' => $totals['lines'],
            'notes' => $quote->notes,
        ]);

        return $quote;
    }

    private function syncOpportunity(Quote $quote, Request $request, OpportunitySalesCycle $salesCycle): void
    {
        $opportunity = $quote->opportunity;
        if (! $opportunity) {
            return;
        }

        if (! in_array($opportunity->stage, ['won', 'lost'], true)) {
            $salesCycle->assertCanTransition($opportunity, 'proposal');
            $opportunity->stageChangeActorId = $request->user()->id;
            $opportunity->stageChangeReason = __('Initial quote :number created.', ['number' => $quote->number]);
            $opportunity->update(['stage' => 'proposal', 'probability' => max(60, $opportunity->probability), 'updated_by' => $request->user()->id]);
            $salesCycle->synchronizeStage($opportunity, $request->user());
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
