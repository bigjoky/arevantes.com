import 'maplibre-gl/dist/maplibre-gl.css';

import maplibregl, {
    type LngLatLike,
    type Map as MapLibreMap,
    type MapOptions,
    type Marker as MapLibreMarker,
    type Popup as MapLibrePopup,
} from 'maplibre-gl';
import {
    createContext,
    type ReactNode,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { createPortal } from 'react-dom';
import { Locate, Minus, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const defaultStyles = {
    light: 'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json',
    dark: 'https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json',
};

type MapContextValue = {
    map: MapLibreMap | null;
    isLoaded: boolean;
};

const MapContext = createContext<MapContextValue | null>(null);

function useMap() {
    const context = useContext(MapContext);

    if (!context) {
        throw new Error('Map components must be used inside <Map>.');
    }

    return context;
}

type MapProps = {
    children?: ReactNode;
    className?: string;
    center?: LngLatLike;
    zoom?: number;
    theme?: 'light' | 'dark';
} & Omit<MapOptions, 'container' | 'style' | 'center' | 'zoom'>;

function Map({
    children,
    className,
    center = [-3.7038, 40.4168],
    zoom = 5.2,
    theme,
    ...options
}: MapProps) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const [map, setMap] = useState<MapLibreMap | null>(null);
    const [isLoaded, setIsLoaded] = useState(false);
    const { resolvedAppearance } = useAppearance();
    const resolvedTheme = theme ?? resolvedAppearance;

    useEffect(() => {
        if (!containerRef.current) {
            return;
        }

        const instance = new maplibregl.Map({
            container: containerRef.current,
            style: defaultStyles[resolvedTheme],
            center,
            zoom,
            attributionControl: { compact: true },
            renderWorldCopies: false,
            ...options,
        });

        instance.on('load', () => setIsLoaded(true));
        setMap(instance);

        return () => {
            instance.remove();
            setMap(null);
            setIsLoaded(false);
        };
        // The map instance is intentionally initialized once.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!map) {
            return;
        }

        map.setStyle(defaultStyles[resolvedTheme]);
    }, [map, resolvedTheme]);

    const value = useMemo(() => ({ map, isLoaded }), [map, isLoaded]);

    return (
        <MapContext.Provider value={value}>
            <div
                ref={containerRef}
                className={cn('relative h-full w-full', className)}
            >
                {!isLoaded && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center bg-background/70 text-sm text-muted-foreground backdrop-blur-xs">
                        Loading map...
                    </div>
                )}
                {map && children}
            </div>
        </MapContext.Provider>
    );
}

type MapControlsProps = {
    className?: string;
};

function MapControls({ className }: MapControlsProps) {
    const { map } = useMap();

    return (
        <div
            className={cn(
                'absolute top-3 right-3 z-10 grid gap-1 rounded-md border bg-background/95 p-1 shadow-sm backdrop-blur',
                className,
            )}
        >
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8"
                onClick={() => map?.zoomIn()}
                aria-label="Zoom in"
            >
                <Plus className="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8"
                onClick={() => map?.zoomOut()}
                aria-label="Zoom out"
            >
                <Minus className="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8"
                onClick={() => map?.flyTo({ center: [-3.7038, 40.4168], zoom: 5.2 })}
                aria-label="Reset map"
            >
                <Locate className="size-4" />
            </Button>
        </div>
    );
}

type MapMarkerProps = {
    longitude: number;
    latitude: number;
    children: ReactNode;
    onClick?: () => void;
};

function MapMarker({
    longitude,
    latitude,
    children,
    onClick,
}: MapMarkerProps) {
    const { map } = useMap();
    const markerRef = useRef<MapLibreMarker | null>(null);
    const [element] = useState(() => document.createElement('div'));

    useEffect(() => {
        if (!map) {
            return;
        }

        markerRef.current = new maplibregl.Marker({
            element,
            anchor: 'bottom',
        })
            .setLngLat([longitude, latitude])
            .addTo(map);

        return () => {
            markerRef.current?.remove();
            markerRef.current = null;
        };
    }, [element, latitude, longitude, map]);

    useEffect(() => {
        markerRef.current?.setLngLat([longitude, latitude]);
    }, [latitude, longitude]);

    useEffect(() => {
        if (!onClick) {
            return;
        }

        const handleMarkerClick = (event: MouseEvent) => {
            event.stopPropagation();
            onClick();
        };

        element.addEventListener('click', handleMarkerClick);

        return () => element.removeEventListener('click', handleMarkerClick);
    }, [element, onClick]);

    return createPortal(children, element);
}

type MapPopupProps = {
    longitude: number;
    latitude: number;
    children: ReactNode;
    onClose?: () => void;
    className?: string;
    maxWidth?: string;
};

function MapPopup({
    longitude,
    latitude,
    children,
    onClose,
    className,
    maxWidth = '360px',
}: MapPopupProps) {
    const { map } = useMap();
    const popupRef = useRef<MapLibrePopup | null>(null);
    const onCloseRef = useRef(onClose);
    const [element] = useState(() => document.createElement('div'));

    useEffect(() => {
        onCloseRef.current = onClose;
    }, [onClose]);

    useEffect(() => {
        if (!map) {
            return;
        }

        let disposed = false;
        const popup = new maplibregl.Popup({
            anchor: 'bottom',
            closeButton: true,
            closeOnClick: true,
            focusAfterOpen: false,
            maxWidth,
            offset: 28,
        })
            .setDOMContent(element)
            .setLngLat([longitude, latitude])
            .addTo(map);

        const handleClose = () => {
            if (!disposed) {
                onCloseRef.current?.();
            }
        };

        popup.on('close', handleClose);
        popupRef.current = popup;

        return () => {
            disposed = true;
            popup.off('close', handleClose);
            popup.remove();
            popupRef.current = null;
        };
    }, [element, latitude, longitude, map, maxWidth]);

    useEffect(() => {
        popupRef.current?.setLngLat([longitude, latitude]);
    }, [latitude, longitude]);

    return createPortal(
        <div className={cn('min-w-64', className)}>{children}</div>,
        element,
    );
}

function MarkerContent({
    children,
    className,
    ariaLabel,
}: {
    children?: ReactNode;
    className?: string;
    ariaLabel?: string;
}) {
    return (
        <button
            type="button"
            aria-label={ariaLabel}
            className={cn(
                'grid size-9 place-items-center rounded-full border-2 border-background bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-110 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                className,
            )}
        >
            {children}
        </button>
    );
}

export { Map, MapControls, MapMarker, MapPopup, MarkerContent, useMap };
