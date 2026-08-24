export const serviceLabels = {
    solar: 'Solar installation',
    ev_charger: 'EV charging point',
    hvac: 'Aircon / clima',
    electrical: 'Electrical installation',
    security: 'Alarm & security system',
} as const;

export type ServiceType = keyof typeof serviceLabels;

const legacyServiceLabels: Record<string, string> = {
    solar_pv: serviceLabels.solar,
};

export function serviceLabel(serviceType: string): string {
    return (
        serviceLabels[serviceType as ServiceType] ??
        legacyServiceLabels[serviceType] ??
        serviceType.replaceAll('_', ' ')
    );
}
