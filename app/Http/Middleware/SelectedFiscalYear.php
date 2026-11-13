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
        $field = match ($request->route()?->getName()) {
            'expenses.store', 'expenses.update' => 'expense_date',
            'purchases.store', 'purchases.update', 'purchases.invoice', 'sales-documents.store', 'sales-documents.update', 'sales-documents.issue' => 'issue_date',
            'expenses.payments.store', 'supplier-invoices.payments.store', 'sales-documents.payments.store' => 'paid_at',
            'accounting.entries.store', 'accounting.drafts.store', 'accounting.drafts.update' => 'entry_date',
            default => null,
        };
        if (! $request->isMethod('GET') && $field && $request->filled($field)) {
            $date = $request->input($field);
            if (is_string($date) && preg_match('/^(\d{4})-\d{2}-\d{2}$/', $date, $matches)
                && (int) $matches[1] !== $year && ! $request->boolean('confirm_other_year')) {
                throw \Illuminate\Validation\ValidationException::withMessages([$field => "La fecha pertenece a {$matches[1]} y el ejercicio seleccionado es {$year}. Confirma expresamente antes de continuar."]);
            }
        }
        if ($request->isMethod('GET') && ! $request->query->has('year')
            && $request->is('dashboard', 'billing*', 'sales*', 'expenses*', 'purchases*', 'accounting*', 'banking*', 'fiscal*', 'tax*', 'corporate-tax*', 'workspace/modules/accounting*')) {
            $request->query->set('year', $year);
        }

        return $next($request);
    }
}
