<?php

namespace App\Services\Website;

use App\Models\Opportunity;
use App\Models\Person;
use App\Models\User;
use App\Models\WebsiteContactReceipt;
use App\Notifications\WebsiteLeadNotification;
use App\Services\Crm\LeadAssignment;
use App\Services\Crm\LeadResponseSla;
use App\Services\Crm\OpportunitySalesCycle;
use App\Services\Customers\OpportunityCustomerResolver;
use App\Services\Documents\OpportunityNumber;
use App\Support\BusinessLineCatalog;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

final class WebsiteContactReceipts
{
    public function receive(array $data): WebsiteContactReceipt
    {
        $payload = array_intersect_key($data, array_flip(['name', 'email', 'phone', 'postal_code', 'service', 'message']));
        $fingerprint = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), config('app.key'));

        return DB::transaction(function () use ($payload, $fingerprint): WebsiteContactReceipt {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['website-receipt:'.$fingerprint]);
            }

            return WebsiteContactReceipt::query()->where('fingerprint', $fingerprint)
                ->where(fn ($query) => $query->whereNull('processed_at')->orWhere('created_at', '>=', now()->subMinutes(10)))
                ->latest('id')->first()
                ?? WebsiteContactReceipt::create(['fingerprint' => $fingerprint, 'payload' => $payload, 'expires_at' => now()->addDays(30)]);
        });
    }

    public function process(WebsiteContactReceipt $receipt): bool
    {
        try {
            DB::transaction(function () use ($receipt): void {
                $receipt = WebsiteContactReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
                if ($receipt->processed_at) {
                    return;
                }
                if (! $receipt->payload) {
                    throw new \RuntimeException('Receipt payload unavailable');
                }
                $data = $receipt->payload;
                $assignment = app(LeadAssignment::class);
                $salesCycle = app(OpportunitySalesCycle::class);
                $responseSla = app(LeadResponseSla::class);
                $email = filled($data['email'] ?? null) ? Str::lower($data['email']) : null;
                $service = BusinessLineCatalog::WEBSITE_SERVICES[$data['service']];
                if (DB::getDriverName() === 'pgsql') {
                    $identities = array_filter([$email ? 'email:'.$email : null, ($phone = PhoneNormalizer::normalize($data['phone'] ?? '')) ? 'phone:'.$phone : null]);
                    sort($identities);
                    foreach ($identities as $identity) {
                        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['website-lead:'.$identity]);
                    }
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
                    $receipt->update(['processed_at' => now(), 'opportunity_id' => $recentDuplicate->id, 'payload' => null]);

                    return;
                }

                $owner = $assignment->nextOwner();
                if (! $owner) {
                    throw new \RuntimeException('No hay ningún responsable comercial disponible para solicitudes web.');
                }

                $opportunity = Opportunity::create([
                    'person_id' => $customer->id,
                    'owner_user_id' => $owner->id,
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

                Notification::send(
                    User::query()->whereIn('role', ['admin', 'commercial'])->get(),
                    new WebsiteLeadNotification($opportunity),
                );
                app(WebsiteContactOutbox::class)->prepare($receipt, $opportunity, $email);
                $receipt->update(['processed_at' => now(), 'opportunity_id' => $opportunity->id, 'payload' => null]);
            });

            return true;
        } catch (Throwable $exception) {
            Log::error('No se pudo procesar una solicitud web recibida.', ['receipt_id' => $receipt->id, 'exception_class' => $exception::class]);

            return false;
        }
    }

    private function splitName(string $name): array
    {
        $parts = explode(' ', Str::squish($name), 2);

        return [$parts[0], $parts[1] ?? null];
    }
}
