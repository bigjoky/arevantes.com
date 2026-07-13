<?php

namespace App\Jobs;

use App\Ai\Agents\AreviaChat;
use App\Models\AiMessage;
use App\Services\Ai\BusinessContextBuilder;
use App\Services\Ai\KnowledgeRetriever;
use App\Services\Ai\RelevantBusinessContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateAreviaReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 240;

    public function __construct(public int $messageId) {}

    public function handle(KnowledgeRetriever $retriever, BusinessContextBuilder $businessContext, RelevantBusinessContext $relevantContext): void
    {
        $message = AiMessage::with(['conversation.messages', 'conversation.user'])->findOrFail($this->messageId);
        $message->update(['status' => 'processing', 'error' => null]);
        $started = hrtime(true);

        try {
            $turns = $message->conversation->messages
                ->where('id', '<', $message->id)
                ->sortByDesc('id')->take(12)->sortBy('id')
                ->map(fn (AiMessage $turn) => strtoupper($turn->role).': '.$turn->content)->implode("\n\n");
            $latestUser = $message->conversation->messages->where('role', 'user')->sortByDesc('id')->first();
            $sources = $retriever->search((string) $latestUser?->content, 4);
            $context = collect($sources)->map(fn (array $source, int $index) => sprintf(
                '[S%d: %s v%d]\n%s', $index + 1, $source['title'], $source['version'], $source['content']
            ))->implode("\n\n---\n\n");
            $liveContext = $businessContext->build($message->conversation->user);
            $records = $relevantContext->search($message->conversation->user, (string) $latestUser?->content, $latestUser?->page_url);
            $prompt = 'Current CRM page: '.($latestUser?->page_url ?: 'unknown')."\n\nLive authorized CRM context:\n{$liveContext}\n\nRelevant authorized CRM records:\n{$records}\n\nConversation:\n{$turns}\n\nPrivate knowledge:\n".
                ($context ?: 'No relevant private documentation found.')."\n\nAnswer the latest USER message.";
            $response = AreviaChat::make()->prompt($prompt, timeout: 180);

            $message->update([
                'status' => 'completed',
                'content' => trim($response->text),
                'sources' => collect($sources)->map(fn (array $source) => ['title' => $source['title'], 'version' => $source['version'], 'score' => $source['score']])->all(),
                'provider' => $response->meta->provider,
                'model' => $response->meta->model,
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            ]);
        } catch (Throwable $exception) {
            $willRetry = $this->attempts() < $this->tries;
            $message->update([
                'status' => $willRetry ? 'queued' : 'failed',
                'error' => $exception->getMessage(),
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            ]);
            throw $exception;
        }
    }
}
