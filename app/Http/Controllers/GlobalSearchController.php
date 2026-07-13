<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\Supplier;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $term = $data['q'];
        $results = collect();

        if (Gate::forUser($request->user())->allows('manage-customers')) {
            Person::where('category', 'customer')->where(fn ($q) => $q->where('company_name', 'like', "%{$term}%")->orWhere('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")->orWhere('tax_id', 'like', "%{$term}%"))->limit(5)->get()->each(fn ($person) => $results->push(['group' => 'Customers', 'title' => $person->company_name ?: trim($person->first_name.' '.$person->last_name), 'subtitle' => collect([$person->city, $person->email])->filter()->implode(' · '), 'href' => route('people.show', $person, false)]));
            Opportunity::with('customer:id,first_name,last_name,company_name')->where(fn ($q) => $q->where('code', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%")->orWhere('source', 'like', "%{$term}%")->orWhere('notes', 'like', "%{$term}%"))->limit(5)->get()->each(fn ($item) => $results->push(['group' => 'Opportunities', 'title' => $item->code.' · '.$item->title, 'subtitle' => collect([$item->customer?->company_name ?: trim(($item->customer?->first_name ?? '').' '.($item->customer?->last_name ?? '')), $item->stage, number_format((float) $item->estimated_value, 2, ',', '.').' EUR'])->filter()->implode(' · '), 'href' => route('opportunities.index', ['opportunity' => $item->id], false)]));
        }

        if (Gate::forUser($request->user())->allows('view-installations')) {
            Installation::with('customer:id,first_name,last_name,company_name')->where(fn ($q) => $q->where('project_reference', 'like', "%{$term}%")->orWhere('site_name', 'like', "%{$term}%")->orWhere('site_address_line_1', 'like', "%{$term}%")->orWhere('site_city', 'like', "%{$term}%")->orWhere('site_postal_code', 'like', "%{$term}%"))->limit(5)->get()->each(fn ($item) => $results->push(['group' => 'Installations', 'title' => $item->project_reference ?: $item->site_name ?: __('Installation #:id', ['id' => $item->id]), 'subtitle' => collect([$item->service_type, $item->status, $item->site_city])->filter()->implode(' · '), 'href' => route('installations.show', $item, false)]));
        }

        if (Gate::forUser($request->user())->allows('manage-operations')) {
            WorkOrder::where(fn ($q) => $q->where('number', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%")->orWhere('status', 'like', "%{$term}%"))->limit(5)->get()->each(fn ($item) => $results->push(['group' => 'Work orders', 'title' => $item->number.' · '.$item->title, 'subtitle' => $item->status, 'href' => route('work-orders.index', ['work_order' => $item->id], false)]));
        }

        if (Gate::forUser($request->user())->allows('manage-catalog')) {
            SalesDocument::with('customer:id,first_name,last_name,company_name')->where(fn ($q) => $q->where('number', 'like', "%{$term}%")->orWhere('status', 'like', "%{$term}%"))->limit(5)->get()->each(fn ($item) => $results->push(['group' => 'Sales', 'title' => $item->number, 'subtitle' => $item->type.' · '.$item->status.' · '.number_format((float) $item->amount, 2, ',', '.').' EUR', 'href' => route('sales-documents.show', $item, false)]));
            PurchaseDocument::with('supplier:id,legal_name,trade_name')->where(fn ($q) => $q->where('number', 'like', "%{$term}%")->orWhere('supplier_number', 'like', "%{$term}%")->orWhere('status', 'like', "%{$term}%"))->limit(5)->get()->each(fn ($item) => $results->push(['group' => 'Purchases', 'title' => $item->number, 'subtitle' => ($item->supplier?->trade_name ?: $item->supplier?->legal_name ?: __('No supplier')).' · '.$item->status, 'href' => route('purchases.show', $item, false)]));
            Supplier::where(fn ($q) => $q->where('legal_name', 'like', "%{$term}%")->orWhere('trade_name', 'like', "%{$term}%")->orWhere('tax_id', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))->limit(5)->get()->each(fn ($item) => $results->push(['group' => 'Suppliers', 'title' => $item->trade_name ?: $item->legal_name, 'subtitle' => collect([$item->tax_id, $item->email])->filter()->implode(' · '), 'href' => route('catalog.index', ['supplier' => $item->id], false)]));
        }

        return response()->json(['results' => $results->take(30)->values()]);
    }
}
