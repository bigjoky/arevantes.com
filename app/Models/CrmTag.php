<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CrmTag extends Model
{
    protected $fillable = ['name', 'color'];

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'crm_tag_person')->withTimestamps();
    }
}
