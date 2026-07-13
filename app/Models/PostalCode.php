<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PostalCode extends Model
{
    protected $fillable = ['country_id', 'code'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function localities(): BelongsToMany
    {
        return $this->belongsToMany(Locality::class, 'locality_postal_code');
    }
}
