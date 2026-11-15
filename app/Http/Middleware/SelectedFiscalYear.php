<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SelectedFiscalYear
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->query->has('year')) {
            $year = filter_var($request->query('year'), FILTER_VALIDATE_INT);
            if ($year !== false && $year >= 2020 && $year <= 2100) {
                $request->session()->put('selected_fiscal_year', $year);
            }
        }

        $year = (int) $request->session()->get('selected_fiscal_year', now()->year);
        $request->attributes->set('selected_fiscal_year', $year);
        $routeName = $request->route()?->getName();
        $field = match ($routeName) {
            'expenses.store', 'expenses.update' => 'expense_date',
            'purchases.store', 'purchases.update', 'purchases.invoice', 'purchases.dates.update', 'supplier-invoices.credit-note', 'sales-documents.store', 'sales-documents.update', 'sales-documents.update-issued', 'sales-documents.issue', 'sales-documents.credit-note' => 'issue_date',
            'expenses.payments.store', 'supplier-invoices.payments.store', 'sales-documents.payments.store' => 'paid_at',
            'accounting.entries.store', 'accounting.entries.reverse', 'accounting.corrections.store', 'accounting.drafts.store', 'accounting.drafts.update', 'accounting.drafts.post' => 'entry_date',
            default => null,
        };
        if (! $request->isMethod('GET') && $field) {
            $date = match ($routeName) {
                'accounting.drafts.post' => ($request->route('entry') instanceof \App\Models\JournalEntry
                    ? $request->route('entry') : \App\Models\JournalEntry::find($request->route('entry')))?->entry_date?->toDateString(),
                'sales-documents.credit-note' => today()->toDateString(),
                default => $request->input($field),
            };
            $operationYear = null;
            $operationDate = null;
            if (is_string($date)) {
                try {
                    $operationDate = \Carbon\CarbonImmutable::parse($date);
                    $operationYear = $operationDate->year;
                } catch (\Throwable) {
                    // The operation's own validator reports invalid dates.
                }
            }
            $fiscalYear = $operationDate ? \App\Models\FiscalYear::where('year', $year)->first(['starts_on', 'ends_on']) : null;
            $outsideYear = $operationDate && ($fiscalYear
                ? ($operationDate->toDateString() < $fiscalYear->starts_on->toDateString() || $operationDate->toDateString() > $fiscalYear->ends_on->toDateString())
                : $operationYear !== $year);
            if ($outsideYear && ! $request->boolean('confirm_other_year')) {
                throw \Illuminate\Validation\ValidationException::withMessages([$field => "La fecha pertenece a {$operationYear} y el ejercicio seleccionado es {$year}. Confirma expresamente antes de continuar."]);
            }
        }
        if ($request->isMethod('GET') && ! $request->query->has('year')
            && $request->is('dashboard', 'billing*', 'sales*', 'expenses*', 'purchases*', 'accounting*', 'banking*', 'fiscal*', 'tax*', 'corporate-tax*', 'workspace/modules/accounting*')) {
            $request->query->set('year', $year);
        }

        return $next($request);
    }
}
