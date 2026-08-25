export type PublicProjectImage = {
    id: number;
    url: string;
    absolute_url: string;
    alt: string;
    caption: string | null;
    width: number;
    height: number;
    position: number;
};

export type PublicProjectSummary = {
    title: string;
    slug: string;
    service_slug: string;
    service_name: string;
    location: string | null;
    summary: string;
    completed_on: string | null;
    completed_year: string | null;
    href: string;
    image: PublicProjectImage | null;
};

export type PublicProjectDetail = PublicProjectSummary & {
    description: string | null;
    images: PublicProjectImage[];
};
