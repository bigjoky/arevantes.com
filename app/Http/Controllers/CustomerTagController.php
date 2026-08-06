<?php

namespace App\Http\Controllers;

use App\Models\CrmTag;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerTagController extends Controller
{
    private const COLORS = ['slate', 'red', 'orange', 'amber', 'green', 'blue', 'violet', 'pink'];

    public function store(Request $request, Person $person): RedirectResponse
    {
        abort_unless($person->category === 'customer', 404);
        $data = $request->validate([
            'tag_id' => ['nullable', 'exists:crm_tags,id'],
            'name' => ['required_without:tag_id', 'nullable', 'string', 'max:60'],
            'color' => ['nullable', Rule::in(self::COLORS)],
        ]);
        $tag = isset($data['tag_id'])
            ? CrmTag::findOrFail($data['tag_id'])
            : CrmTag::firstOrCreate(
                ['name' => str($data['name'])->squish()->toString()],
                ['color' => $data['color'] ?? 'slate'],
            );
        $person->tags()->syncWithoutDetaching($tag->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Tag assigned.')]);
    }

    public function destroy(Person $person, CrmTag $tag): RedirectResponse
    {
        abort_unless($person->category === 'customer', 404);
        $person->tags()->detach($tag->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Tag removed.')]);
    }
}
