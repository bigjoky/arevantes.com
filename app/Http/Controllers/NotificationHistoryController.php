<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()->latest()->paginate(25)->through(fn ($item) => [
            'id' => $item->id,
            'message' => $item->data['message'] ?? 'Nueva notificación',
            'url' => $this->safeUrl($request, $item->data),
            'created_at' => $item->created_at?->toIso8601String(),
            'read_at' => $item->read_at?->toIso8601String(),
        ]);

        return Inertia::render('notifications/index', ['notifications' => $notifications]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return back();
    }

    private function safeUrl(Request $request, array $data): ?string
    {
        if (isset($data['opportunity_id'])) {
            return $request->user()->can('manage-customers')
                ? route('opportunities.show', $data['opportunity_id'], false)
                : null;
        }

        $url = $data['url'] ?? null;
        if (! is_string($url)) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['host']) && $parts['host'] !== $request->getHost())
            || (isset($parts['scheme']) && ! in_array($parts['scheme'], ['http', 'https'], true))) {
            return null;
        }
        $path = $parts['path'] ?? '';
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return null;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
