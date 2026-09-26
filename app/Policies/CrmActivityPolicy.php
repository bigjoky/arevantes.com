<?php

namespace App\Policies;

use App\Models\CrmActivity;
use App\Models\User;

class CrmActivityPolicy
{
    public function update(User $user, CrmActivity $activity): bool
    {
        return $user->hasRole('admin', 'commercial')
            || ($user->hasRole('technician') && $activity->assigned_user_id === $user->id);
    }

    public function delete(User $user, CrmActivity $activity): bool
    {
        return $user->hasRole('admin', 'commercial') && $activity->work_order_id === null;
    }

    public function convert(User $user, CrmActivity $activity): bool
    {
        return $user->hasRole('admin', 'commercial')
            && $activity->work_order_id === null
            && $activity->installation_id !== null
            && ! in_array($activity->status, ['completed', 'cancelled'], true);
    }
}
