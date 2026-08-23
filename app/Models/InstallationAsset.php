<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallationAsset extends Model
{
    protected $fillable = ['installation_id', 'product_id', 'serial_number_id', 'source_purchase_document_id', 'replaced_by_asset_id', 'asset_tag', 'name', 'category', 'manufacturer', 'model', 'serial_number', 'status', 'commissioned_at', 'removed_at', 'warranty_starts_on', 'warranty_ends_on', 'specifications'];

    protected function casts(): array
    {
        return ['commissioned_at' => 'datetime', 'removed_at' => 'datetime', 'warranty_starts_on' => 'date', 'warranty_ends_on' => 'date', 'specifications' => 'array'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(SerialNumber::class, 'serial_number_id');
    }

    public function sourcePurchase(): BelongsTo
    {
        return $this->belongsTo(PurchaseDocument::class, 'source_purchase_document_id');
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_asset_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class)->latest('occurred_at');
    }

    public function rmas(): HasMany
    {
        return $this->hasMany(AssetRma::class)->latest('requested_at');
    }
}
