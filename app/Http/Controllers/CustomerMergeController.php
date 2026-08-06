<?php

namespace App\Http\Controllers;

use App\Models\CrmActivity;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerMergeController extends Controller
{
    public function __invoke(Request $request, Person $person): RedirectResponse
    {
        abort_unless($person->category === 'customer', 404);
        $data = $request->validate(['duplicate_id' => ['required', 'integer', 'exists:people,id']]);
        $duplicate = Person::where('category', 'customer')->findOrFail($data['duplicate_id']);
        abort_if($duplicate->is($person), 422, __('A customer cannot be merged with itself.'));

        DB::transaction(function () use ($person, $duplicate, $request): void {
            $installationLinks = DB::table('installation_person')->where('person_id', $duplicate->id)->get();
            foreach (['installations', 'opportunities', 'quotes', 'sales_documents', 'crm_activities', 'customer_contacts', 'customer_consents', 'customer_portal_accesses'] as $table) {
                DB::table($table)->where('person_id', $duplicate->id)->update(['person_id' => $person->id]);
            }
            foreach ($installationLinks as $link) {
                DB::table('installation_person')->upsert([[
                    'installation_id' => $link->installation_id,
                    'person_id' => $person->id,
                    'role' => $link->role,
                    'ownership_percentage' => $link->ownership_percentage,
                    'is_primary' => $link->is_primary,
                    'created_at' => $link->created_at,
                    'updated_at' => now(),
                ]], ['installation_id', 'person_id'], ['role', 'ownership_percentage', 'is_primary', 'updated_at']);
            }
            DB::table('installation_person')->where('person_id', $duplicate->id)->delete();
            $primaryContact = DB::table('customer_contacts')->where('person_id', $person->id)->where('is_primary', true)->orderBy('id')->value('id');
            if ($primaryContact) {
                DB::table('customer_contacts')->where('person_id', $person->id)->where('is_primary', true)->where('id', '!=', $primaryContact)->update(['is_primary' => false]);
            }

            foreach (DB::table('crm_tag_person')->where('person_id', $duplicate->id)->pluck('crm_tag_id') as $tagId) {
                DB::table('crm_tag_person')->insertOrIgnore(['person_id' => $person->id, 'crm_tag_id' => $tagId]);
            }
            DB::table('crm_tag_person')->where('person_id', $duplicate->id)->delete();

            $person->update([
                'email' => $person->email ?: $duplicate->email,
                'phone' => $person->phone ?: $duplicate->phone,
                'tax_id' => $person->tax_id ?: $duplicate->tax_id,
                'billing_email' => $person->billing_email ?: $duplicate->billing_email,
                'notes' => collect([$person->notes, $duplicate->notes])->filter()->unique()->implode("\n\n"),
            ]);
            $duplicate->delete();

            CrmActivity::create([
                'person_id' => $person->id,
                'assigned_user_id' => $request->user()->id,
                'created_by' => $request->user()->id,
                'type' => 'task',
                'title' => __('Duplicate customer merged'),
                'notes' => __('Merged customer #:id into this profile.', ['id' => $duplicate->id]),
                'priority' => 'normal',
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        });

        return to_route('people.show', $person)->with('toast', ['type' => 'success', 'message' => __('Customers merged with full traceability.')]);
    }
}
