import { usePage } from '@inertiajs/react';
import {
    Bot,
    LoaderCircle,
    Send,
    Sparkles,
    ThumbsDown,
    ThumbsUp,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type Message = {
    id: number;
    role: 'user' | 'assistant';
    status: 'queued' | 'processing' | 'completed' | 'failed';
    content: string | null;
    rating: 'helpful' | 'unhelpful' | null;
    message: string | null;
    context_module: string;
    sources: {
        title: string;
        version: number;
        score: number | null;
        excerpt: string;
        url: string;
    }[];
    metrics: {
        automated_score: number | null;
        human_score: number | null;
        duration_ms: number | null;
        cost_micros: number | null;
    } | null;
};
type Conversation = { id: number; messages: Message[] };

export function AreviaChat() {
    const user = usePage<{ auth: { user: { id: number } } }>().props.auth.user;
    const storageKey = `arevia-chat-open-${user.id}`;
    const draftKey = `arevia-chat-draft-${user.id}`;
    const [open, setOpen] = useState(
        () => localStorage.getItem(storageKey) === 'true',
    );
    const [draft, setDraft] = useState(
        () => localStorage.getItem(draftKey) || '',
    );
    const [conversation, setConversation] = useState<Conversation | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const messagesEnd = useRef<HTMLDivElement>(null);
    const pending =
        conversation?.messages.some(
            (message) =>
                message.status === 'queued' || message.status === 'processing',
        ) ?? false;

    useEffect(() => {
        localStorage.setItem(storageKey, String(open));

        if (open) {
            void loadConversation();
        }
    }, [open, storageKey]);

    useEffect(() => {
        localStorage.setItem(draftKey, draft);
    }, [draft, draftKey]);

    useEffect(() => {
        if (!open || !pending) {
            return;
        }

        const timer = window.setInterval(
            () => void loadConversation(false),
            2000,
        );

        return () => window.clearInterval(timer);
    }, [open, pending]);

    useEffect(() => {
        if (open) {
            messagesEnd.current?.scrollIntoView({ behavior: 'smooth' });
        }
    }, [open, conversation?.messages.length]);

    async function loadConversation(showLoader = true) {
        if (showLoader) {
            setLoading(true);
        }

        try {
            const response = await fetch('/arevia/conversation', {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Unable to load the Arevia conversation.');
            }

            const data = (await response.json()) as {
                conversation: Conversation | null;
            };
            setConversation(data.conversation);
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Unable to load Arevia.',
            );
        } finally {
            if (showLoader) {
                setLoading(false);
            }
        }
    }

    async function sendMessage(event: FormEvent) {
        event.preventDefault();
        const content = draft.trim();

        if (!content || pending) {
            return;
        }

        setDraft('');
        setError(null);
        setLoading(true);

        try {
            const response = await fetch('/arevia/messages', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({
                    content,
                    page_url: window.location.pathname + window.location.search,
                }),
            });
            const data = (await response.json()) as {
                conversation?: Conversation;
                message?: string;
            };

            if (!response.ok || !data.conversation) {
                throw new Error(data.message || 'Unable to send the message.');
            }

            setConversation(data.conversation);
        } catch (reason) {
            setDraft(content);
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Unable to send the message.',
            );
        } finally {
            setLoading(false);
        }
    }

    async function rateMessage(
        messageId: number,
        rating: 'helpful' | 'unhelpful',
    ) {
        const response = await fetch(`/arevia/messages/${messageId}/feedback`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({ rating }),
        });

        if (response.ok) {
            await loadConversation(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="ml-auto"
                    title="Open Arevia"
                >
                    <Sparkles className="size-4" />
                    <span className="hidden sm:inline">Arevia</span>
                    {pending ? (
                        <span className="size-2 rounded-full bg-amber-500" />
                    ) : null}
                </Button>
            </DialogTrigger>
            <DialogContent className="grid h-[min(760px,calc(100vh-2rem))] grid-rows-[auto_minmax(0,1fr)_auto] p-0 sm:max-w-2xl">
                <DialogHeader className="border-b px-5 py-4 pr-12">
                    <DialogTitle className="flex items-center gap-2">
                        <Bot className="size-5" /> Arevia
                    </DialogTitle>
                    <DialogDescription>
                        Private Arevantes assistant using CRM context and
                        approved knowledge.
                    </DialogDescription>
                </DialogHeader>

                <div className="overflow-y-auto px-5 py-4">
                    {loading && !conversation ? (
                        <div className="flex h-full items-center justify-center">
                            <LoaderCircle className="size-5 animate-spin text-muted-foreground" />
                        </div>
                    ) : !conversation?.messages.length ? (
                        <div className="flex h-full flex-col items-center justify-center text-center text-sm text-muted-foreground">
                            <Sparkles className="mb-3 size-6" />
                            <p>
                                Ask about an installation, a procedure, or the
                                next operational step.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {conversation.messages.map((message) => (
                                <div
                                    key={message.id}
                                    className={
                                        message.role === 'user'
                                            ? 'ml-auto max-w-[85%]'
                                            : 'mr-auto max-w-[92%]'
                                    }
                                >
                                    <div
                                        className={
                                            message.role === 'user'
                                                ? 'rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground'
                                                : 'rounded-md border bg-muted/30 px-3 py-2 text-sm'
                                        }
                                    >
                                        {message.status === 'queued' ||
                                        message.status === 'processing' ? (
                                            <span className="flex items-center gap-2 text-muted-foreground">
                                                <LoaderCircle className="size-4 animate-spin" />{' '}
                                                Arevia is thinking…
                                            </span>
                                        ) : message.status === 'failed' ? (
                                            <span className="text-destructive">
                                                {message.message}
                                            </span>
                                        ) : (
                                            <>
                                                <p className="whitespace-pre-wrap">
                                                    {message.content}
                                                </p>
                                                {message.role === 'assistant' &&
                                                message.sources.length > 0 ? (
                                                    <div className="mt-3 space-y-2 border-t pt-2">
                                                        <div className="text-xs font-medium">
                                                            Sources used
                                                        </div>
                                                        {message.sources.map(
                                                            (source, index) => (
                                                                <a
                                                                    key={`${source.url}-${index}`}
                                                                    href={
                                                                        source.url
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="block rounded border bg-background p-2 text-xs hover:border-foreground/40"
                                                                >
                                                                    <span className="font-medium">
                                                                        [S
                                                                        {index +
                                                                            1}
                                                                        ]{' '}
                                                                        {
                                                                            source.title
                                                                        }{' '}
                                                                        · v
                                                                        {
                                                                            source.version
                                                                        }
                                                                    </span>
                                                                    <span className="mt-1 block text-muted-foreground">
                                                                        {
                                                                            source.excerpt
                                                                        }
                                                                    </span>
                                                                </a>
                                                            ),
                                                        )}
                                                    </div>
                                                ) : null}
                                            </>
                                        )}
                                    </div>
                                    {message.role === 'assistant' &&
                                    message.status === 'completed' ? (
                                        <div className="mt-1 flex flex-wrap items-center gap-1">
                                            {message.metrics ? (
                                                <span className="mr-auto text-[11px] text-muted-foreground">
                                                    {message.context_module} ·
                                                    quality{' '}
                                                    {message.metrics
                                                        .automated_score ?? '—'}
                                                    /100 ·{' '}
                                                    {message.metrics.duration_ms
                                                        ? `${(message.metrics.duration_ms / 1000).toFixed(1)} s`
                                                        : '—'}
                                                </span>
                                            ) : null}
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                className="size-7"
                                                title="Useful"
                                                onClick={() =>
                                                    rateMessage(
                                                        message.id,
                                                        'helpful',
                                                    )
                                                }
                                            >
                                                <ThumbsUp
                                                    className={
                                                        message.rating ===
                                                        'helpful'
                                                            ? 'fill-current'
                                                            : ''
                                                    }
                                                />
                                            </Button>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                className="size-7"
                                                title="Not useful"
                                                onClick={() =>
                                                    rateMessage(
                                                        message.id,
                                                        'unhelpful',
                                                    )
                                                }
                                            >
                                                <ThumbsDown
                                                    className={
                                                        message.rating ===
                                                        'unhelpful'
                                                            ? 'fill-current'
                                                            : ''
                                                    }
                                                />
                                            </Button>
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                            <div ref={messagesEnd} />
                        </div>
                    )}
                </div>

                <form onSubmit={sendMessage} className="border-t p-4">
                    {error ? (
                        <p className="mb-2 text-xs text-destructive">{error}</p>
                    ) : null}
                    <div className="flex items-end gap-2">
                        <textarea
                            value={draft}
                            onChange={(event) => setDraft(event.target.value)}
                            rows={2}
                            placeholder="Message Arevia…"
                            className="min-h-10 flex-1 resize-none rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />
                        <Button
                            type="submit"
                            size="icon"
                            disabled={!draft.trim() || pending || loading}
                            title="Send"
                        >
                            <Send />
                        </Button>
                    </div>
                    <p className="mt-2 text-xs text-muted-foreground">
                        Ratings are saved for evaluation. Only reviewed
                        information becomes training knowledge.
                    </p>
                </form>
            </DialogContent>
        </Dialog>
    );
}
