import { createContext, useContext } from 'react';
import type { BreadcrumbItem } from '@/types';

export const ShellContext = createContext<{
    breadcrumbs: BreadcrumbItem[];
    year?: number;
}>({
    breadcrumbs: [],
});
export const useShellContext = () => useContext(ShellContext);
