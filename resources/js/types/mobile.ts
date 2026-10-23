/** Additive contracts for mobile services. Unsupported fields stay absent; do
 * not infer a complete inbox or task system from the shared unread preview. */
export type MobileNotification = {
    id: string;
    message: string;
    href: string;
    readAt: string | null;
    createdAt: string;
    priority?: 'normal' | 'important';
};
export type MobileNotificationInbox = {
    items: MobileNotification[];
    unreadCount: number;
    nextCursor: string | null;
    capabilities: {
        history: boolean;
        individualRead: boolean;
        priorities: boolean;
    };
};
export type MobileTaskCreation = {
    available: boolean;
    reason?: string;
    /** Server-supplied authorized endpoint, when a generic task flow is approved. */
    endpoint?: string;
};
