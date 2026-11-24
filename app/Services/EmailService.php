<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Render a configurable email template with variable interpolation and dispatch via SMTP.
     * Accurately records SMTP acceptance vs failure without claiming unverified delivery.
     */
    public function sendTemplatedEmail(
        string $templateSlug,
        string $recipientEmail,
        string $recipientName,
        array $variables = [],
        ?Model $relatedModel = null
    ): EmailLog {
        $template = EmailTemplate::where('slug', $templateSlug)->where('is_active', true)->first();

        $subject = $template?->subject ?? ucfirst(str_replace('_', ' ', $templateSlug));
        $body = $template?->body_html ?? '<p>Notification from OrbiTouch ATS.</p>';

        foreach ($variables as $key => $val) {
            $placeholder = '{{'.$key.'}}';
            $subject = str_replace($placeholder, (string) $val, $subject);
            $body = str_replace($placeholder, e((string) $val), $body);
        }

        $status = 'queued';
        $smtpResponse = null;

        try {
            Mail::html($body, function ($message) use ($recipientEmail, $recipientName, $subject) {
                $message->to($recipientEmail, $recipientName)->subject($subject);
            });

            $mailer = config('mail.default', 'log');
            $status = in_array($mailer, ['log', 'array'], true) ? 'simulated_local' : 'smtp_accepted';
            $smtpResponse = "Accepted by configured mailer [{$mailer}] at ".now()->toIso8601String();
        } catch (\Throwable $e) {
            $status = 'failed';
            $smtpResponse = 'SMTP dispatch failure: '.$e->getMessage();
        }

        return EmailLog::create([
            'template_slug' => $templateSlug,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'subject' => $subject,
            'body_preview' => substr(strip_tags($body), 0, 500),
            'delivery_status' => $status,
            'smtp_response' => $smtpResponse,
            'related_type' => $relatedModel ? get_class($relatedModel) : null,
            'related_id' => $relatedModel?->getKey(),
            'triggered_by' => Auth::id(),
            'sent_at' => $status !== 'failed' ? now() : null,
        ]);
    }
}
