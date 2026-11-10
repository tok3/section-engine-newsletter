<?php

namespace Tok3\SectionEngineNewsletter\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;
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
            $alreadyConfirmed = $subscription?->status === SubscriptionStatus::Confirmed;
            $lastRequestedAt = $subscription
                ? ($subscription->last_requested_at ?? $subscription->requested_at)
                : null;
            $withinCooldown = $lastRequestedAt?->gt(now()->subMinutes(config('section-engine-newsletter.resend_cooldown_minutes'))) ?? false;
            if ($subscription && $withinCooldown && ($alreadyConfirmed || $subscription->status === SubscriptionStatus::Pending)) {
                $this->record($subscription, SubscriptionEventType::Requested, $context, $request, array_filter([
                    'mail_requested' => false,
                    'already_confirmed' => $alreadyConfirmed ?: null,
                ], fn (mixed $value): bool => $value !== null));

                return $subscription;
            }
            $token = Str::random(64);
            $snapshot = $context['consent_snapshot'];
            $requestedAt = now();
            $subscription ??= new Subscription(['subscriber_id' => $subscriber->id, 'list_id' => $list->id]);
            $confirmationAttributes = [
                'confirmation_token_hash' => hash('sha256', $token), 'confirmation_expires_at' => now()->addHours(config('section-engine-newsletter.confirmation_ttl_hours')),
            ];
            $attributes = $alreadyConfirmed
                ? $confirmationAttributes + ['last_requested_at' => $requestedAt]
                : $confirmationAttributes + [
                    'tag' => $context['tag'], 'source_page_id' => $context['page_id'],
                    'source_url' => $context['url'], 'source_locale' => $context['locale'], 'consent_text_snapshot' => $snapshot,
                    'consent_hash' => hash('sha256', $snapshot), 'requested_at' => $requestedAt, 'last_requested_at' => $requestedAt, 'requested_ip' => $request->ip(), 'requested_user_agent' => $request->userAgent(),
                    'status' => SubscriptionStatus::Pending,
                    'confirmed_at' => null, 'confirmed_ip' => null, 'confirmed_user_agent' => null, 'unsubscribed_at' => null, 'unsubscribed_ip' => null,
                ];
            if ($alreadyConfirmed) {
                $subscription->timestamps = false;
            }
            try {
                $subscription->fill($attributes)->save();
            } finally {
                $subscription->timestamps = true;
            }
            $this->record($subscription, $resubscribe ? SubscriptionEventType::ResubscribeRequested : SubscriptionEventType::Requested, $context, $request, array_filter([
                'mail_requested' => true,
                'already_confirmed' => $alreadyConfirmed ?: null,
            ], fn (mixed $value): bool => $value !== null));

            if ($isNew || $resubscribe) {
                DB::afterCommit(fn () => SubscriptionRequested::dispatch($subscription));
            }
            $mailLocale = $context['locale'];
            DB::afterCommit(function () use ($subscription, $token, $request, $mailLocale): void {
                $connection = config('section-engine-newsletter.queue');
                $queued = is_string($connection) && $connection !== '';
                $ip = $request->ip();
                $userAgent = $request->userAgent();
                $deliver = function () use ($subscription, $token, $connection, $queued, $ip, $userAgent, $mailLocale): void {
                    // Attempt each scheduled delivery only once, even if termination is invoked again.
                    static $attempted = false;
                    if ($attempted) {
                        return;
                    }
                    $attempted = true;

                    try {
                        $mail = (new ConfirmSubscriptionMail($subscription->id, $token, $ip, $userAgent))->locale($mailLocale);
                        $mailer = Mail::mailer(config('section-engine-newsletter.mailer'))->to($subscription->subscriber->email);
                        if ($queued) {
                            $mailer->queue($mail->onConnection($connection));
                        } else {
                            $mailer->send($mail);
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                };

                if ($queued) {
                    $deliver();
                } else {
                    app()->terminating($deliver);
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
