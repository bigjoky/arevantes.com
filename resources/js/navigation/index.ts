import { administrationNavigation } from '@/navigation/administration';
import { coreNavigation } from '@/navigation/core';
import { crmNavigation } from '@/navigation/crm';
import { financeNavigation } from '@/navigation/finance';
import { humanResourcesNavigation } from '@/navigation/human-resources';
import { operationsNavigation } from '@/navigation/operations';
import { supplyNavigation } from '@/navigation/supply';
import type { NavItem, PlatformAbilities } from '@/types';

export function buildNavigation(abilities: PlatformAbilities): NavItem[] {
    return [
        ...coreNavigation,
        ...crmNavigation(abilities),
        ...operationsNavigation(abilities),
        ...supplyNavigation(abilities),
        ...financeNavigation(abilities),
        ...humanResourcesNavigation(abilities),
        ...administrationNavigation(abilities),
    ];
}
