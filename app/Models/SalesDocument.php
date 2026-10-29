<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class SalesDocument extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $document) {
            if ($document->source_document_id) {
                $parent = self::find($document->source_document_id);
                if ($parent) {
                    $document->accounting_overrides ??= $parent->accounting_overrides;
                    $document->accounting_category_id ??= $parent->accounting_category_id;
                }
            }
        });
    }

    protected $hidden = ['creation_key', 'creation_fingerprint', 'issuer_snapshot', 'archived_pdf_path', 'archived_pdf_hash'];

    public function issuerIdentity(): array
    {
        return $this->issuer_snapshot ?? CompanyProfile::first()?->documentSnapshot() ?? config('company');
    }

    protected $fillable = ['accounting_overrides', 'accounting_category_id', 'type', 'quote_id', 'source_document_id', 'person_id', 'installation_id', 'document_series_id', 'created_by', 'number', 'status', 'fulfillment_status', 'billing_status', 'issue_date', 'due_date', 'billing_percentage', 'billing_sequence', 'billing_label', 'collect_before_invoice', 'is_acceptance_invoice', 'issued_at', 'locked_at', 'subtotal', 'discount_amount', 'tax_amount', 'amount', 'paid_amount', 'customer_snapshot', 'notes'];

    protected function casts(): array
    {
        return ['accounting_overrides' => 'array', 'issuer_snapshot' => 'array', 'issue_date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'billing_percentage' => 'decimal:2', 'collect_before_invoice' => 'boolean', 'is_acceptance_invoice' => 'boolean', 'issued_at' => 'datetime', 'locked_at' => 'datetime', 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'customer_snapshot' => 'array'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id')->withTrashed();
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_document_id');
    }

    public function childDocuments(): HasMany
    {
        return $this->hasMany(self::class, 'source_document_id');
    }

    public function stockReservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function purchaseDocuments(): HasMany
    {
        return $this->hasMany(PurchaseDocument::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesDocumentLine::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalesDocumentPayment::class)->orderByDesc('paid_at')->orderByDesc('id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(DocumentDelivery::class)->latest();
    }

    public function accountingEntry(): MorphOne
    {
        return $this->morphOne(JournalEntry::class, 'source')->where('event', 'document');
    }

    public function collectionAction(): HasOne
    {
        return $this->hasOne(CollectionAction::class);
    }

    public function recurringServiceBilling(): HasOne
    {
        return $this->hasOne(RecurringServiceBilling::class);
    }

    public function accountingCategory(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'accounting_category_id');
    }
}
