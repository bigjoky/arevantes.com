import type { FormComponentRef } from '@inertiajs/core';
import { Form } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useRef, useState } from 'react';
import {
    FormActions,
    FormErrorSummary,
    FormSection,
} from '@/components/forms/form-layout';
import InputError from '@/components/input-error';
import LocationFields from '@/components/locations/location-fields';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCommercialFormProtection } from '@/hooks/use-commercial-form-protection';
import { useLocale } from '@/hooks/use-locale';
import { translate } from '@/i18n';

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
    billing_name?: string | null;
    billing_tax_id?: string | null;
    billing_address_line_1?: string | null;
    billing_address_line_2?: string | null;
    billing_city?: string | null;
    billing_locality?: string | null;
    billing_province_state?: string | null;
    billing_postal_code?: string | null;
    billing_country?: string | null;
    payment_terms_days?: number | null;
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
    staffRoles?: string[];
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
    staffRoles = [],
}: PersonFormProps) {
    const { locale, t } = useLocale();
    const category = forcedCategory ?? person?.category ?? 'customer';
    const isCustomer = category === 'customer';
    const isStaff = category === 'staff';
    const draftForm = useRef<FormComponentRef | null>(null);
    const protection = useCommercialFormProtection(
        () => draftForm.current,
        isCustomer,
    );
    const [customerType, setCustomerType] = useState(
        person?.customer_type ?? 'residential',
    );
    const isResidential = customerType === 'residential';
    const finalSubmitLabel =
        submitLabel === 'Add person'
            ? t.addPerson
            : submitLabel === 'Save person'
              ? t.savePerson
              : submitLabel;

    return (
        <>
            {protection.confirmation}
            <Form
                ref={draftForm}
                onBefore={protection.beginSubmit}
                onFinish={protection.finishSubmit}
                onSuccess={() => {
                    if (isCustomer) {
                        protection.saved();
                    }
                }}
                disableWhileProcessing={isCustomer && protection.mobile}
                action={action}
                method={method}
                options={{ preserveScroll: true }}
                resetOnSuccess={method === 'post'}
                className={
                    isCustomer
                        ? 'commercial-person-form space-y-6'
                        : 'space-y-6'
                }
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

                        <FormErrorSummary errors={errors} />

                        <FormSection
                            title={translate('Profile classification', locale)}
                            description={translate(
                                'Choose the profile nature so the form only requests relevant identity and billing information.',
                                locale,
                            )}
                        >
                            <div className="grid gap-4 md:grid-cols-3">
                                {!forcedCategory && (
                                    <div className={fieldClassName}>
                                        <Label htmlFor="category">
                                            {t.personType}
                                        </Label>
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
                                            value={customerType}
                                            onValueChange={setCustomerType}
                                        >
                                            <SelectTrigger
                                                id="customer_type"
                                                className="w-full"
                                            >
                                                <SelectValue
                                                    placeholder={
                                                        t.selectCustomerType
                                                    }
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
                                        <InputError
                                            message={errors.customer_type}
                                        />
                                    </div>
                                )}

                                {isCustomer && (
                                    <div className={fieldClassName}>
                                        <Label htmlFor="lifecycle_status">
                                            {translate(
                                                'Customer status',
                                                locale,
                                            )}
                                        </Label>
                                        <Select
                                            name="lifecycle_status"
                                            defaultValue={
                                                person?.lifecycle_status ??
                                                'active'
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
                                                    {t.lead}
                                                </SelectItem>
                                                <SelectItem value="active">
                                                    {translate(
                                                        'Active',
                                                        locale,
                                                    )}
                                                </SelectItem>
                                                <SelectItem value="inactive">
                                                    {translate(
                                                        'Inactive',
                                                        locale,
                                                    )}
                                                </SelectItem>
                                                <SelectItem value="former">
                                                    {translate(
                                                        'Former customer',
                                                        locale,
                                                    )}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.lifecycle_status}
                                        />
                                    </div>
                                )}

                                {isStaff && (
                                    <div className={fieldClassName}>
                                        <Label htmlFor="staff_role">
                                            {t.staffRole}
                                        </Label>
                                        <select
                                            id="staff_role"
                                            name="staff_role"
                                            defaultValue={
                                                person?.staff_role ?? ''
                                            }
                                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                        >
                                            <option value="">
                                                {translate(
                                                    'Unassigned',
                                                    locale,
                                                )}
                                            </option>
                                            {person?.staff_role &&
                                                !staffRoles.includes(
                                                    person.staff_role,
                                                ) && (
                                                    <option
                                                        value={
                                                            person.staff_role
                                                        }
                                                    >
                                                        {person.staff_role}
                                                    </option>
                                                )}
                                            {staffRoles.map((role) => (
                                                <option key={role} value={role}>
                                                    {translate(role, locale)}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.staff_role}
                                        />
                                    </div>
                                )}
                            </div>
                        </FormSection>

                        <FormSection
                            title={translate(
                                'Identity and tax details',
                                locale,
                            )}
                            description={
                                isCustomer && !isResidential
                                    ? translate(
                                          'Record the legal entity and its primary contact person.',
                                          locale,
                                      )
                                    : translate(
                                          'Record the person exactly as shown on their identification.',
                                          locale,
                                      )
                            }
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field
                                    id="first_name"
                                    label={
                                        isCustomer && !isResidential
                                            ? translate(
                                                  'Primary contact first name',
                                                  locale,
                                              )
                                            : t.firstName
                                    }
                                    placeholder={t.firstName}
                                    defaultValue={person?.first_name}
                                    error={errors.first_name}
                                    required
                                />
                                <Field
                                    id="last_name"
                                    label={
                                        isCustomer && !isResidential
                                            ? translate(
                                                  'Primary contact last name',
                                                  locale,
                                              )
                                            : t.lastName
                                    }
                                    placeholder={t.lastName}
                                    defaultValue={person?.last_name}
                                    error={errors.last_name}
                                />
                                {(!isCustomer || !isResidential) && (
                                    <Field
                                        id="company_name"
                                        label={translate(
                                            customerType === 'community'
                                                ? 'Community name'
                                                : customerType ===
                                                    'public_sector'
                                                  ? 'Public body name'
                                                  : 'Legal name',
                                            locale,
                                        )}
                                        placeholder={translate(
                                            'Registered legal name',
                                            locale,
                                        )}
                                        defaultValue={person?.company_name}
                                        error={errors.company_name}
                                        required={isCustomer && !isResidential}
                                    />
                                )}
                                <Field
                                    id="tax_id"
                                    label={t.taxId}
                                    placeholder={translate(
                                        'CIF, VAT or tax reference',
                                        locale,
                                    )}
                                    defaultValue={person?.tax_id}
                                    error={errors.tax_id}
                                    required={isCustomer && !isResidential}
                                />
                            </div>

                            <div className="grid gap-4 md:grid-cols-3">
                                <div className={fieldClassName}>
                                    <Label htmlFor="document_type">
                                        {t.idType}
                                    </Label>
                                    <Select
                                        name="document_type"
                                        defaultValue={
                                            person?.document_type ?? ''
                                        }
                                    >
                                        <SelectTrigger
                                            id="document_type"
                                            className="w-full"
                                        >
                                            <SelectValue
                                                placeholder={t.chooseIdType}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="passport">
                                                {translate('Passport', locale)}
                                            </SelectItem>
                                            <SelectItem value="nif">
                                                NIF
                                            </SelectItem>
                                            <SelectItem value="nie">
                                                NIE
                                            </SelectItem>
                                            <SelectItem value="other">
                                                {translate('Other', locale)}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.document_type}
                                    />
                                </div>
                                <Field
                                    id="document_number"
                                    label={t.idNumber}
                                    placeholder={translate(
                                        'Passport, NIF or NIE',
                                        locale,
                                    )}
                                    defaultValue={person?.document_number}
                                    error={errors.document_number}
                                />
                                {isCustomer && (
                                    <Field
                                        id="lead_source"
                                        label={t.leadSource}
                                        placeholder={translate(
                                            'Referral, website or Google',
                                            locale,
                                        )}
                                        defaultValue={person?.lead_source}
                                        error={errors.lead_source}
                                    />
                                )}
                                {isCustomer && (
                                    <Field
                                        id="data_source"
                                        label={translate('Data source', locale)}
                                        placeholder={translate(
                                            'Web form, event or import',
                                            locale,
                                        )}
                                        defaultValue={person?.data_source}
                                        error={errors.data_source}
                                    />
                                )}
                            </div>
                        </FormSection>

                        <FormSection
                            title={translate('Contact preferences', locale)}
                            description={translate(
                                'Keep operational, billing and commercial contact choices together.',
                                locale,
                            )}
                        >
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
                            </div>

                            {isCustomer && (
                                <div className="grid gap-4 rounded-md border p-4 md:grid-cols-[1fr_auto]">
                                    <div className={fieldClassName}>
                                        <Label htmlFor="preferred_contact_channel">
                                            {translate(
                                                'Preferred contact channel',
                                                locale,
                                            )}
                                        </Label>
                                        <Select
                                            name="preferred_contact_channel"
                                            defaultValue={
                                                person?.preferred_contact_channel ??
                                                ''
                                            }
                                        >
                                            <SelectTrigger
                                                id="preferred_contact_channel"
                                                className="w-full"
                                            >
                                                <SelectValue
                                                    placeholder={translate(
                                                        'No preference',
                                                        locale,
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="email">
                                                    Email
                                                </SelectItem>
                                                <SelectItem value="phone">
                                                    {t.phone}
                                                </SelectItem>
                                                <SelectItem value="whatsapp">
                                                    WhatsApp
                                                </SelectItem>
                                                <SelectItem value="sms">
                                                    SMS
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={
                                                errors.preferred_contact_channel
                                            }
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
                                        {translate(
                                            'Do not contact commercially',
                                            locale,
                                        )}
                                    </label>
                                </div>
                            )}
                        </FormSection>

                        <FormSection
                            title={translate('Postal address', locale)}
                            description={translate(
                                'Select country, province, municipality and locality in order.',
                                locale,
                            )}
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field
                                    id="address_line_1"
                                    label={t.address}
                                    placeholder={translate(
                                        'Street and number',
                                        locale,
                                    )}
                                    defaultValue={person?.address_line_1}
                                    error={errors.address_line_1}
                                />
                                <Field
                                    id="address_line_2"
                                    label={t.address2}
                                    placeholder={translate(
                                        'Apartment or floor',
                                        locale,
                                    )}
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
                        </FormSection>

                        {isCustomer && (
                            <FormSection
                                title={translate('Billing details', locale)}
                                description={translate(
                                    'Only complete values that differ from the legal identity or postal address. Empty fields inherit the customer details.',
                                    locale,
                                )}
                            >
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    <Field
                                        id="billing_name"
                                        label={translate(
                                            'Billing name',
                                            locale,
                                        )}
                                        placeholder={
                                            person?.company_name ||
                                            translate(
                                                'Legal or personal name',
                                                locale,
                                            )
                                        }
                                        defaultValue={person?.billing_name}
                                        error={errors.billing_name}
                                    />
                                    <Field
                                        id="billing_tax_id"
                                        label={translate(
                                            'Billing tax ID',
                                            locale,
                                        )}
                                        placeholder={
                                            person?.tax_id || 'NIF / CIF'
                                        }
                                        defaultValue={person?.billing_tax_id}
                                        error={errors.billing_tax_id}
                                    />
                                    <Field
                                        id="billing_email"
                                        label={t.billingEmail}
                                        type="email"
                                        placeholder="billing@example.com"
                                        defaultValue={person?.billing_email}
                                        error={errors.billing_email}
                                    />
                                    <Field
                                        id="payment_terms_days"
                                        label={translate(
                                            'Payment terms (days)',
                                            locale,
                                        )}
                                        type="number"
                                        placeholder="30"
                                        defaultValue={
                                            person?.payment_terms_days == null
                                                ? undefined
                                                : String(
                                                      person.payment_terms_days,
                                                  )
                                        }
                                        error={errors.payment_terms_days}
                                    />
                                </div>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field
                                        id="billing_address_line_1"
                                        label={translate(
                                            'Billing address',
                                            locale,
                                        )}
                                        placeholder={translate(
                                            'Street and number',
                                            locale,
                                        )}
                                        defaultValue={
                                            person?.billing_address_line_1
                                        }
                                        error={errors.billing_address_line_1}
                                    />
                                    <Field
                                        id="billing_address_line_2"
                                        label={t.address2}
                                        placeholder={translate(
                                            'Apartment or floor',
                                            locale,
                                        )}
                                        defaultValue={
                                            person?.billing_address_line_2
                                        }
                                        error={errors.billing_address_line_2}
                                    />
                                </div>
                                <LocationFields
                                    prefix="billing_"
                                    errors={errors}
                                    defaults={{
                                        country: person?.billing_country,
                                        province:
                                            person?.billing_province_state,
                                        municipality: person?.billing_city,
                                        locality: person?.billing_locality,
                                        postalCode: person?.billing_postal_code,
                                    }}
                                />
                            </FormSection>
                        )}

                        <FormSection
                            title={translate('Additional information', locale)}
                            description={translate(
                                'Add service context, internal notes and an emergency contact when applicable.',
                                locale,
                            )}
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                {isCustomer && (
                                    <TextareaField
                                        id="service_interests"
                                        label={t.serviceInterests}
                                        placeholder={translate(
                                            'Solar panels\nElectrical installation\nAlarm and security system',
                                            locale,
                                        )}
                                        defaultValue={person?.service_interests?.join(
                                            '\n',
                                        )}
                                        error={errors.service_interests}
                                    />
                                )}
                                <TextareaField
                                    id="notes"
                                    label={t.notes}
                                    placeholder={translate(
                                        'Preferences, access needs or invoice notes',
                                        locale,
                                    )}
                                    defaultValue={person?.notes}
                                    error={errors.notes}
                                />
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field
                                    id="emergency_contact_name"
                                    label={t.emergencyContact}
                                    placeholder={translate(
                                        'Contact name',
                                        locale,
                                    )}
                                    defaultValue={
                                        person?.emergency_contact_name
                                    }
                                    error={errors.emergency_contact_name}
                                />
                                <Field
                                    id="emergency_contact_phone"
                                    label={t.emergencyPhone}
                                    type="tel"
                                    placeholder="+34 600 000 000"
                                    defaultValue={
                                        person?.emergency_contact_phone
                                    }
                                    error={errors.emergency_contact_phone}
                                />
                            </div>
                        </FormSection>

                        <FormActions
                            processing={processing}
                            submitLabel={finalSubmitLabel}
                            icon={<UserPlus />}
                        />
                    </>
                )}
            </Form>
        </>
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
