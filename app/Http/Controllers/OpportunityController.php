<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    private const STAGES = ['new', 'qualified', 'survey', 'proposal', 'negotiation', 'won', 'lost'];

    public function index(): Response
    {
        $opportunities = Opportunity::with([
            'customer:id,first_name,last_name,company_name',
            'installation:id,person_id,project_reference,site_name',
            'owner:id,name',
            'quote:id,opportunity_id,number,status',
        ])->latest()->get();

        $open = $opportunities->whereNotIn('stage', ['won', 'lost']);

        return Inertia::render('opportunities/index', [
            'opportunities' => $opportunities->map(fn (Opportunity $item) => [
                ...$item->toArray(),
                'expected_close_on' => $item->expected_close_on?->toDateString(),
            ]),
            'customers' => Person::where('category', 'customer')->orderBy('company_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name']),
            'installations' => Installation::orderBy('project_reference')->get(['id', 'person_id', 'project_reference', 'site_name']),
            'users' => User::whereIn('role', ['admin', 'commercial'])->orderBy('name')->get(['id', 'name']),
            'metrics' => [
                'pipeline_value' => round((float) $open->sum('estimated_value'), 2),
                'weighted_forecast' => round((float) $open->sum(fn (Opportunity $item) => (float) $item->estimated_value * $item->probability / 100), 2),
                'won_value' => round((float) $opportunities->where('stage', 'won')->sum('estimated_value'), 2),
                'overdue' => $open->filter(fn (Opportunity $item) => $item->expected_close_on?->isPast())->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->ensureInstallationBelongsToCustomer($data);

        DB::transaction(function () use ($data, $request) {
            $opportunity = Opportunity::create([...$data, 'created_by' => $request->user()->id]);
            $opportunity->update(['code' => 'OPP-'.now()->format('Y').'-'.str_pad((string) $opportunity->id, 5, '0', STR_PAD_LEFT)]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Opportunity created.')]);
    }

    public function update(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $data = $this->validated($request);
        $this->ensureInstallationBelongsToCustomer($data);
        abort_if(
            $opportunity->quote()->exists()
                && ((int) $opportunity->person_id !== (int) $data['person_id']
                    || (int) $opportunity->installation_id !== (int) ($data['installation_id'] ?? 0)),
            422,
            __('The customer or installation cannot be changed after preparing a quote.'),
        );
        $opportunity->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Opportunity updated.')]);
    }

    public function destroy(Opportunity $opportunity): RedirectResponse
    {
        abort_if($opportunity->quote()->exists(), 422, __('An opportunity linked to a quote cannot be deleted.'));
        $opportunity->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Opportunity deleted.')]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'person_id' => ['required', Rule::exists('people', 'id')->where('category', 'customer')],
            'installation_id' => ['nullable', 'exists:installations,id'],
            'owner_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['admin', 'commercial']))],
            'title' => ['required', 'string', 'max:255'],
            'stage' => ['required', Rule::in(self::STAGES)],
            'probability' => ['required', 'integer', 'between:0,100'],
            'estimated_value' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'expected_close_on' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
            'lost_reason' => [Rule::requiredIf($request->input('stage') === 'lost'), 'nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function ensureInstallationBelongsToCustomer(array $data): void
    {
        if (empty($data['installation_id'])) {
            return;
        }

        $installation = Installation::findOrFail($data['installation_id']);
        abort_if((int) $installation->person_id !== (int) $data['person_id'], 422, __('The installation does not belong to this customer.'));
    }
}
