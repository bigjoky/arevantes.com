<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    protected $fillable = ['opportunity_id', 'installation_id', 'person_id', 'document_series_id', 'created_by', 'number', 'version', 'status', 'issue_date', 'amount', 'subtotal', 'discount_amount', 'tax_amount', 'valid_until', 'notes', 'customer_snapshot', 'sent_at', 'accepted_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'issue_date' => 'date:Y-m-d', 'valid_until' => 'date:Y-m-d', 'customer_snapshot' => 'array', 'sent_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id')->withTrashed();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('position');
    }

    public function materialRequirements(): HasMany
    {
        return $this->hasMany(InstallationMaterialRequirement::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(DocumentDelivery::class)->latest();
    }

    public function salesDocuments(): HasMany
    {
        return $this->hasMany(SalesDocument::class);
    }
}
