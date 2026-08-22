<?php

namespace App\Http\Controllers;

use App\Services\Customers\OpportunityCustomerResolver;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerPhoneLookupController extends Controller
{
    public function __invoke(Request $request, OpportunityCustomerResolver $resolver): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:40']]);

        if (! PhoneNormalizer::isPlausibleMobile($data['phone'])) {
            throw ValidationException::withMessages([
                'phone' => __('Enter a valid mobile number.'),
            ]);
        }

        $customer = $resolver->findByPhone($data['phone']);

        return response()->json([
            'normalized_phone' => PhoneNormalizer::normalize($data['phone']),
            'customer' => $customer ? [
                'id' => $customer->id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'company_name' => $customer->company_name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'preferred_contact_channel' => $customer->preferred_contact_channel,
                'do_not_contact' => $customer->do_not_contact,
            ] : null,
        ]);
    }
}
