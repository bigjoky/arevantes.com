import { Link } from '@inertiajs/react';
import { ArrowUpRight, CalendarClock, UserRound } from 'lucide-react';
import { commercialDetailUrl } from '@/lib/mobile/commercial-navigation';
import type { Opportunity } from '@/pages/opportunities/index';
import '../../../css/commercial-mobile.css';

export function MobileOpportunities({
    items,
    stages,
    filters,
    busy,
    onMove,
}: {
    items: Opportunity[];
    stages: Record<string, string>;
    filters: Record<string, string | undefined>;
    busy: boolean;
    onMove: (item: Opportunity, stage: string) => void;
}) {
    return (
        <div
            className="commercial-opportunity-list"
            aria-label="Oportunidades en tarjetas"
            aria-busy={busy}
        >
            {items.map((item) => (
                <article className="commercial-card" key={item.id}>
                    <div className="commercial-card-heading">
                        <span className="commercial-stage">
                            {stages[item.stage] ?? item.stage_label}
                        </span>
                        <small>{item.code}</small>
                    </div>
                    <Link
                        className="commercial-card-title"
                        href={commercialDetailUrl(
                            '/opportunities',
                            item.id,
                            filters,
                        )}
                    >
                        {item.title}
                        <ArrowUpRight aria-hidden="true" size={18} />
                    </Link>
                    {item.customer && (
                        <p>
                            <Link href={`/people/${item.customer.id}`}>
                                {item.customer.company_name ||
                                    [
                                        item.customer.first_name,
                                        item.customer.last_name,
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                            </Link>
                        </p>
                    )}
                    <div className="commercial-card-facts">
                        <strong>
                            {new Intl.NumberFormat('es-ES', {
                                style: 'currency',
                                currency: 'EUR',
                            }).format(Number(item.estimated_value))}
                        </strong>
                        <span>
                            <UserRound aria-hidden="true" size={14} />
                            {item.owner?.name ?? 'Sin asignar'}
                        </span>
                    </div>
                    {item.next_activity && (
                        <Link
                            className="commercial-next-action"
                            href={commercialDetailUrl(
                                '/opportunities',
                                item.id,
                                filters,
                            )}
                        >
                            <CalendarClock size={16} aria-hidden="true" />
                            <span>
                                {item.next_activity.title}
                                {item.next_activity.due_at && (
                                    <time dateTime={item.next_activity.due_at}>
                                        {new Date(
                                            item.next_activity.due_at,
                                        ).toLocaleDateString('es-ES')}
                                    </time>
                                )}
                            </span>
                        </Link>
                    )}
                    {!item.deleted_at && item.available_stages.length > 0 && (
                        <label className="commercial-stage-control">
                            Etapa de {item.code}
                            <select
                                value={item.stage}
                                disabled={busy}
                                onChange={(event) =>
                                    onMove(item, event.target.value)
                                }
                            >
                                {[
                                    ...new Set([
                                        item.stage,
                                        ...item.available_stages,
                                    ]),
                                ]
                                    .filter((key) => stages[key])
                                    .map((key) => (
                                        <option value={key} key={key}>
                                            {stages[key]}
                                        </option>
                                    ))}
                            </select>
                        </label>
                    )}
                </article>
            ))}
            {!items.length && (
                <p className="commercial-empty" role="status">
                    No hay oportunidades con estos filtros.
                </p>
            )}
        </div>
    );
}
