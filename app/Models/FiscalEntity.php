<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalEntity extends Model
{
    protected $fillable = ['country', 'tax_id', 'tax_id_key', 'legal_name', 'trade_names', 'addresses', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['trade_names' => 'array', 'addresses' => 'array', 'is_active' => 'boolean'];
    }

    public static function taxKey(string $value): string
    {
        return strtoupper(str_replace([' ', '-', '.'], '', trim($value)));
    }

    public function revision(): string
    {
        return hash('sha256', json_encode($this->only($this->fillable), JSON_THROW_ON_ERROR));
    }
}
