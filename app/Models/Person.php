<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category',
        'customer_type',
        'first_name',
        'last_name',
        'company_name',
        'tax_id',
        'receivable_account_code',
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
        'service_interests',
        'lead_source',
        'billing_email',
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

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
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
        ];
    }
}
