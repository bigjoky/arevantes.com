import { lazy, Suspense } from 'react';
import type { ComponentProps } from 'react';
import ProjectDetail from '@/components/projects/project-detail';
import type { DetailProps } from '@/components/projects/project-detail';
import { Skeleton } from '@/components/ui/skeleton';
const LegacyInstallation = lazy(() => import('./legacy-show'));
type Props = { workspace?: DetailProps } & Partial<
    ComponentProps<typeof LegacyInstallation>
>;
export default function InstallationShow(props: Props) {
    return props.workspace ? (
        <ProjectDetail {...props.workspace} />
    ) : (
        <Suspense fallback={<Skeleton className="h-40 w-full" />}>
            <LegacyInstallation
                {...(props as ComponentProps<typeof LegacyInstallation>)}
            />
        </Suspense>
    );
}
