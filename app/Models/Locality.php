<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Locality extends Model
{
    protected $fillable = ['municipality_id', 'name'];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function postalCodes(): BelongsToMany
    {
        return $this->belongsToMany(PostalCode::class, 'locality_postal_code');
    }
}
