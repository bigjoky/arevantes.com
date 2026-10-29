<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingAccountAssignment extends Model
{
    protected $fillable = ['entity_type', 'entity_id', 'role', 'ledger_account_id', 'updated_by'];
}
