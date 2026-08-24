import { Form } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import InputError from '@/components/input-error';
import LocationFields from '@/components/locations/location-fields';
import { Button } from '@/components/ui/button';
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

export type PersonFormValue = {
    id?: number;
    category?: 'customer' | 'staff';
    customer_type?: string | null;
    lifecycle_status?: string | null;
    first_name?: string;
    last_name?: string | null;
    company_name?: string | null;
    tax_id?: string | null;
    staff_role?: string | null;
    document_type?: string | null;
    document_number?: string | null;
    phone?: string | null;
    email?: string | null;
    billing_email?: string | null;
    address_line_1?: string | null;
    address_line_2?: string | null;
    city?: string | null;
    locality?: string | null;
    province_state?: string | null;
    postal_code?: string | null;
    country?: string | null;
    preferred_language?: string | null;
    preferred_contact_channel?: string | null;
    do_not_contact?: boolean;
    service_interests?: string[] | null;
    lead_source?: string | null;
    data_source?: string | null;
    emergency_contact_name?: string | null;
    emergency_contact_phone?: string | null;
    notes?: string | null;
};

type PersonFormProps = {
    action: string;
    method?: 'post' | 'patch';
    person?: PersonFormValue;
    forcedCategory?: 'customer' | 'staff';
    submitLabel?: string;
};

const fieldClassName = 'grid gap-2';
const textareaClassName =
    'rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export default function PersonForm({
    action,
    method = 'post',
    person,
    forcedCategory,
    submitLabel = 'Add person',
}: PersonFormProps) {
    const { t } = useLocale();
    const category = forcedCategory ?? person?.category ?? 'customer';
    const isCustomer = category === 'customer';
    const isStaff = category === 'staff';
    const finalSubmitLabel =
        submitLabel === 'Add person'
            ? t.addPerson
            : submitLabel === 'Save person'
              ? t.savePerson
              : submitLabel;

    return (
        <Form
            action={action}
            method={method}
            options={{ preserveScroll: true }}
            resetOnSuccess={method === 'post'}
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    {forcedCategory && (
                        <input
                            type="hidden"
                            name="category"
                            value={forcedCategory}
                        />
                    )}

                    <div className="grid gap-4 md:grid-cols-3">
                        {!forcedCategory && (
                            <div className={fieldClassName}>
                                <Label htmlFor="category">{t.personType}</Label>
                                <Select
                                    name="category"
                                    defaultValue={
                                        person?.category ?? 'customer'
                                    }
                                >
                                    <SelectTrigger
                                        id="category"
                                        className="w-full"
                                    >
                                        <SelectValue
                                            placeholder={t.personType}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="customer">
                                            {t.customer}
                                        </SelectItem>
                                        <SelectItem value="staff">
                                            {t.staff}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.category} />
                            </div>
                        )}

                        {isCustomer && (
                            <div className={fieldClassName}>
                                <Label htmlFor="customer_type">
                                    {t.customerType}
                                </Label>
                                <Select
                                    name="customer_type"
                                    defaultValue={person?.customer_type ?? ''}
                                >
                                    <SelectTrigger
                                        id="customer_type"
                                        className="w-full"
                                    >
                                        <SelectValue
                                            placeholder={t.selectCustomerType}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="residential">
                                            {t.residential}
                                        </SelectItem>
                                        <SelectItem value="business">
                                            {t.business}
                                        </SelectItem>
                                        <SelectItem value="community">
                                            {t.community}
                                        </SelectItem>
                                        <SelectItem value="public_sector">
                                            {t.publicSector}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.customer_type} />
                            </div>
                        )}

                        {isCustomer && (
                            <div className={fieldClassName}>
                                <Label htmlFor="lifecycle_status">
                                    Estado del cliente
                                </Label>
                                <Select
                                    name="lifecycle_status"
                                    defaultValue={
                                        person?.lifecycle_status ?? 'active'
                                    }
                                >
                                    <SelectTrigger
                                        id="lifecycle_status"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="lead">
                                            Lead
                                        </SelectItem>
                                        <SelectItem value="active">
                                            Activo
                                        </SelectItem>
                                        <SelectItem value="inactive">
                                            Inactivo
                                        </SelectItem>
                                        <SelectItem value="former">
                                            Antiguo cliente
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.lifecycle_status} />
                            </div>
                        )}

                        {isStaff && (
                            <Field
                                id="staff_role"
                                label={t.staffRole}
                                placeholder="Installer, sales, admin..."
                                defaultValue={person?.staff_role}
                                error={errors.staff_role}
                            />
                        )}
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field
                            id="first_name"
                            label={t.firstName}
                            placeholder={t.firstName}
                            defaultValue={person?.first_name}
                            error={errors.first_name}
                            required
                        />
                        <Field
                            id="last_name"
                            label={t.lastName}
                            placeholder={t.lastName}
                            defaultValue={person?.last_name}
                            error={errors.last_name}
                        />
                        <Field
                            id="company_name"
                            label="Company / community"
                            placeholder="Company, community, property manager..."
                            defaultValue={person?.company_name}
                            error={errors.company_name}
                        />
                        <Field
                            id="tax_id"
                            label={t.taxId}
                            placeholder="CIF, VAT, tax reference..."
                            defaultValue={person?.tax_id}
                            error={errors.tax_id}
                        />
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <div className={fieldClassName}>
                            <Label htmlFor="document_type">{t.idType}</Label>
                            <Select
                                name="document_type"
                                defaultValue={person?.document_type ?? ''}
                            >
                                <SelectTrigger
                                    id="document_type"
                                    className="w-full"
                                >
                                    <SelectValue placeholder={t.chooseIdType} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="passport">
                                        Passport
                                    </SelectItem>
                                    <SelectItem value="nif">NIF</SelectItem>
                                    <SelectItem value="nie">NIE</SelectItem>
                                    <SelectItem value="other">Other</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.document_type} />
                        </div>
                        <Field
                            id="document_number"
                            label={t.idNumber}
                            placeholder="Passport, NIF, NIE..."
                            defaultValue={person?.document_number}
                            error={errors.document_number}
                        />
                        {isCustomer && (
                            <Field
                                id="lead_source"
                                label={t.leadSource}
                                placeholder="Referral, web, Google..."
                                defaultValue={person?.lead_source}
                                error={errors.lead_source}
                            />
                        )}
                        {isCustomer && (
                            <Field
                                id="data_source"
                                label="Origen del dato"
                                placeholder="Formulario web, evento, importación..."
                                defaultValue={person?.data_source}
                                error={errors.data_source}
                            />
                        )}
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <Field
                            id="phone"
                            label={t.phone}
                            type="tel"
                            placeholder="+34 600 000 000"
                            defaultValue={person?.phone}
                            error={errors.phone}
                        />
                        <Field
                            id="email"
                            label={t.email}
                            type="email"
                            placeholder="name@example.com"
                            defaultValue={person?.email}
                            error={errors.email}
                        />
                        {isCustomer && (
                            <Field
                                id="billing_email"
                                label={t.billingEmail}
                                type="email"
                                placeholder="billing@example.com"
                                defaultValue={person?.billing_email}
                                error={errors.billing_email}
                            />
                        )}
                    </div>

                    {isCustomer && (
                        <div className="grid gap-4 rounded-md border p-4 md:grid-cols-[1fr_auto]">
                            <div className={fieldClassName}>
                                <Label htmlFor="preferred_contact_channel">
                                    Canal de contacto preferido
                                </Label>
                                <Select
                                    name="preferred_contact_channel"
                                    defaultValue={
                                        person?.preferred_contact_channel ?? ''
                                    }
                                >
                                    <SelectTrigger
                                        id="preferred_contact_channel"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Sin preferencia" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="email">
                                            Email
                                        </SelectItem>
                                        <SelectItem value="phone">
                                            Teléfono
                                        </SelectItem>
                                        <SelectItem value="whatsapp">
                                            WhatsApp
                                        </SelectItem>
                                        <SelectItem value="sms">SMS</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={errors.preferred_contact_channel}
                                />
                            </div>
                            <label className="flex items-center gap-3 self-end rounded-md border px-4 py-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="do_not_contact"
                                    value="1"
                                    defaultChecked={
                                        person?.do_not_contact ?? false
                                    }
                                />
                                No contactar comercialmente
                            </label>
                        </div>
                    )}

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field
                            id="address_line_1"
                            label={t.address}
                            placeholder="Street and number"
                            defaultValue={person?.address_line_1}
                            error={errors.address_line_1}
                        />
                        <Field
                            id="address_line_2"
                            label={t.address2}
                            placeholder="Apartment, floor..."
                            defaultValue={person?.address_line_2}
                            error={errors.address_line_2}
                        />
                    </div>

                    <LocationFields
                        errors={errors}
                        defaults={{
                            country: person?.country,
                            province: person?.province_state,
                            municipality: person?.city,
                            locality: person?.locality,
                            postalCode: person?.postal_code,
                        }}
                    />

                    <div className="grid gap-4 md:grid-cols-2">
                        {isCustomer && (
                            <TextareaField
                                id="service_interests"
                                label={t.serviceInterests}
                                placeholder="Solar panels&#10;Electrical installation&#10;Alarm & security system"
                                defaultValue={person?.service_interests?.join(
                                    '\n',
                                )}
                                error={errors.service_interests}
                            />
                        )}
                        <TextareaField
                            id="notes"
                            label={t.notes}
                            placeholder="Preferences, access needs, invoice notes..."
                            defaultValue={person?.notes}
                            error={errors.notes}
                        />
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Field
                            id="emergency_contact_name"
                            label={t.emergencyContact}
                            placeholder="Contact name"
                            defaultValue={person?.emergency_contact_name}
                            error={errors.emergency_contact_name}
                        />
                        <Field
                            id="emergency_contact_phone"
                            label={t.emergencyPhone}
                            type="tel"
                            placeholder="+34 600 000 000"
                            defaultValue={person?.emergency_contact_phone}
                            error={errors.emergency_contact_phone}
                        />
                    </div>

                    <Button disabled={processing}>
                        <UserPlus />
                        {finalSubmitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}

function Field({
    id,
    label,
    error,
    defaultValue,
    required,
    type = 'text',
    placeholder,
}: {
    id: string;
    label: string;
    error?: string;
    defaultValue?: string | null;
    required?: boolean;
    type?: string;
    placeholder?: string;
}) {
    return (
        <div className={fieldClassName}>
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                name={id}
                type={type}
                required={required}
                placeholder={placeholder}
                defaultValue={defaultValue ?? ''}
            />
            <InputError message={error} />
        </div>
    );
}

function TextareaField({
    id,
    label,
    error,
    defaultValue,
    placeholder,
}: {
    id: string;
    label: string;
    error?: string;
    defaultValue?: string | null;
    placeholder?: string;
}) {
    return (
        <div className={fieldClassName}>
            <Label htmlFor={id}>{label}</Label>
            <textarea
                id={id}
                name={id}
                rows={4}
                className={textareaClassName}
                placeholder={placeholder}
                defaultValue={defaultValue ?? ''}
            />
            <InputError message={error} />
        </div>
    );
}
