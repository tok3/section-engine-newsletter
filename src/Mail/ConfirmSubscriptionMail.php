<?php

namespace Tok3\SectionEngineNewsletter\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SentMessage;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Support\FormContext;

class ConfirmSubscriptionMail extends Mailable implements ShouldBeEncrypted
{
    use Queueable;

    public function __construct(public int $subscriptionId, public string $token, public ?string $proofIp, public ?string $proofUserAgent)
    {
        $this->afterCommit();
    }

    public function build(): static
    {
        $subscription = Subscription::with('list')->findOrFail($this->subscriptionId);
        $locale = $this->locale ?? $subscription->source_locale;
        $subject = FormContext::localized($subscription->list->confirmation_subject, $locale, __('section-engine-newsletter::newsletter.mail_subject', [], $locale));
        $intro = FormContext::localized($subscription->list->confirmation_intro, $locale, __('section-engine-newsletter::newsletter.mail_intro', [], $locale));
        $brandName = (string) (config('section-engine-newsletter.mail.brand_name') ?: config('app.name'));
        $logoUrl = config('section-engine-newsletter.mail.logo_url');
        $footerConfig = config('section-engine-newsletter.mail.footer');
        $footer = is_array($footerConfig)
            ? FormContext::localized($footerConfig, $locale)
            : (is_string($footerConfig) ? $footerConfig : null);
        $address = config('section-engine-newsletter.from.address') ?: config('mail.from.address');
        $name = config('section-engine-newsletter.from.name') ?: config('mail.from.name');

        return $this->from($address, $name)->subject($subject)->markdown('section-engine-newsletter::mail.confirm', [
            'brandName' => $brandName,
            'logoUrl' => is_string($logoUrl) && $logoUrl !== '' ? $logoUrl : null,
            'listName' => $subscription->list->name,
            'intro' => $intro,
            'confirmationUrl' => route('section-engine-newsletter.confirm.show', ['subscription' => $this->subscriptionId, 'token' => $this->token]),
            'greeting' => __('section-engine-newsletter::newsletter.mail_greeting', ['brand' => $brandName], $locale),
            'listText' => __('section-engine-newsletter::newsletter.mail_list', ['list' => $subscription->list->name], $locale),
            'confirmLabel' => __('section-engine-newsletter::newsletter.confirm_button', [], $locale),
            'fallbackLabel' => __('section-engine-newsletter::newsletter.mail_fallback', [], $locale),
            'ignoreNotice' => __('section-engine-newsletter::newsletter.mail_ignore', [], $locale),
            'footer' => $footer,
        ]);
    }

    /** @param Factory|Mailer|null $mailer */
    public function send($mailer): ?SentMessage
    {
        $subscription = Subscription::find($this->subscriptionId);
        if (! $subscription || ! hash_equals($subscription->confirmation_token_hash ?? '', hash('sha256', $this->token))) {
            return null;
        }
        $sent = parent::send($mailer);
        if ($sent) {
            $subscription->events()->create([
                'type' => 'confirmation_sent', 'tag' => $subscription->tag, 'source_page_id' => $subscription->source_page_id,
                'source_url' => $subscription->source_url, 'ip' => $this->proofIp, 'user_agent' => $this->proofUserAgent,
                'meta' => ['mailer' => $this->mailer, 'queued' => filled($this->connection)],
            ]);
        }

        return $sent;
    }
}
