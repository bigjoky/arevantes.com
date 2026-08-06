import { Head, Link } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    Building2,
    CalendarClock,
    ClipboardCheck,
    KeyRound,
    UserPlus,
    UsersRound,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Summary = {
    total: number;
    withAccess: number;
    withoutAccess: number;
    roles: number;
    activeEmployment: number;
    pendingLeave: number;
    departments: number;
    overdueTasks: number;
};

type RoleDistribution = {
    role: string;
    count: number;
};

type StaffMember = {
    id: number;
    name: string;
    email: string | null;
    staffRole: string | null;
    accessRole: string | null;
    createdAt: string | null;
};

export default function HumanResourcesIndex({
    summary,
    roleDistribution,
    recentStaff,
}: {
    summary: Summary;
    roleDistribution: RoleDistribution[];
    recentStaff: StaffMember[];
}) {
    const metrics = [
        {
            label: 'Personas en plantilla',
            value: summary.total,
            detail: 'Perfiles activos en el directorio',
            icon: UsersRound,
        },
        {
            label: 'Con acceso',
            value: summary.withAccess,
            detail: 'Cuentas vinculadas al sistema',
            icon: KeyRound,
        },
        {
            label: 'Sin acceso',
            value: summary.withoutAccess,
            detail: 'Perfiles sin cuenta de usuario',
            icon: UserPlus,
        },
        {
            label: 'Puestos definidos',
            value: summary.roles,
            detail: 'Funciones distintas en el equipo',
            icon: BriefcaseBusiness,
        },
        {
            label: 'Relaciones activas',
            value: summary.activeEmployment,
            detail: 'Fichas laborales en estado activo',
            icon: BriefcaseBusiness,
        },
        {
            label: 'Ausencias pendientes',
            value: summary.pendingLeave,
            detail: 'Solicitudes que requieren revisión',
            icon: CalendarClock,
        },
        {
            label: 'Departamentos',
            value: summary.departments,
            detail: 'Unidades activas de la organización',
            icon: Building2,
        },
        {
            label: 'Tareas vencidas',
            value: summary.overdueTasks,
            detail: 'Acciones abiertas fuera de plazo',
            icon: ClipboardCheck,
        },
    ];

    return (
        <>
            <Head title="Recursos Humanos" />
            <div className="flex flex-1 flex-col gap-8 p-4">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Recursos Humanos
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Visión general del equipo, sus funciones y accesos.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/staff/create">
                            <UserPlus />
                            Añadir persona
                        </Link>
                    </Button>
                </header>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {metrics.map((metric) => {
                        const Icon = metric.icon;

                        return (
                            <Card key={metric.label}>
                                <CardHeader className="flex-row items-center justify-between space-y-0">
                                    <CardDescription>
                                        {metric.label}
                                    </CardDescription>
                                    <Icon className="size-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-3xl font-semibold">
                                        {metric.value}
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {metric.detail}
                                    </p>
                                </CardContent>
                            </Card>
                        );
                    })}
                </section>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
                    <Card>
                        <CardHeader className="flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle>Últimas incorporaciones</CardTitle>
                                <CardDescription>
                                    Perfiles añadidos recientemente al directorio.
                                </CardDescription>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/staff">Ver equipo</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {recentStaff.length ? (
                                <div className="divide-y">
                                    {recentStaff.map((person) => (
                                        <Link
                                            key={person.id}
                                            href={`/staff/${person.id}`}
                                            className="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0 last:pb-0"
                                        >
                                            <div className="min-w-0">
                                                <div className="truncate font-medium">
                                                    {person.name ||
                                                        'Sin nombre'}
                                                </div>
                                                <div className="truncate text-sm text-muted-foreground">
                                                    {person.email ||
                                                        'Sin correo electrónico'}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline">
                                                    {person.staffRole ||
                                                        'Sin puesto'}
                                                </Badge>
                                                <Badge
                                                    variant={
                                                        person.accessRole
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {person.accessRole
                                                        ? `Acceso: ${person.accessRole}`
                                                        : 'Sin acceso'}
                                                </Badge>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <div className="py-10 text-center text-sm text-muted-foreground">
                                    Todavía no hay personas en la plantilla.
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Distribución por puesto</CardTitle>
                            <CardDescription>
                                Composición actual del equipo.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {roleDistribution.length ? (
                                <div className="space-y-4">
                                    {roleDistribution.map((item) => (
                                        <div
                                            key={item.role}
                                            className="space-y-2"
                                        >
                                            <div className="flex items-center justify-between gap-4 text-sm">
                                                <span className="truncate font-medium">
                                                    {item.role}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {item.count}
                                                </span>
                                            </div>
                                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-primary"
                                                    style={{
                                                        width: `${Math.max(
                                                            (item.count /
                                                                summary.total) *
                                                                100,
                                                            4,
                                                        )}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Los puestos aparecerán al añadir personal.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
