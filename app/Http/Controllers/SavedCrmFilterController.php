<?php

namespace App\Http\Controllers;

use App\Models\SavedCrmFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SavedCrmFilterController extends Controller
{
    private const FILTER_KEYS = [
        'customers' => ['search', 'customer_type', 'lifecycle_status', 'lead_source', 'tag'],
        'opportunities' => ['search', 'stage', 'owner', 'source', 'commercial_kind', 'attention', 'period', 'view', 'status', 'from', 'to', 'sort', 'direction', 'calendar_month'],
    ];

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(array_keys(self::FILTER_KEYS))],
            'name' => ['required', 'string', 'max:80'],
            'filters' => ['required', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $filters = Arr::only($data['filters'], self::FILTER_KEYS[$data['scope']]);

        DB::transaction(function () use ($request, $data, $filters): void {
            if ($data['is_default'] ?? false) {
                SavedCrmFilter::where('user_id', $request->user()->id)
                    ->where('scope', $data['scope'])
                    ->update(['is_default' => false]);
            }

            SavedCrmFilter::updateOrCreate(
                ['user_id' => $request->user()->id, 'scope' => $data['scope'], 'name' => $data['name']],
                ['filters' => $filters, 'is_default' => $data['is_default'] ?? false],
            );
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Filter saved.')]);
    }

    public function destroy(Request $request, SavedCrmFilter $filter): RedirectResponse
    {
        abort_unless($filter->user_id === $request->user()->id, 404);
        $filter->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Filter deleted.')]);
    }
}
