<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Person;
use App\Models\User;
use App\Notifications\WebsiteLeadNotification;
use App\Services\Crm\LeadAssignment;
use App\Services\Crm\LeadResponseSla;
use App\Services\Crm\OpportunitySalesCycle;
use App\Services\Customers\OpportunityCustomerResolver;
use App\Services\Documents\OpportunityNumber;
use App\Support\BusinessLineCatalog;
use App\Support\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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
        if (filled($data['phone']) && ! PhoneNormalizer::normalize($data['phone'])) {
            throw ValidationException::withMessages(['phone' => 'Introduce un teléfono válido para poder contactar contigo.']);
        }

        [$opportunity, $created] = DB::transaction(function () use ($data, $assignment, $salesCycle, $responseSla): array {
            $email = filled($data['email'] ?? null) ? Str::lower($data['email']) : null;
            $service = BusinessLineCatalog::WEBSITE_SERVICES[$data['service']];
            if (DB::getDriverName() === 'pgsql') {
                $identity = PhoneNormalizer::normalize($data['phone'] ?? '') ?: $email;
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['website-lead:'.$identity]);
            }
            $customer = $email ? Person::query()->where('category', 'customer')->whereRaw('lower(email) = ?', [$email])->first() : app(OpportunityCustomerResolver::class)->findByPhone($data['phone']);

            if (! $customer && filled($data['phone'])) {
                $customer = app(OpportunityCustomerResolver::class)->findByPhone($data['phone']);
            }

            if ($customer) {
                $customer->update([
                    'phone' => $customer->phone ?: ($data['phone'] ?: null),
                    'postal_code' => $customer->postal_code ?: ($data['postal_code'] ?: null),
                    'service_interests' => collect($customer->service_interests)
                        ->push($service)
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                ]);
            } else {
                [$firstName, $lastName] = $this->splitName($data['name']);
                $customer = Person::create([
                    'category' => 'customer',
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $data['phone'] ?: null,
                    'postal_code' => $data['postal_code'] ?: null,
                    'country' => 'Spain',
                    'preferred_language' => 'es',
                    'service_interests' => [$service],
                    'lead_source' => 'Website',
                ]);
            }

            $notes = collect([
                $data['message'],
                $email && $customer->email !== $email ? 'Email facilitado para revisión: '.$email : null,
                filled($data['phone']) ? 'Teléfono: '.$data['phone'] : null,
                filled($data['postal_code']) ? 'Código postal: '.$data['postal_code'] : null,
            ])->filter()->implode("\n\n");
            $title = 'Solicitud web · '.$service;

            $recentDuplicate = $customer->opportunities()
                ->where('source', 'website')
                ->where('title', $title)
                ->where('notes', $notes)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->latest('id')
                ->first();

            if ($recentDuplicate) {
                return [$recentDuplicate, false];
            }

            $opportunity = Opportunity::create([
                'person_id' => $customer->id,
                'owner_user_id' => $assignment->nextOwner()?->id,
                'title' => $title,
                'stage' => 'new',
                'probability' => 10,
                'estimated_value' => 0,
                'source' => 'website',
                'notes' => $notes,
            ]);
            $opportunity->update([
                'code' => app(OpportunityNumber::class)->next($opportunity->id),
            ]);
            $responseSla->start($opportunity);
            $salesCycle->ensureNextAction($opportunity, $opportunity->owner);
            $salesCycle->refreshScore($opportunity);

            return [$opportunity, true];
        });

        if ($created) {
            try {
                Notification::send(
                    User::query()->whereIn('role', ['admin', 'commercial'])->get(),
                    new WebsiteLeadNotification($opportunity),
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return back()->with('contact_submitted', true);
    }

    /** @return array{string, ?string} */
    private function splitName(string $name): array
    {
        $parts = explode(' ', Str::squish($name), 2);

        return [$parts[0], $parts[1] ?? null];
    }
}
