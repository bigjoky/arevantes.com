<?php

namespace App\Http\Controllers;

use App\Ai\Agents\AreviaChat;
use App\Jobs\GenerateAreviaReply;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AreviaContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreviaChatController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $conversation = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->with(['messages' => fn ($query) => $query->latest()->limit(50)])
            ->latest('last_message_at')
            ->first();

        return response()->json(['conversation' => $conversation ? $this->payload($conversation) : null]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! AreviaChat::isFaked() && ! $this->providerIsConfigured()) {
            return response()->json(['message' => __('The AI provider is not configured.')], 422);
        }
        $data = $request->validate([
            'content' => ['required', 'string', 'max:12000'],
            'page_url' => ['nullable', 'string', 'max:500'],
        ]);
        $conversation = AiConversation::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['last_message_at' => now()],
        );
        $module = AreviaContext::module($data['page_url'] ?? null, $data['content']);
        $conversation->messages()->create([
            'role' => 'user', 'status' => 'completed', 'content' => trim($data['content']), 'page_url' => $data['page_url'] ?? null,
            'context_module' => $module,
        ]);
        $reply = $conversation->messages()->create(['role' => 'assistant', 'status' => 'queued', 'context_module' => $module]);
        $conversation->update(['last_message_at' => now()]);
        GenerateAreviaReply::dispatch($reply->id);

        return response()->json(['conversation' => $this->payload($conversation->load('messages'))], 202);
    }

    public function feedback(Request $request, AiMessage $aiMessage): JsonResponse
    {
        abort_unless($aiMessage->conversation()->where('user_id', $request->user()->id)->exists(), 404);
        abort_unless($aiMessage->role === 'assistant' && $aiMessage->status === 'completed', 422);
        $data = $request->validate([
            'rating' => ['required', 'in:helpful,unhelpful'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $aiMessage->update([
            'rating' => $data['rating'],
            'feedback_notes' => $data['notes'] ?? null,
            'human_score' => $data['rating'] === 'helpful' ? 100 : 25,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => $this->messagePayload($aiMessage)]);
    }

    private function payload(AiConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'messages' => $conversation->messages->sortBy('id')->values()->map(fn (AiMessage $message) => $this->messagePayload($message)),
        ];
    }

    private function messagePayload(AiMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'status' => $message->status,
            'content' => $message->content,
            'rating' => $message->rating,
            'context_module' => $message->context_module,
            'sources' => $message->sources ?? [],
            'metrics' => $message->role === 'assistant' ? [
                'automated_score' => $message->automated_score,
                'human_score' => $message->human_score,
                'duration_ms' => $message->duration_ms,
                'cost_micros' => $message->cost_micros,
            ] : null,
            'created_at' => $message->created_at?->toIso8601String(),
            'message' => $message->status === 'failed'
                ? (str_contains((string) $message->error, 'timed out') ? __('Arevia needed more time than expected. Please try again.') : __('Arevia could not answer right now.'))
                : null,
        ];
    }

    private function providerIsConfigured(): bool
    {
        $configuration = config('ai.providers.'.config('ai.default'), []);

        return ($configuration['driver'] ?? null) === 'ollama'
            ? filled($configuration['url'] ?? null)
            : filled($configuration['key'] ?? null);
    }
}
