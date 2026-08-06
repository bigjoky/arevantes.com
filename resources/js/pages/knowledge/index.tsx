import { Form, Head } from '@inertiajs/react';
import {
    BookOpen,
    ExternalLink,
    FileText,
    RefreshCw,
    Settings2,
    Trash2,
    Upload,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Document = {
    id: number;
    title: string;
    area: string;
    allowed_roles: string[] | null;
    confidentiality: string;
    status: string;
    failure_reason: string | null;
    original_filename: string | null;
    source_url: string | null;
    file_size: number | null;
    version: number;
    embedding_model: string | null;
    indexed_at: string | null;
    chunks_count: number;
    versions_count: number;
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
    queue,
    metrics,
    evaluationQuestions,
}: {
    documents: Document[];
    pendingFeedback: PendingFeedback[];
    queue: Record<string, number>;
    metrics: {
        responses: number;
        average_score: number;
        average_duration_ms: number;
        cost_micros: number;
        human_reviews: number;
    };
    evaluationQuestions: Record<string, string[]>;
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

                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Metric
                        label="Evaluated responses"
                        value={metrics.responses}
                    />
                    <Metric
                        label="Average quality"
                        value={`${metrics.average_score}/100`}
                    />
                    <Metric
                        label="Average time"
                        value={`${(metrics.average_duration_ms / 1000).toFixed(1)} s`}
                    />
                    <Metric
                        label="Estimated cost"
                        value={`€${(metrics.cost_micros / 1_000_000).toFixed(4)}`}
                    />
                </section>

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
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="area">Area</Label>
                                            <select
                                                id="area"
                                                name="area"
                                                defaultValue="general"
                                                className="h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                                            >
                                                <option value="general">
                                                    General
                                                </option>
                                                <option value="crm">CRM</option>
                                                <option value="installations">
                                                    Installations
                                                </option>
                                                <option value="finance">
                                                    Finance
                                                </option>
                                                <option value="operations">
                                                    Operations
                                                </option>
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="confidentiality">
                                                Confidentiality
                                            </Label>
                                            <select
                                                id="confidentiality"
                                                name="confidentiality"
                                                defaultValue="internal"
                                                className="h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                                            >
                                                <option value="internal">
                                                    Internal
                                                </option>
                                                <option value="restricted">
                                                    Restricted
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <fieldset className="space-y-2">
                                        <Label>
                                            Roles allowed to consult it
                                        </Label>
                                        <div className="flex gap-4 text-sm">
                                            {[
                                                'admin',
                                                'commercial',
                                                'technician',
                                            ].map((role) => (
                                                <label
                                                    key={role}
                                                    className="flex items-center gap-2 capitalize"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name="allowed_roles[]"
                                                        value={role}
                                                        defaultChecked
                                                    />{' '}
                                                    {role}
                                                </label>
                                            ))}
                                        </div>
                                    </fieldset>
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
                    <h2 className="font-semibold">Document queue</h2>
                    <p className="mb-3 text-sm text-muted-foreground">
                        Pending, failed, quarantined and stale sources remain
                        visible until reviewed.
                    </p>
                    <div className="flex flex-wrap gap-2 text-sm">
                        {[
                            'pending',
                            'processing',
                            'failed',
                            'quarantined',
                            'stale',
                            'indexed',
                        ].map((status) => (
                            <span
                                key={status}
                                className="rounded-md border px-3 py-1.5 capitalize"
                            >
                                {status}:{' '}
                                {queue[status] ??
                                    (status === 'stale'
                                        ? documents.filter(
                                              (item) => item.status === 'stale',
                                          ).length
                                        : 0)}
                            </span>
                        ))}
                    </div>
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
                                <div key={document.id} className="py-4">
                                    <div className="flex flex-wrap items-center justify-between gap-4">
                                        <div className="flex min-w-0 items-start gap-3">
                                            <FileText className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                            <div className="min-w-0">
                                                <div className="font-medium">
                                                    {document.title}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    <span className="font-medium uppercase">
                                                        {document.status}
                                                    </span>{' '}
                                                    · {document.area} ·{' '}
                                                    {document.confidentiality} ·
                                                    v{document.version} (
                                                    {document.versions_count}{' '}
                                                    saved) ·{' '}
                                                    {document.chunks_count}{' '}
                                                    fragments ·{' '}
                                                    {document.embedding_model ||
                                                        'No embeddings'}
                                                    {document.original_filename
                                                        ? ` · ${document.original_filename}`
                                                        : ''}
                                                </div>
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    Roles:{' '}
                                                    {document.allowed_roles?.join(
                                                        ', ',
                                                    ) || 'all'}
                                                </div>
                                                {document.failure_reason ? (
                                                    <p className="mt-1 max-w-2xl text-xs text-destructive">
                                                        {
                                                            document.failure_reason
                                                        }
                                                    </p>
                                                ) : null}
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
                                                        href={
                                                            document.source_url
                                                        }
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
                                    <details className="mt-3 rounded-md border bg-muted/20">
                                        <summary className="flex cursor-pointer list-none items-center gap-2 px-3 py-2 text-sm font-medium">
                                            <Settings2 className="size-4" />{' '}
                                            Access and new version
                                        </summary>
                                        <Form
                                            action={`/knowledge/${document.id}`}
                                            method="patch"
                                            encType="multipart/form-data"
                                            className="grid gap-4 border-t p-4 lg:grid-cols-2"
                                        >
                                            {({ processing, errors }) => (
                                                <>
                                                    <div className="space-y-3">
                                                        <div className="space-y-2">
                                                            <Label
                                                                htmlFor={`title-${document.id}`}
                                                            >
                                                                Title
                                                            </Label>
                                                            <Input
                                                                id={`title-${document.id}`}
                                                                name="title"
                                                                defaultValue={
                                                                    document.title
                                                                }
                                                                required
                                                            />
                                                        </div>
                                                        <div className="grid gap-3 sm:grid-cols-2">
                                                            <select
                                                                name="area"
                                                                defaultValue={
                                                                    document.area
                                                                }
                                                                className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                                            >
                                                                {[
                                                                    'general',
                                                                    'crm',
                                                                    'installations',
                                                                    'finance',
                                                                    'operations',
                                                                ].map(
                                                                    (area) => (
                                                                        <option
                                                                            key={
                                                                                area
                                                                            }
                                                                            value={
                                                                                area
                                                                            }
                                                                        >
                                                                            {
                                                                                area
                                                                            }
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                            <select
                                                                name="confidentiality"
                                                                defaultValue={
                                                                    document.confidentiality
                                                                }
                                                                className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                                            >
                                                                <option value="internal">
                                                                    internal
                                                                </option>
                                                                <option value="restricted">
                                                                    restricted
                                                                </option>
                                                            </select>
                                                        </div>
                                                        <div className="flex flex-wrap gap-4 text-sm">
                                                            {[
                                                                'admin',
                                                                'commercial',
                                                                'technician',
                                                            ].map((role) => (
                                                                <label
                                                                    key={role}
                                                                    className="flex items-center gap-2 capitalize"
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        name="allowed_roles[]"
                                                                        value={
                                                                            role
                                                                        }
                                                                        defaultChecked={
                                                                            document.allowed_roles ===
                                                                                null ||
                                                                            document.allowed_roles.includes(
                                                                                role,
                                                                            )
                                                                        }
                                                                    />{' '}
                                                                    {role}
                                                                </label>
                                                            ))}
                                                        </div>
                                                        {Object.keys(errors)
                                                            .length ? (
                                                            <p className="text-xs text-destructive">
                                                                Review the
                                                                highlighted
                                                                knowledge
                                                                fields.
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                    <div className="space-y-3">
                                                        <div className="space-y-2">
                                                            <Label
                                                                htmlFor={`replacement-${document.id}`}
                                                            >
                                                                Optional
                                                                replacement text
                                                            </Label>
                                                            <textarea
                                                                id={`replacement-${document.id}`}
                                                                name="content"
                                                                rows={3}
                                                                placeholder="Leave empty to update access only"
                                                                className="flex w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                                                            />
                                                        </div>
                                                        <Input
                                                            name="document"
                                                            type="file"
                                                            accept=".txt,.md,text/plain,text/markdown"
                                                        />
                                                        <Input
                                                            name="source_url"
                                                            type="url"
                                                            placeholder="Or replace with a public URL"
                                                        />
                                                        <Button
                                                            type="submit"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Save settings or
                                                            version
                                                        </Button>
                                                    </div>
                                                </>
                                            )}
                                        </Form>
                                    </details>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                <section className="border-t pt-5">
                    <h2 className="font-semibold">Evaluation set by module</h2>
                    <p className="mb-3 text-sm text-muted-foreground">
                        Automatic scoring is complemented by{' '}
                        {metrics.human_reviews} human reviews.
                    </p>
                    <div className="grid gap-4 lg:grid-cols-2">
                        {Object.entries(evaluationQuestions).map(
                            ([module, questions]) => (
                                <div
                                    key={module}
                                    className="rounded-md border p-4"
                                >
                                    <h3 className="mb-2 font-medium capitalize">
                                        {module} · {questions.length} questions
                                    </h3>
                                    <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                                        {questions.map((question) => (
                                            <li key={question}>{question}</li>
                                        ))}
                                    </ol>
                                </div>
                            ),
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

function Metric({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-md border p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-xl font-semibold">{value}</div>
        </div>
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
