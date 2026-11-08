<?php

namespace Tok3\SectionEngineNewsletter\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tok3\SectionEngineNewsletter\Enums\SubscriptionEventType;
use Tok3\SectionEngineNewsletter\Enums\SubscriptionStatus;
use Tok3\SectionEngineNewsletter\Events\SubscriptionRequested;
use Tok3\SectionEngineNewsletter\Mail\ConfirmSubscriptionMail;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Models\Subscriber;
use Tok3\SectionEngineNewsletter\Models\Subscription;

class RequestSubscription
{
    /** @param array<string, mixed> $context */
    public function handle(string $email, array $context, Request $request): Subscription
    {
        return DB::transaction(function () use ($email, $context, $request): Subscription {
            $list = NewsletterList::query()->where('key', $context['list'])->where('active', true)->firstOrFail();
            $subscriber = Subscriber::firstOrCreate(['email' => mb_strtolower(trim($email))], ['locale' => $context['locale']]);
            $subscription = Subscription::query()->where('subscriber_id', $subscriber->id)->where('list_id', $list->id)->lockForUpdate()->first();
            $isNew = $subscription === null;
            $resubscribe = $subscription?->status === SubscriptionStatus::Unsubscribed;
            if ($subscription?->status === SubscriptionStatus::Confirmed || ($subscription?->status === SubscriptionStatus::Pending && $subscription->requested_at->gt(now()->subMinutes(config('section-engine-newsletter.resend_cooldown_minutes'))))) {
                $this->record($subscription, SubscriptionEventType::Requested, $context, $request, ['mail_requested' => false]);

                return $subscription;
            }
            $token = Str::random(64);
            $snapshot = $context['consent_snapshot'];
            $subscription ??= new Subscription(['subscriber_id' => $subscriber->id, 'list_id' => $list->id]);
            $subscription->fill([
                'status' => SubscriptionStatus::Pending, 'tag' => $context['tag'], 'source_page_id' => $context['page_id'],
                'source_url' => $context['url'], 'source_locale' => $context['locale'], 'consent_text_snapshot' => $snapshot,
                'consent_hash' => hash('sha256', $snapshot), 'requested_at' => now(), 'requested_ip' => $request->ip(), 'requested_user_agent' => $request->userAgent(),
                'confirmed_at' => null, 'confirmed_ip' => null, 'confirmed_user_agent' => null, 'unsubscribed_at' => null, 'unsubscribed_ip' => null,
                'confirmation_token_hash' => hash('sha256', $token), 'confirmation_expires_at' => now()->addHours(config('section-engine-newsletter.confirmation_ttl_hours')),
            ])->save();
            $this->record($subscription, $resubscribe ? SubscriptionEventType::ResubscribeRequested : SubscriptionEventType::Requested, $context, $request, ['mail_requested' => true]);

            if ($isNew || $resubscribe) {
                DB::afterCommit(fn () => SubscriptionRequested::dispatch($subscription));
            }
            DB::afterCommit(function () use ($subscription, $token, $request): void {
                $mail = (new ConfirmSubscriptionMail($subscription->id, $token, $request->ip(), $request->userAgent()))->locale($subscription->source_locale);
                $mailer = Mail::mailer(config('section-engine-newsletter.mailer'))->to($subscription->subscriber->email);
                $connection = config('section-engine-newsletter.queue');
                if (is_string($connection) && $connection !== '') {
                    $mailer->queue($mail->onConnection($connection));
                } else {
                    $mailer->send($mail);
                }
            });

            return $subscription;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $meta
     */
    public function record(Subscription $subscription, SubscriptionEventType $type, array $context, Request $request, array $meta = []): void
    {
        $subscription->events()->create([
            'type' => $type, 'tag' => $context['tag'], 'source_page_id' => $context['page_id'], 'source_url' => $context['url'],
            'ip' => $request->ip(), 'user_agent' => $request->userAgent(),
            'meta' => array_merge($meta, ['consent_text_snapshot' => $context['consent_snapshot'], 'consent_hash' => hash('sha256', $context['consent_snapshot']), 'locale' => $context['locale']]),
        ]);
    }
}
