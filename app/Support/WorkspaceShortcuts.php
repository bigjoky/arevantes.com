<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class WorkspaceShortcuts
{
    public function for(User $user, ?string $visited = null, ?string $favorite = null, bool $enabled = false): array
    {
        $items = collect(app(WorkspaceNavigation::class)->for($user))
            ->flatMap(fn (array $item) => $item['children'] ?? [$item])
            ->keyBy('href');

        if ($favorite !== null) {
            abort_unless($items->has($favorite), 403);
        }

        $stored = $user->workspace_shortcuts ?? [];
        $paths = [...($stored['favorites'] ?? []), ...($stored['recent'] ?? [])];
        if ($favorite === null && $stored !== [] && array_diff($paths, $items->keys()->all()) === []
            && ($visited === null || ! $items->has($visited) || ($stored['recent'][0] ?? null) === $visited)) {
            return [
                'favorites' => array_map(fn (string $href) => $items[$href], $stored['favorites']),
                'recent' => array_map(fn (string $href) => $items[$href], $stored['recent']),
            ];
        }

        return DB::transaction(function () use ($user, $items, $visited, $favorite, $enabled): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $stored = $locked->workspace_shortcuts ?? [];
            $favorites = array_values(array_intersect($stored['favorites'] ?? [], $items->keys()->all()));
            $recent = array_values(array_intersect($stored['recent'] ?? [], $items->keys()->all()));
            if ($favorite !== null) {
                $favorites = array_values(array_diff($favorites, [$favorite]));
                if ($enabled) {
                    $favorites[] = $favorite;
                }
            }
            if ($visited !== null && $items->has($visited)) {
                $recent = array_slice(array_values(array_unique([$visited, ...$recent])), 0, 5);
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
