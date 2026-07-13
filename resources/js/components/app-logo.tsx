import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-zinc-950 text-white shadow-sm">
                <AppLogoIcon className="size-5 fill-current" />
            </div>
            <div className="ml-1 flex flex-1 items-center text-left text-sm">
                <span className="truncate leading-tight font-bold tracking-[0.08em]">
                    AREVANTES
                </span>
            </div>
        </>
    );
}
