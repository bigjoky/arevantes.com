<?php

namespace App\Services\Ai;

use App\Models\KnowledgeDocument;
use App\Models\User;

class KnowledgeAccess
{
    public function allows(User $user, KnowledgeDocument $document): bool
    {
        return $document->status === 'indexed'
            && ($user->hasRole('admin') || $document->allowed_roles === null || in_array($user->role, $document->allowed_roles, true));
    }
}
