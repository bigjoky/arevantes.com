<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Expense extends Model
{
    protected $fillable = ['expense_category_id', 'supplier_id', 'installation_id', 'created_by', 'number', 'type', 'document_number', 'merchant_name', 'expense_date', 'due_date', 'subtotal', 'tax_amount', 'amount', 'paid_amount', 'status', 'notes'];

    protected function casts(): array
    {
        return ['expense_date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'amount' => 'decimal:2', 'paid_amount' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class)->orderByDesc('paid_at');
    }

    public function accountingEntry(): MorphOne
    {
        return $this->morphOne(JournalEntry::class, 'source')->where('event', 'document');
    }
}
