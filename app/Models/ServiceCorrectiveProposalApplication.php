<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCorrectiveProposalApplication extends Model
{
    protected $fillable = [
        'service_corrective_proposal_id', 'status', 'input', 'target_before',
        'target_after', 'result', 'error_code', 'error_message', 'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array', 'target_before' => 'array', 'target_after' => 'array',
            'result' => 'array',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ServiceCorrectiveProposal::class, 'service_corrective_proposal_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
