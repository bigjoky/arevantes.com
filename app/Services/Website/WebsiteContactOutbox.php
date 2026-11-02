<?php

namespace App\Services\Website;

use App\Mail\WebsiteContactMailMessage;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\WebsiteContactMail;
use App\Models\WebsiteContactReceipt;
use App\Services\Website\Graph\GraphException;
use App\Services\Website\Graph\GraphMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class WebsiteContactOutbox
{
    public function prepare(WebsiteContactReceipt $receipt, Opportunity $opportunity, ?string $visitorEmail): void
    {
        $staff = User::query()->where(fn ($query) => $query->where('role', 'admin')->orWhere('id', $opportunity->owner_user_id))->get()
            ->filter(fn (User $user) => $user->can('manage-customers') && filter_var($user->email, FILTER_VALIDATE_EMAIL))
            ->pluck('email')->map(fn ($email) => Str::lower(trim($email)))->unique();
        foreach ($staff as $email) {
            $this->add($receipt, $opportunity, 'staff', $email);
        }
        if ($visitorEmail && filter_var($visitorEmail, FILTER_VALIDATE_EMAIL)) {
            $this->add($receipt, $opportunity, 'visitor', Str::lower(trim($visitorEmail)));
        }
    }

    private function add(WebsiteContactReceipt $receipt, Opportunity $opportunity, string $kind, string $email): void
    {
        WebsiteContactMail::query()->firstOrCreate([
            'receipt_id' => $receipt->id, 'kind' => $kind,
            'recipient_hash' => hash_hmac('sha256', $email, config('app.key')),
        ], ['opportunity_id' => $opportunity->id, 'recipient' => $email]);
    }

    public function send(int $id): string
    {
        $transport = config('contact-mail.transport');
        if (! config('contact-mail.enabled') || ! in_array($transport, ['smtp', 'graph'], true) || ($transport === 'smtp' && config('mail.default') !== 'smtp')) {
            return 'disabled';
        }
        $item = DB::transaction(function () use ($id): ?WebsiteContactMail {
            $item = WebsiteContactMail::query()->lockForUpdate()->findOrFail($id);
            if ($item->status !== 'pending') {
                return null;
            }
            $item->update(['status' => 'sending', 'attempted_at' => now(), 'attempts' => $item->attempts + 1]);

            return $item;
        });
        if (! $item) {
            return 'not_pending';
        }
        try {
            if ($transport === 'graph') {
                app(GraphMailer::class)->send($item);
            } else {
                $sent = Mail::to($item->recipient)->send(new WebsiteContactMailMessage(
                    $item->kind === 'visitor',
                    $item->kind === 'staff' ? route('opportunities.show', $item->opportunity_id) : null,
                ));
                if (! $sent) {
                    throw new \RuntimeException('El transporte no confirmó la aceptación.');
                }
            }
            $item->update(['status' => 'sent', 'sent_at' => now(), 'error_class' => null]);

            return 'sent';
        } catch (Throwable $exception) {
            $category = $exception instanceof GraphException ? $exception->category : $exception::class;
            $item->update(['status' => 'uncertain', 'error_class' => $category]);
            Log::error('No se pudo confirmar el envío de correo de contacto.', ['mail_id' => $item->id, 'error' => $category]);

            return 'uncertain';
        }
    }
}
