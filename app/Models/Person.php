<?php

namespace App\Models;

use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (self $person): void {
            $person->phone_normalized = PhoneNormalizer::normalize($person->phone);
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category',
        'customer_type',
        'lifecycle_status',
        'first_name',
        'last_name',
        'company_name',
        'tax_id',
        'receivable_account_code',
        'employee_advance_account_code',
        'employee_payable_account_code',
        'staff_role',
        'document_type',
        'document_number',
        'phone',
        'email',
        'address_line_1',
        'address_line_2',
        'city',
        'locality',
        'province_state',
        'postal_code',
        'country',
        'date_of_birth',
        'preferred_language',
        'preferred_contact_channel',
        'do_not_contact',
        'service_interests',
        'lead_source',
        'data_source',
        'billing_email',
        'billing_name',
        'billing_tax_id',
        'billing_address_line_1',
        'billing_address_line_2',
        'billing_city',
        'billing_locality',
        'billing_province_state',
        'billing_postal_code',
        'billing_country',
        'payment_terms_days',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
        'extra_details',
    ];

    /**
     * Get the installations linked to this customer.
     */
    public function installations(): HasMany
    {
        return $this->hasMany(Installation::class);
    }

    public function sharedInstallations(): BelongsToMany
    {
        return $this->belongsToMany(Installation::class, 'installation_person')
            ->withPivot(['role', 'ownership_percentage', 'is_primary'])
            ->withTimestamps();
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function employmentProfile(): HasOne
    {
        return $this->hasOne(HrEmploymentProfile::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(HrLeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(HrLeaveBalance::class);
    }

    public function hrTasks(): HasMany
    {
        return $this->hasMany(HrEmployeeTask::class);
    }

    public function contractChanges(): HasMany
    {
        return $this->hasMany(HrContractChange::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(HrEmergencyContact::class);
    }

    public function employmentDocuments(): HasMany
    {
        return $this->hasMany(HrEmploymentDocument::class);
    }

    public function portalAccesses(): HasMany
    {
        return $this->hasMany(CustomerPortalAccess::class);
    }

    public function portalEvents(): HasMany
    {
        return $this->hasMany(CustomerPortalEvent::class);
    }

    public function portalPreference(): HasOne
    {
        return $this->hasOne(CustomerPortalPreference::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class)->orderByDesc('is_primary')->orderBy('first_name');
    }

    /**
     * Freeze the effective fiscal identity used by proposals and sales documents.
     *
     * @return array<string, mixed>
     */
    public function customerSnapshot(): array
    {
        $billingContact = $this->contacts()
            ->whereJsonContains('roles', 'billing')
            ->orderByDesc('is_primary')
            ->first();

        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'company_name' => $this->billing_name ?: $this->company_name,
            'tax_id' => $this->billing_tax_id ?: $this->tax_id,
            'document_number' => $this->document_number,
            'billing_email' => $this->billing_email ?: $billingContact?->email,
            'email' => $this->email,
            'address_line_1' => $this->billing_address_line_1 ?: $this->address_line_1,
            'address_line_2' => $this->billing_address_line_2 ?: $this->address_line_2,
            'postal_code' => $this->billing_postal_code ?: $this->postal_code,
            'city' => $this->billing_city ?: $this->city,
            'locality' => $this->billing_locality ?: $this->locality,
            'province_state' => $this->billing_province_state ?: $this->province_state,
            'country' => $this->billing_country ?: $this->country,
            'payment_terms_days' => $this->payment_terms_days,
            'billing_contact' => $billingContact ? [
                'name' => trim($billingContact->first_name.' '.($billingContact->last_name ?? '')),
                'email' => $billingContact->email,
                'phone' => $billingContact->phone,
            ] : null,
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CrmTag::class, 'crm_tag_person')->withTimestamps();
    }

    public function consents(): HasMany
    {
        return $this->hasMany(CustomerConsent::class)->latest('captured_at');
    }

    public function serviceCycleCommunications(): HasMany
    {
        return $this->hasMany(ServiceCycleCommunication::class)->orderByDesc('scheduled_for');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'service_interests' => 'array',
            'extra_details' => 'array',
            'do_not_contact' => 'boolean',
            'payment_terms_days' => 'integer',
        ];
    }
}
