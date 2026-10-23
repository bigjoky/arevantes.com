<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class WorkspaceShortcuts
{
    public function for(User $user, ?string $favorite = null, bool $enabled = false): array
    {
        $items = collect(app(WorkspaceNavigation::class)->availableLinks($user))
            ->keyBy('href');

        if ($favorite !== null) {
            abort_unless($items->has($favorite), 403);
        }

        $stored = $user->workspace_shortcuts ?? [];
        $paths = $stored['favorites'] ?? [];
        if ($favorite === null && $stored !== [] && array_diff($paths, $items->keys()->all()) === []
            && ($stored['recent'] ?? []) === []) {
            return [
                'favorites' => array_map(fn (string $href) => $items[$href], $stored['favorites']),
                'recent' => [],
            ];
        }

        return DB::transaction(function () use ($user, $items, $favorite, $enabled): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $stored = $locked->workspace_shortcuts ?? [];
            $favorites = array_values(array_intersect($stored['favorites'] ?? [], $items->keys()->all()));
            // Recent navigation is deliberately disabled; discard legacy history.
            $recent = [];
            if ($favorite !== null) {
                $favorites = array_values(array_diff($favorites, [$favorite]));
                if ($enabled) {
                    $favorites[] = $favorite;
                }
            }
            $state = ['favorites' => $favorites, 'recent' => $recent];
            if ($stored !== $state) {
                $locked->timestamps = false;
                $locked->forceFill(['workspace_shortcuts' => $state])->saveQuietly();
            }

            return [
                'favorites' => array_map(fn (string $href) => $items[$href], $favorites),
                'recent' => array_map(fn (string $href) => $items[$href], $recent),
            ];
        });
    }
}
