<?php

namespace App\Services\Finance;

use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\PurchaseDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Cross-module fiscal identity; monetary ownership remains in the existing source. */
class ReceivedInvoiceIdentity
{
    private static ?int $correctionExpense = null;

    public static function correctionAllowed(int $id): bool
    {
        return self::$correctionExpense === $id;
    }

    public static function correctingExpense(int $id, \Closure $apply): mixed
    {
        $previous = self::$correctionExpense;
        self::$correctionExpense = $id;
        try {
            return DB::transaction(function () use ($id, $apply) {
                $source = Expense::lockForUpdate()->findOrFail($id);
                $identity = DB::table('received_invoice_candidates')->where('source_kind', 'expense')->where('source_id', $id)->first();
                if ($identity) {
                    app(self::class)->assertUsable($source);
                    $key = (array) $identity;
                    unset($key['source_kind'], $key['source_id']);
                    if (! DB::table('received_invoice_identities')->where($key)->where('expense_id', $id)->exists()) {
                        DB::table('received_invoice_identities')->insert([...$key, 'expense_id' => $id]);
                    }
                }
                if (DB::getDriverName() === 'pgsql') {
                    DB::select("SELECT set_config('arevantes.received_invoice_correction_id', ?, true)", [(string) $id]);
                }

                return $apply();
            });
        } finally {
            self::$correctionExpense = $previous;
            if (DB::getDriverName() === 'pgsql') {
                DB::select("SELECT set_config('arevantes.received_invoice_correction_id', '', true)");
            }
        }
    }

    public function lockInput(string $country, ?string $taxId, string $kind, string $number, ?int $receiver = null): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        $country = strtoupper(trim($country));
        if (in_array($country, ['', 'SPAIN', 'ESPAÑA'])) {
            $country = 'ES';
        }
        $taxId = strtoupper(str_replace([' ', '-', '.'], '', trim($taxId ?? '')));
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(jsonb_build_array(?::bigint, ?::text, ?::text, ?::text, ?::text)::text, 0))', [$receiver ?? CompanyProfile::min('id'), $country, $taxId, $kind, trim($number)]);
    }

    public function lockCorrectionKeys(Expense $expense, array $data): void
    {
        $inputs = [[$expense->issuer_country ?? 'ES', $expense->issuer_tax_id, 'invoice', $expense->document_number ?? '', $expense->receiver_company_id],
            [$data['issuer_country'] ?? 'ES', $data['issuer_tax_id'] ?? null, 'invoice', $data['document_number'] ?? '', $expense->receiver_company_id]];
        $inputs = array_map(function ($input) {
            $country = strtoupper(trim($input[0]));

            return [in_array($country, ['', 'SPAIN', 'ESPAÑA']) ? 'ES' : $country, strtoupper(str_replace([' ', '-', '.'], '', trim($input[1] ?? ''))), $input[2], trim($input[3]), $input[4] ?? CompanyProfile::min('id')];
        }, $inputs);
        usort($inputs, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
        foreach ($inputs as $input) {
            $this->lockInput(...$input);
        }
    }

    public function assertUsable(Expense|PurchaseDocument $source): void
    {
        $kind = $source instanceof Expense ? 'expense' : 'purchase';
        $identity = DB::table('received_invoice_candidates')->where('source_kind', $kind)->where('source_id', $source->id)->first();
        if (! $identity) {
            return; // Receipts/other expenses have their existing documentary rules.
        }
        if (! $identity->issuer_tax_id || ! $identity->external_number || ! $identity->receiver_company_id) {
            $this->fail('Faltan datos de identidad fiscal. Revisa emisor, receptor y número externo antes de registrar efectos económicos.');
        }
        if ((int) $identity->receiver_company_id !== (int) CompanyProfile::min('id')) {
            $this->fail('La factura pertenece a otra empresa receptora. Esta sesión no puede registrar efectos económicos sobre ella.');
        }
        if ($source->receiver_company_id === null && CompanyProfile::count() > 1) {
            $this->fail('El receptor histórico no está identificado de forma inequívoca. Requiere revisión autorizada.');
        }
        $ownerColumn = $kind === 'expense' ? 'expense_id' : 'purchase_document_id';
        $ownerClaims = DB::table('received_invoice_identities')->where($ownerColumn, $source->id);
        $matching = clone $ownerClaims;
        foreach (['receiver_company_id', 'issuer_country', 'issuer_tax_id', 'document_kind', 'external_number'] as $field) {
            $matching->where($field, $identity->$field);
        }
        if ($ownerClaims->exists() && ! $matching->exists()) {
            $this->fail('La identidad actual no coincide con la identidad fiscal conservada. Requiere revisión documental.');
        }
        if ($source instanceof PurchaseDocument && blank(data_get($source->supplier_snapshot, 'tax_id')) && ! $ownerClaims->exists()) {
            $this->fail('La identidad histórica del proveedor no está congelada. Revisa el documento original antes de crear efectos económicos.');
        }
        $claims = DB::table('received_invoice_identities');
        foreach (['receiver_company_id', 'issuer_country', 'issuer_tax_id', 'document_kind', 'external_number'] as $field) {
            $claims->where($field, $identity->$field);
        }
        $owner = $kind === 'expense' ? 'expense_id' : 'purchase_document_id';
        if ($claims->where(fn ($q) => $q->whereNull($owner)->orWhere($owner, '<>', $source->id))->exists()) {
            $this->fail('Esta identidad fiscal está reservada para otro documento, incluida su trazabilidad de correcciones.');
        }
        $same = DB::table('received_invoice_candidates');
        foreach (['receiver_company_id', 'issuer_country', 'issuer_tax_id', 'document_kind', 'external_number'] as $field) {
            $same->where($field, $identity->$field);
        }
        $duplicates = $same->where(fn ($q) => $q->where('source_kind', '<>', $kind)->orWhere('source_id', '<>', $source->id))->get(['source_kind', 'source_id']);
        if ($duplicates->isNotEmpty()) {
            $this->fail('Esta factura coincide fiscalmente con otro registro de Compras o Gastos. Revisa la identidad y utiliza el documento existente; no se creará otra obligación ni otro asiento.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['document_number' => $message, 'supplier_number' => $message]);
    }
}
