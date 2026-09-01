import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

type LocationRecord = {
    id: number;
    name: string;
    code?: string;
};

type PostalCodeMatch = {
    id: number;
    name: string;
    municipality: LocationRecord;
    province: LocationRecord;
    country: LocationRecord & { code: string };
};

type LocationFieldsProps = {
    prefix?: string;
    provinceField?: string;
    countryValue?: 'name' | 'code';
    defaults?: {
        country?: string | null;
        province?: string | null;
        municipality?: string | null;
        locality?: string | null;
        postalCode?: string | null;
    };
    errors: Record<string, string | undefined>;
};

const fieldClassName = 'grid gap-2';
const normalized = (value: string | null | undefined) =>
    value
        ?.normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLocaleLowerCase() ?? '';

const matchesName = (
    record: LocationRecord,
    value: string | null | undefined,
) => normalized(record.name) === normalized(value);

const matchesCountry = (
    record: LocationRecord,
    value: string | null | undefined,
) =>
    matchesName(record, value) ||
    (record.code === 'ES' && ['spain', 'espana'].includes(normalized(value)));

const countryLabel = (record: LocationRecord, locale: 'en' | 'es') =>
    record.code === 'ES' ? translate('Spain', locale) : record.name;

async function fetchLocations<T>(
    url: string,
    signal?: AbortSignal,
): Promise<T> {
    const response = await fetch(url, { signal });

    if (!response.ok) {
        throw new Error(`Location request failed with ${response.status}`);
    }

    return response.json() as Promise<T>;
}

export default function LocationFields({
    prefix = '',
    provinceField = 'province_state',
    countryValue = 'name',
    defaults,
    errors,
}: LocationFieldsProps) {
    const { locale, t } = useLocale();
    const [countries, setCountries] = useState<LocationRecord[]>([]);
    const [provinces, setProvinces] = useState<LocationRecord[]>([]);
    const [municipalities, setMunicipalities] = useState<LocationRecord[]>([]);
    const [localities, setLocalities] = useState<LocationRecord[]>([]);
    const [postalMatches, setPostalMatches] = useState<PostalCodeMatch[]>([]);
    const [country, setCountry] = useState(
        defaults?.country ?? (countryValue === 'code' ? 'ES' : 'Spain'),
    );
    const [province, setProvince] = useState(defaults?.province ?? '');
    const [municipality, setMunicipality] = useState(
        defaults?.municipality ?? '',
    );
    const [locality, setLocality] = useState(defaults?.locality ?? '');
    const [postalCode, setPostalCode] = useState(defaults?.postalCode ?? '');

    const name = (field: string) =>
        `${prefix}${field === 'province_state' ? provinceField : field}`;
    const submittedCountry =
        countryValue === 'code'
            ? (countries.find((item) => item.name === country)?.code ?? country)
            : country;

    useEffect(() => {
        const initialCountry =
            defaults?.country ?? (countryValue === 'code' ? 'ES' : 'Spain');
        const controller = new AbortController();

        async function hydrateSavedLocation() {
            try {
                const countryRecords = await fetchLocations<LocationRecord[]>(
                    '/api/locations/countries',
                    controller.signal,
                );
                setCountries(countryRecords);
                const selectedCountry = countryRecords.find((item) =>
                    matchesCountry(item, initialCountry),
                );

                if (!selectedCountry) {
                    return;
                }

                setCountry(selectedCountry.name);
                const provinceRecords = await fetchLocations<LocationRecord[]>(
                    `/api/locations/provinces?country_id=${selectedCountry.id}`,
                    controller.signal,
                );
                setProvinces(provinceRecords);
                const selectedProvince = provinceRecords.find((item) =>
                    matchesName(item, defaults?.province),
                );

                if (!selectedProvince) {
                    return;
                }

                setProvince(selectedProvince.name);
                const municipalityRecords = await fetchLocations<
                    LocationRecord[]
                >(
                    `/api/locations/municipalities?province_id=${selectedProvince.id}`,
                    controller.signal,
                );
                setMunicipalities(municipalityRecords);
                const selectedMunicipality = municipalityRecords.find((item) =>
                    matchesName(item, defaults?.municipality),
                );

                if (!selectedMunicipality) {
                    return;
                }

                setMunicipality(selectedMunicipality.name);
                const localityRecords = await fetchLocations<LocationRecord[]>(
                    `/api/locations/localities?municipality_id=${selectedMunicipality.id}`,
                    controller.signal,
                );
                setLocalities(localityRecords);
                const selectedLocality = localityRecords.find((item) =>
                    matchesName(item, defaults?.locality),
                );

                if (selectedLocality) {
                    setLocality(selectedLocality.name);
                }
            } catch (error) {
                if (
                    !(
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    )
                ) {
                    setCountries([]);
                }
            }
        }

        void hydrateSavedLocation();

        return () => controller.abort();
    }, [
        defaults?.country,
        defaults?.locality,
        defaults?.municipality,
        defaults?.province,
        countryValue,
    ]);

    async function loadProvinces(countryId: number) {
        const records = await fetchLocations<LocationRecord[]>(
            `/api/locations/provinces?country_id=${countryId}`,
        );
        setProvinces(records);
    }

    async function loadMunicipalities(provinceId: number) {
        const records = await fetchLocations<LocationRecord[]>(
            `/api/locations/municipalities?province_id=${provinceId}`,
        );
        setMunicipalities(records);
    }

    async function loadLocalities(municipalityId: number) {
        const records = await fetchLocations<LocationRecord[]>(
            `/api/locations/localities?municipality_id=${municipalityId}`,
        );
        setLocalities(records);
    }

    function lookupPostalCode(value: string) {
        setPostalCode(value);

        if (value.length < 5) {
            setPostalMatches([]);

            return;
        }

        fetch(`/api/locations/postal-codes/${value}`)
            .then((response) => response.json())
            .then((data) => {
                const matches = data.localities ?? [];
                setPostalMatches(matches);

                if (matches.length === 1) {
                    applyPostalMatch(matches[0]);
                }
            });
    }

    function applyPostalMatch(match: PostalCodeMatch) {
        setCountry(match.country.name);
        setProvince(match.province.name);
        setMunicipality(match.municipality.name);
        setLocality(match.name);
        loadProvinces(match.country.id);
        loadMunicipalities(match.province.id);
        loadLocalities(match.municipality.id);
    }

    return (
        <div className="grid gap-4 md:grid-cols-2">
            <div className={fieldClassName}>
                <Label htmlFor={name('country')}>{t.country}</Label>
                <Select
                    value={country}
                    onValueChange={(value) => {
                        const selected = countries.find(
                            (item) => item.name === value,
                        );
                        setCountry(value);
                        setProvince('');
                        setMunicipality('');
                        setLocality('');
                        setMunicipalities([]);
                        setLocalities([]);

                        if (selected) {
                            void loadProvinces(selected.id);
                        }
                    }}
                >
                    <SelectTrigger id={name('country')} className="w-full">
                        <SelectValue placeholder={t.country} />
                    </SelectTrigger>
                    <SelectContent>
                        {countries.map((item) => (
                            <SelectItem key={item.id} value={item.name}>
                                {countryLabel(item, locale)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input
                    type="hidden"
                    name={name('country')}
                    value={submittedCountry}
                />
                <InputError message={errors[name('country')]} />
            </div>

            <div className={fieldClassName}>
                <Label htmlFor={name('province_state')}>{t.province}</Label>
                <Select
                    value={province}
                    onValueChange={(value) => {
                        const selected = provinces.find(
                            (item) => item.name === value,
                        );
                        setProvince(value);
                        setMunicipality('');
                        setLocality('');
                        setLocalities([]);

                        if (selected) {
                            void loadMunicipalities(selected.id);
                        }
                    }}
                >
                    <SelectTrigger
                        id={name('province_state')}
                        className="w-full"
                    >
                        <SelectValue placeholder={t.selectProvince} />
                    </SelectTrigger>
                    <SelectContent>
                        {provinces.map((item) => (
                            <SelectItem key={item.id} value={item.name}>
                                {item.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input
                    type="hidden"
                    name={name('province_state')}
                    value={province}
                />
                <InputError message={errors[name('province_state')]} />
            </div>

            <div className={fieldClassName}>
                <Label htmlFor={name('city')}>{t.cityMunicipality}</Label>
                <Select
                    value={municipality}
                    onValueChange={(value) => {
                        const selected = municipalities.find(
                            (item) => item.name === value,
                        );
                        setMunicipality(value);
                        setLocality('');

                        if (selected) {
                            void loadLocalities(selected.id);
                        }
                    }}
                >
                    <SelectTrigger id={name('city')} className="w-full">
                        <SelectValue placeholder={t.selectMunicipality} />
                    </SelectTrigger>
                    <SelectContent>
                        {municipalities.map((item) => (
                            <SelectItem key={item.id} value={item.name}>
                                {item.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name={name('city')} value={municipality} />
                <InputError message={errors[name('city')]} />
            </div>

            <div className={fieldClassName}>
                <Label htmlFor={name('locality')}>{t.locality}</Label>
                <Select value={locality} onValueChange={setLocality}>
                    <SelectTrigger id={name('locality')} className="w-full">
                        <SelectValue placeholder={t.selectLocality} />
                    </SelectTrigger>
                    <SelectContent>
                        {localities.map((item) => (
                            <SelectItem key={item.id} value={item.name}>
                                {item.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name={name('locality')} value={locality} />
                <InputError message={errors[name('locality')]} />
            </div>

            <div className={fieldClassName}>
                <Label htmlFor={name('postal_code')}>{t.postalCode}</Label>
                <Input
                    id={name('postal_code')}
                    name={name('postal_code')}
                    value={postalCode}
                    onChange={(event) => lookupPostalCode(event.target.value)}
                    placeholder="14001"
                />
                <InputError message={errors[name('postal_code')]} />
            </div>

            {postalMatches.length > 1 && (
                <div className={fieldClassName}>
                    <Label>{t.selectLocality}</Label>
                    <div className="grid gap-2">
                        {postalMatches.map((match) => (
                            <button
                                key={match.id}
                                type="button"
                                className="rounded-md border px-3 py-2 text-left text-sm hover:bg-muted"
                                onClick={() => applyPostalMatch(match)}
                            >
                                {match.name}, {match.municipality.name},{' '}
                                {match.province.name}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
