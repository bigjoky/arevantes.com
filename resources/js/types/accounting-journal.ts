/** Additive journal detail contract. Optional fields are not supplied by the current controller.
 * See UI-JOURNAL in docs/roadmap.md before implementing the server payload.
 * undefined means unavailable; null/[] means loaded and empty.
 */
export type JournalDocument = {
    id: string;
    name: string;
    mime_type: string;
    size_bytes: number;
    attached_at: string;
    preview_href: string | null;
    download_href: string | null;
};

export type JournalHistoryEvent = {
    id: string;
    occurred_at: string;
    actor_name: string | null;
    description: string;
};

export type JournalDetailExtension = {
    journal?: { id: string; name: string } | null;
    notes?: string | null;
    documents?: JournalDocument[];
    history?: JournalHistoryEvent[];
};
