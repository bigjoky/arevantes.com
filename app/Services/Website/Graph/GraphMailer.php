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
        $auth = app(GraphAuthentication::class);
        $token = $auth->token();
        $visitor = $item->kind === 'visitor';
        $message = new WebsiteContactMailMessage($visitor, $visitor ? null : route('opportunities.show', $item->opportunity_id));
        try {
            $response = Http::withToken($token)->timeout(15)->connectTimeout(5)->acceptJson()->post(
                'https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/sendMail',
                ['message' => [
                    'subject' => $message->envelope()->subject,
                    'body' => ['contentType' => 'HTML', 'content' => $message->render()],
                    'toRecipients' => [['emailAddress' => ['address' => $item->recipient]]],
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
