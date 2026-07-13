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

export default function LocationFields({
    prefix = '',
    defaults,
    errors,
}: LocationFieldsProps) {
    const { t } = useLocale();
    const [countries, setCountries] = useState<LocationRecord[]>([]);
    const [provinces, setProvinces] = useState<LocationRecord[]>([]);
    const [municipalities, setMunicipalities] = useState<LocationRecord[]>([]);
    const [localities, setLocalities] = useState<LocationRecord[]>([]);
    const [postalMatches, setPostalMatches] = useState<PostalCodeMatch[]>([]);
    const [country, setCountry] = useState(defaults?.country ?? 'Spain');
    const [province, setProvince] = useState(defaults?.province ?? '');
    const [municipality, setMunicipality] = useState(
        defaults?.municipality ?? '',
    );
    const [locality, setLocality] = useState(defaults?.locality ?? '');
    const [postalCode, setPostalCode] = useState(defaults?.postalCode ?? '');

    const name = (field: string) => `${prefix}${field}`;

    useEffect(() => {
        const initialCountry = defaults?.country ?? 'Spain';

        fetch('/api/locations/countries')
            .then((response) => response.json())
            .then((data) => {
                setCountries(data);
                const selected = data.find(
                    (item: LocationRecord) => item.name === initialCountry,
                );

                if (selected) {
                    loadProvinces(selected.id);
                }
            });
    }, [defaults?.country]);

    function loadProvinces(countryId: number) {
        fetch(`/api/locations/provinces?country_id=${countryId}`)
            .then((response) => response.json())
            .then(setProvinces);
    }

    function loadMunicipalities(provinceId: number) {
        fetch(`/api/locations/municipalities?province_id=${provinceId}`)
            .then((response) => response.json())
            .then(setMunicipalities);
    }

    function loadLocalities(municipalityId: number) {
        fetch(`/api/locations/localities?municipality_id=${municipalityId}`)
            .then((response) => response.json())
            .then(setLocalities);
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
                            loadProvinces(selected.id);
                        }
                    }}
                >
                    <SelectTrigger id={name('country')} className="w-full">
                        <SelectValue placeholder={t.country} />
                    </SelectTrigger>
                    <SelectContent>
                        {countries.map((item) => (
                            <SelectItem key={item.id} value={item.name}>
                                {item.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name={name('country')} value={country} />
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
                            loadMunicipalities(selected.id);
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
                            loadLocalities(selected.id);
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
