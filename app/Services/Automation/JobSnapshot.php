<?php

namespace App\Services\Automation;

use App\Models\AiMessage;
use App\Models\Installation;
use App\Models\MobileDevice;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;

class JobSnapshot
{
    public static function push(WorkOrder $order, int $userId, string $notificationTitle): ?string
    {
        $user = User::find($userId);
        if (! $user || ! Gate::forUser($user)->allows('manage-operations') || $order->assigned_user_id !== $userId || ! MobileDevice::where('user_id', $userId)->where('enabled', true)->exists() || (($order->completed_at || in_array($order->status, ['completed', 'cancelled', 'closed'], true)) && $notificationTitle !== 'Orden actualizada')) {
            return null;
        }

        return self::fields($order->getAttributes(), ['id', 'number', 'assigned_user_id', 'status', 'title', 'instructions', 'scheduled_start', 'scheduled_end', 'scheduled_start_utc', 'scheduled_end_utc', 'updated_at']);
    }

    public static function arevia(AiMessage $message, bool $allowProcessing = false): ?string
    {
        $conversation = $message->conversation()->with('user')->first();
        $user = $conversation?->user;
        $turn = $conversation?->messages()->where('role', 'user')->where('id', '<', $message->id)->latest('id')->first();
        if (! $user || ! Gate::forUser($user)->allows('use-ai') || $message->role !== 'assistant' || ! in_array($message->status, $allowProcessing ? ['queued', 'processing'] : ['queued'], true) || filled($message->content) || ! $turn || $conversation->messages()->where('role', 'user')->max('id') !== $turn->id) {
            return null;
        }

        return hash('sha256', json_encode([$message->id, $conversation->id, $user->id, $message->context_module, $turn->id, $turn->content, $turn->page_url, $turn->updated_at?->toISOString()], JSON_THROW_ON_ERROR));
    }

    public static function geocode(Installation $installation): ?string
    {
        if ($installation->dossier_closed_at || in_array($installation->status, ['closed', 'cancelled', 'completed'], true) || ($installation->site_latitude !== null && $installation->site_longitude !== null) || blank($installation->site_address_line_1)) {
            return null;
        }

        return self::fields($installation->getAttributes(), ['id', 'site_address_line_1', 'site_street_number', 'site_locality', 'site_city', 'site_province_state', 'site_postal_code', 'site_country', 'site_latitude', 'site_longitude', 'updated_at']);
    }

    private static function fields(array $attributes, array $keys): string
    {
        return hash('sha256', json_encode(array_intersect_key($attributes, array_flip($keys)), JSON_THROW_ON_ERROR));
    }
}
