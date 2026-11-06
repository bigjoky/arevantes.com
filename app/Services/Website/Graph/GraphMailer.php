<?php

namespace App\Services\Website\Graph;

use App\Mail\WebsiteContactMailMessage;
use App\Models\WebsiteContactMail;
use Illuminate\Support\Facades\Http;
use Throwable;

final class GraphMailer
{
    public function send(WebsiteContactMail $item): void
    {
        $from = strtolower((string) config('contact-mail.from'));
        $replyTo = strtolower((string) config('contact-mail.reply_to'));
        if ($from !== 'info@arevantes.com' || $replyTo !== $from) {
            throw new GraphException('sender_configuration');
        }
        $visitor = $item->kind === 'visitor';
        $message = new WebsiteContactMailMessage($visitor, $visitor ? null : route('opportunities.show', $item->opportunity_id));
        $this->sendMessage($from, $replyTo, $item->recipient, $message->envelope()->subject, $message->render());
    }

    public function sendTechnicalTest(string $id): void
    {
        $from = strtolower((string) config('contact-mail.from'));
        $replyTo = strtolower((string) config('contact-mail.reply_to'));
        if ($from !== 'info@arevantes.com' || $replyTo !== $from || ! \Illuminate\Support\Str::isUuid($id)) {
            throw new GraphException('sender_configuration');
        }
        $this->sendMessage($from, $replyTo, 'info@arevantes.com', 'Prueba técnica de correo · AREVANTES', '<p>Prueba técnica autorizada de Microsoft Graph. Identificador: '.e($id).'</p>');
    }

    private function sendMessage(string $from, string $replyTo, string $recipient, string $subject, string $html): void
    {
        $auth = app(GraphAuthentication::class);
        $token = $auth->token();
        try {
            $response = Http::withToken($token)->timeout(15)->connectTimeout(5)->acceptJson()->post(
                'https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/sendMail',
                ['message' => [
                    'subject' => $subject,
                    'body' => ['contentType' => 'HTML', 'content' => $html],
                    'toRecipients' => [['emailAddress' => ['address' => $recipient]]],
                    'replyTo' => [['emailAddress' => ['address' => $replyTo]]],
                ]],
            );
        } catch (Throwable) {
            throw new GraphException('send_uncertain');
        }
        if ($response->status() !== 202) {
            if ($response->status() === 401) {
                $auth->forgetToken();
            }
            throw new GraphException('send_http_'.$response->status());
        }
    }
}
