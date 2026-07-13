<?php

namespace App\Services\Ai;

use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\PurchaseInboxDocument;
use App\Models\SalesDocument;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BusinessContextBuilder
{
    public function build(User $user): string
    {
        $company = CompanyProfile::first();
        $year = FiscalYear::where('year', now()->year)->first();
        $lines = [
            'Live CRM snapshot generated at '.now()->toIso8601String().'.',
            'Company: '.($company?->legal_name ?: 'Arevantes').($company?->tax_id ? ' · tax ID '.$company->tax_id : '').($company?->city ? ' · '.$company->city.', '.$company->province : '').'.',
            'Current fiscal year: '.($year ? $year->year.' ('.$year->status.')' : 'not configured').'.',
            'Customers: '.Person::where('category', 'customer')->count().'.',
            'Installations: '.$this->counts(Installation::query(), 'status').'.',
        ];

        if (Gate::forUser($user)->allows('manage-customers')) {
            $open = Opportunity::whereNotIn('stage', ['won', 'lost']);
            $weighted = (clone $open)->get()->sum(fn (Opportunity $item) => (float) $item->estimated_value * $item->probability / 100);
            $lines[] = 'Sales opportunities: '.$this->counts(Opportunity::query(), 'stage').' · open pipeline '.number_format((float) (clone $open)->sum('estimated_value'), 2, '.', '').' EUR · weighted forecast '.number_format($weighted, 2, '.', '').' EUR.';
        }

        if (Gate::forUser($user)->allows('manage-catalog')) {
            $sales = SalesDocument::where('type', 'invoice');
            $purchases = PurchaseDocument::whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereNot('status', 'review_required');
            $purchasesUnderReview = PurchaseDocument::where('type', 'supplier_invoice')->where('status', 'review_required');
            $purchaseFilesPending = PurchaseDocument::whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereDoesntHave('attachment', fn ($query) => $query->where('extraction_status', 'verified'));
            $purchaseInbox = PurchaseInboxDocument::whereIn('status', ['pending_review', 'ready']);
            $salesOutstanding = (clone $sales)->selectRaw('coalesce(sum(amount - paid_amount), 0) total')->value('total');
            $purchaseOutstanding = (clone $purchases)->selectRaw('coalesce(sum(amount - paid_amount), 0) total')->value('total');
            $lines[] = 'Sales invoices: '.$sales->count().' · total '.number_format((float) $sales->sum('amount'), 2, '.', '').' EUR · outstanding '.number_format((float) $salesOutstanding, 2, '.', '').' EUR.';
            $lines[] = 'Supplier invoices and credits: '.$purchases->count().' · net total '.number_format((float) $purchases->sum('amount'), 2, '.', '').' EUR · net outstanding '.number_format((float) $purchaseOutstanding, 2, '.', '').' EUR · under three-way review '.$purchasesUnderReview->count().' · original documents pending verification '.$purchaseFilesPending->count().'.';
            $lines[] = 'Purchase document inbox: '.$purchaseInbox->count().' awaiting processing · '.(clone $purchaseInbox)->where('status', 'ready')->count().' ready to convert.';
            $lines[] = 'Expenses: '.Expense::count().' · total '.number_format((float) Expense::sum('amount'), 2, '.', '').' EUR.';
            $accounts = BankAccount::withSum('transactions as movement_total', 'amount')->where('is_active', true)->get();
            $lines[] = 'Bank accounts: '.$accounts->map(fn ($account) => $account->name.' '.number_format((float) $account->opening_balance + (float) ($account->movement_total ?? 0), 2, '.', '').' '.$account->currency)->implode(' · ').'.';
        }

        return implode("\n", $lines);
    }

    private function counts($query, string $column): string
    {
        return $query->selectRaw("{$column}, count(*) total")->groupBy($column)->pluck('total', $column)->map(fn ($count, $status) => ($status ?: 'unspecified').' '.$count)->implode(' · ');
    }
}
