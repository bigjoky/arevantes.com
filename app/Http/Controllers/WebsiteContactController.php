<?php

namespace App\Http\Controllers;

use App\Services\Crm\LeadAssignment;
use App\Services\Crm\LeadResponseSla;
use App\Services\Crm\OpportunitySalesCycle;
use App\Services\Website\WebsiteContactReceipts;
use App\Support\BusinessLineCatalog;
use App\Support\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class WebsiteContactController extends Controller
{
    public function __invoke(Request $request, LeadAssignment $assignment, OpportunitySalesCycle $salesCycle, LeadResponseSla $responseSla): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'service' => ['required', Rule::in(array_keys(BusinessLineCatalog::WEBSITE_SERVICES))],
            'message' => ['required', 'string', 'min:20', 'max:2000'],
            'privacy' => ['accepted'],
            'company_url' => ['nullable', 'string', 'max:0'],
        ]);

        $data += ['email' => null, 'phone' => null, 'postal_code' => null];
        if (filled($data['phone']) && (! PhoneNormalizer::isPlausibleMobile($data['phone']) || ! preg_match('/^[+0-9\s().-]+$/', $data['phone']))) {
            throw ValidationException::withMessages(['phone' => 'Introduce un teléfono válido para poder contactar contigo.']);
        }

        try {
            $receipt = app(WebsiteContactReceipts::class)->receive($data);
        } catch (Throwable $exception) {
            Log::error('No se pudo recibir una solicitud web.', ['exception_class' => $exception::class]);
            throw ValidationException::withMessages([
                'submission' => 'No hemos podido registrar tu consulta. Inténtalo de nuevo o escríbenos por correo electrónico.',
            ]);
        }

        app(WebsiteContactReceipts::class)->process($receipt);

        return back()->with('contact_submitted', true);
    }
}
