<?php

namespace Tok3\SectionEngineNewsletter\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tok3\SectionEngineNewsletter\Enums\SubscriptionStatus;
use Tok3\SectionEngineNewsletter\Events\SubscriptionUnsubscribed;
use Tok3\SectionEngineNewsletter\Models\Subscription;

class UnsubscribeSubscription
{
    public function handle(Subscription $subscription, Request $request): Subscription
    {
        return DB::transaction(function () use ($subscription, $request): Subscription {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if ($subscription->status !== SubscriptionStatus::Unsubscribed) {
                $subscription->update(['status' => SubscriptionStatus::Unsubscribed, 'unsubscribed_at' => now(), 'unsubscribed_ip' => $request->ip()]);
                $subscription->events()->create([
                    'type' => 'unsubscribed', 'tag' => $subscription->tag, 'source_page_id' => $subscription->source_page_id,
                    'source_url' => $subscription->source_url, 'ip' => $request->ip(), 'user_agent' => $request->userAgent(),
                ]);
                DB::afterCommit(fn () => SubscriptionUnsubscribed::dispatch($subscription));
            }

            return $subscription;
        });
    }
}
