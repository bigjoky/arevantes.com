import '@/../css/hr-mobile.css';
import { Head } from '@inertiajs/react';
import { Building2, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import Form from '@/components/projects/protected-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import ConfirmSubmit from '@/pages/human-resources/confirm-submit';
import MobileHrNavigation from '@/pages/human-resources/mobile-navigation';

type Department = {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    isActive: boolean;
    peopleCount: number;
};

export default function Departments({
    departments,
}: {
    departments: Department[];
}) {
    return (
        <>
            <Head title="Departamentos" />
            <div className="hr-mobile-workspace flex flex-1 flex-col gap-8 p-4">
                <MobileHrNavigation />
                <header>
                    <h1 className="text-xl font-semibold">Departamentos</h1>
                    <p className="text-sm text-muted-foreground">
                        Estructura organizativa disponible para las fichas
                        laborales.
                    </p>
                </header>

                <div className="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Nuevo departamento</CardTitle>
                            <CardDescription>
                                Añade una unidad a la organización.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/human-resources/departments"
                                method="post"
                                resetOnSuccess
                                className="grid gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="department-name">
                                                Nombre
                                            </Label>
                                            <Input
                                                id="department-name"
                                                name="name"
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="department-code">
                                                Código
                                            </Label>
                                            <Input
                                                id="department-code"
                                                name="code"
                                                placeholder="OPS"
                                            />
                                            <InputError message={errors.code} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="department-description">
                                                Descripción
                                            </Label>
                                            <Textarea
                                                id="department-description"
                                                name="description"
                                                rows={4}
                                            />
                                        </div>
                                        <input
                                            type="hidden"
                                            name="is_active"
                                            value="1"
                                        />
                                        <Button disabled={processing}>
                                            Crear departamento
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <div className="grid gap-4">
                        {departments.map((department) => (
                            <Card key={department.id}>
                                <CardHeader className="flex-row items-start justify-between gap-4">
                                    <div>
                                        <CardTitle className="flex items-center gap-2">
                                            <Building2 className="size-4" />
                                            {department.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {department.peopleCount}{' '}
                                            {department.peopleCount === 1
                                                ? 'persona'
                                                : 'personas'}
                                        </CardDescription>
                                    </div>
                                    <Badge
                                        variant={
                                            department.isActive
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {department.isActive
                                            ? 'Activo'
                                            : 'Inactivo'}
                                    </Badge>
                                </CardHeader>
                                <CardContent>
                                    <Form
                                        action={`/human-resources/departments/${department.id}`}
                                        method="put"
                                        className="grid gap-4 md:grid-cols-[minmax(0,1fr)_9rem]"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label
                                                        htmlFor={`name-${department.id}`}
                                                    >
                                                        Nombre
                                                    </Label>
                                                    <Input
                                                        id={`name-${department.id}`}
                                                        name="name"
                                                        defaultValue={
                                                            department.name
                                                        }
                                                        required
                                                    />
                                                    <InputError
                                                        message={errors.name}
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label
                                                        htmlFor={`code-${department.id}`}
                                                    >
                                                        Código
                                                    </Label>
                                                    <Input
                                                        id={`code-${department.id}`}
                                                        name="code"
                                                        defaultValue={
                                                            department.code ??
                                                            ''
                                                        }
                                                    />
                                                    <InputError
                                                        message={errors.code}
                                                    />
                                                </div>
                                                <div className="grid gap-2 md:col-span-2">
                                                    <Label
                                                        htmlFor={`description-${department.id}`}
                                                    >
                                                        Descripción
                                                    </Label>
                                                    <Textarea
                                                        id={`description-${department.id}`}
                                                        name="description"
                                                        defaultValue={
                                                            department.description ??
                                                            ''
                                                        }
                                                        rows={2}
                                                    />
                                                </div>
                                                <div className="flex flex-wrap items-center gap-3 md:col-span-2">
                                                    <label className="flex items-center gap-2 text-sm">
                                                        <input
                                                            type="hidden"
                                                            name="is_active"
                                                            value="0"
                                                        />
                                                        <input
                                                            type="checkbox"
                                                            name="is_active"
                                                            value="1"
                                                            defaultChecked={
                                                                department.isActive
                                                            }
                                                            className="size-4"
                                                        />
                                                        Departamento activo
                                                    </label>
                                                    <Button
                                                        size="sm"
                                                        disabled={processing}
                                                    >
                                                        Guardar cambios
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                    <Form
                                        action={`/human-resources/departments/${department.id}`}
                                        method="delete"
                                        className="mt-4 border-t pt-4"
                                    >
                                        <ConfirmSubmit
                                            size="sm"
                                            variant="ghost"
                                            description="¿Eliminar este departamento? Las fichas asociadas quedarán sin departamento."
                                        >
                                            <Trash2 />
                                            Eliminar
                                        </ConfirmSubmit>
                                    </Form>
                                </CardContent>
                            </Card>
                        ))}
                        {!departments.length ? (
                            <Card>
                                <CardContent className="py-12 text-center text-sm text-muted-foreground">
                                    Aún no hay departamentos configurados.
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>
                </div>
            </div>
        </>
    );
}
