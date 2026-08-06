import { Form, Head } from '@inertiajs/react';
import {
    BookOpen,
    ExternalLink,
    FileText,
    RefreshCw,
    Trash2,
    Upload,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Document = {
    id: number;
    title: string;
    original_filename: string | null;
    source_url: string | null;
    file_size: number | null;
    version: number;
    embedding_model: string | null;
    indexed_at: string | null;
    chunks_count: number;
    user_name: string | null;
};

type PendingFeedback = {
    id: number;
    installation_id: number;
    installation_reference: string | null;
    service_type: string;
    reviewer: string;
    notes: string | null;
    original_response: { summary?: string };
    corrected_response: { summary?: string };
    created_at: string;
};

export default function KnowledgeIndex({
    documents,
    pendingFeedback,
}: {
    documents: Document[];
    pendingFeedback: PendingFeedback[];
}) {
    return (
        <>
            <Head title="Knowledge" />
            <div className="flex min-w-0 flex-1 flex-col gap-8 p-4 md:p-6">
                <header>
                    <div className="flex items-center gap-2">
                        <BookOpen className="size-5" />
                        <h1 className="text-xl font-semibold">
                            Arevia knowledge
                        </h1>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Private manuals, procedures and technical criteria used
                        by the CRM assistant.
                    </p>
                </header>

                <section className="border-t pt-5">
                    <Form
                        action="/knowledge"
                        method="post"
                        encType="multipart/form-data"
                        className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="title">Title</Label>
                                        <Input
                                            id="title"
                                            name="title"
                                            required
                                            placeholder="e.g. Solar maintenance protocol"
                                        />
                                        {errors.title ? (
                                            <p className="text-xs text-destructive">
                                                {errors.title}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="document">
                                            Text or Markdown document
                                        </Label>
                                        <Input
                                            id="document"
                                            name="document"
                                            type="file"
                                            accept=".txt,.md,text/plain,text/markdown"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            TXT or MD, up to 10 MB.
                                        </p>
                                        {errors.document ? (
                                            <p className="text-xs text-destructive">
                                                {errors.document}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="source_url">
                                            Or public web source
                                        </Label>
                                        <Input
                                            id="source_url"
                                            name="source_url"
                                            type="url"
                                            placeholder="https://manufacturer.example/manual"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            HTML or plain-text pages. A private
                                            indexed copy is stored for
                                            consistent answers.
                                        </p>
                                        {errors.source_url ? (
                                            <p className="text-xs text-destructive">
                                                {errors.source_url}
                                            </p>
                                        ) : null}
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="content">
                                        Or write the knowledge directly
                                    </Label>
                                    <textarea
                                        id="content"
                                        name="content"
                                        rows={7}
                                        placeholder="Describe a procedure, diagnostic criterion, product rule or internal policy..."
                                        className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                    />
                                    {errors.content ? (
                                        <p className="text-xs text-destructive">
                                            {errors.content}
                                        </p>
                                    ) : null}
                                    <Button type="submit" disabled={processing}>
                                        <Upload /> Add and index
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </section>

                <section className="border-t pt-5">
                    <h2 className="font-semibold">
                        Corrections pending approval
                    </h2>
                    <p className="mb-3 text-sm text-muted-foreground">
                        Human revisions are only used by Arevia after an
                        administrator approves them.
                    </p>
                    {pendingFeedback.length === 0 ? (
                        <div className="border-y py-6 text-sm text-muted-foreground">
                            No corrections are pending review.
                        </div>
                    ) : (
                        <div className="divide-y border-y">
                            {pendingFeedback.map((feedback) => (
                                <div
                                    key={feedback.id}
                                    className="grid gap-4 py-4 lg:grid-cols-[minmax(0,1fr)_auto]"
                                >
                                    <div className="space-y-2 text-sm">
                                        <div className="font-medium">
                                            {feedback.installation_reference ||
                                                `Installation #${feedback.installation_id}`}{' '}
                                            · {feedback.service_type}
                                        </div>
                                        <div className="grid gap-3 md:grid-cols-2">
                                            <div>
                                                <span className="text-xs font-medium text-muted-foreground">
                                                    Original
                                                </span>
                                                <p>
                                                    {feedback.original_response
                                                        .summary ||
                                                        'No summary'}
                                                </p>
                                            </div>
                                            <div>
                                                <span className="text-xs font-medium text-muted-foreground">
                                                    Correction
                                                </span>
                                                <p>
                                                    {feedback.corrected_response
                                                        .summary ||
                                                        'No corrected summary'}
                                                </p>
                                            </div>
                                        </div>
                                        {feedback.notes ? (
                                            <p className="text-muted-foreground">
                                                Reviewer note: {feedback.notes}
                                            </p>
                                        ) : null}
                                        <p className="text-xs text-muted-foreground">
                                            {feedback.reviewer} ·{' '}
                                            {formatDate(feedback.created_at)}
                                        </p>
                                    </div>
                                    <Form
                                        action={`/knowledge/feedback/${feedback.id}/approve`}
                                        method="post"
                                    >
                                        <Button type="submit">
                                            <BookOpen /> Approve for RAG
                                        </Button>
                                    </Form>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                <section className="border-t pt-5">
                    <h2 className="mb-3 font-semibold">Indexed sources</h2>
                    {documents.length === 0 ? (
                        <div className="border-y py-8 text-center text-sm text-muted-foreground">
                            No private knowledge has been added yet.
                        </div>
                    ) : (
                        <div className="divide-y border-y">
                            {documents.map((document) => (
                                <div
                                    key={document.id}
                                    className="flex flex-wrap items-center justify-between gap-4 py-4"
                                >
                                    <div className="flex min-w-0 items-start gap-3">
                                        <FileText className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        <div className="min-w-0">
                                            <div className="font-medium">
                                                {document.title}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                v{document.version} ·{' '}
                                                {document.chunks_count}{' '}
                                                fragments ·{' '}
                                                {document.embedding_model ||
                                                    'No embeddings'}
                                                {document.original_filename
                                                    ? ` · ${document.original_filename}`
                                                    : ''}
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                {document.indexed_at
                                                    ? formatDate(
                                                          document.indexed_at,
                                                      )
                                                    : 'Pending'}
                                                {document.user_name
                                                    ? ` · ${document.user_name}`
                                                    : ''}
                                            </div>
                                            {document.source_url ? (
                                                <a
                                                    href={document.source_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                                                >
                                                    <ExternalLink className="size-3" />{' '}
                                                    Source page
                                                </a>
                                            ) : null}
                                        </div>
                                    </div>
                                    <div className="flex gap-1">
                                        <Form
                                            action={`/knowledge/${document.id}/reindex`}
                                            method="post"
                                        >
                                            <Button
                                                type="submit"
                                                size="icon"
                                                variant="ghost"
                                                title="Reindex"
                                            >
                                                <RefreshCw />
                                            </Button>
                                        </Form>
                                        <Form
                                            action={`/knowledge/${document.id}`}
                                            method="delete"
                                        >
                                            <Button
                                                type="submit"
                                                size="icon"
                                                variant="ghost"
                                                title="Delete"
                                            >
                                                <Trash2 />
                                            </Button>
                                        </Form>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

KnowledgeIndex.layout = {
    breadcrumbs: [{ title: 'Knowledge', href: '/knowledge' }],
};
