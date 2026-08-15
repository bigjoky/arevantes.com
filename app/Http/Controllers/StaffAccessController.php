<?php

namespace App\Http\Controllers;

use App\Models\PermissionProfile;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffAccessController extends Controller
{
    public function store(Request $request, Person $staff): RedirectResponse
    {
        $this->ensureStaff($staff);
        abort_if($staff->user()->exists(), 422, __('This staff member already has CRM access.'));
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'commercial', 'technician'])],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $profile = $this->profileForRole($data['role']);
        User::create([
            'person_id' => $staff->id,
            'name' => trim($staff->first_name.' '.($staff->last_name ?? '')),
            'email' => $data['email'],
            'role' => $data['role'],
            'permission_profile_id' => $profile->id,
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('CRM access created.')]);
    }

    public function update(Request $request, Person $staff): RedirectResponse
    {
        $this->ensureStaff($staff);
        $user = $staff->user()->firstOrFail();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in(['admin', 'commercial', 'technician'])],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);
        $administrator = PermissionProfile::where('slug', 'administrator')->firstOrFail();
        if ($user->permission_profile_id === $administrator->id && $data['role'] !== 'admin' && User::where('permission_profile_id', $administrator->id)->count() === 1) {
            return back()->withErrors(['role' => __('The last administrator cannot be demoted.')]);
        }
        $profile = $this->profileForRole($data['role']);
        $user->update([
            'name' => trim($staff->first_name.' '.($staff->last_name ?? '')),
            'email' => $data['email'],
            'role' => $data['role'],
            'permission_profile_id' => $profile->id,
            'access_reviewed_at' => now(),
            'access_review_due_at' => now()->addMonths((int) config('security.access_review_months', 3)),
            ...(filled($data['password'] ?? null) ? ['password' => Hash::make($data['password'])] : []),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('CRM access updated.')]);
    }

    public function destroy(Request $request, Person $staff): RedirectResponse
    {
        $this->ensureStaff($staff);
        $user = $staff->user()->firstOrFail();
        abort_if($user->id === $request->user()->id, 422, __('You cannot remove your own access.'));
        $administrator = PermissionProfile::where('slug', 'administrator')->firstOrFail();
        abort_if($user->permission_profile_id === $administrator->id && User::where('permission_profile_id', $administrator->id)->count() === 1, 422, __('The last administrator cannot be removed.'));
        $user->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('CRM access removed. The staff profile was kept.')]);
    }

    private function ensureStaff(Person $staff): void
    {
        abort_unless($staff->category === 'staff', 404);
    }

    private function profileForRole(string $role): PermissionProfile
    {
        $slug = $role === 'admin' ? 'administrator' : $role;

        return PermissionProfile::where('slug', $slug)->firstOrFail();
    }
}
