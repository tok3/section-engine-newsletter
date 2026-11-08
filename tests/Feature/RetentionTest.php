<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tok3\SectionEngineNewsletter\Events\SubscriberDeleted;
use Tok3\SectionEngineNewsletter\Events\SubscriptionConfirmed;
use Tok3\SectionEngineNewsletter\Events\SubscriptionRequested;
use Tok3\SectionEngineNewsletter\Events\SubscriptionUnsubscribed;
use Tok3\SectionEngineNewsletter\Mail\ConfirmSubscriptionMail;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Models\SubscriptionEvent;

class RetentionTest extends SubscribeTest
{
    public function test_domain_events_are_dispatched_once_per_transition(): void
    {
        Event::fake([SubscriptionRequested::class, SubscriptionConfirmed::class, SubscriptionUnsubscribed::class, SubscriberDeleted::class]);
        Mail::fake();
        $payload = $this->formPayload();
        $this->postJson('/newsletter/subscribe', $payload)->assertOk();
        $this->postJson('/newsletter/subscribe', $payload)->assertOk();
        Event::assertDispatchedTimes(SubscriptionRequested::class, 1);
        $mail = Mail::sent(ConfirmSubscriptionMail::class)->first();
        $path = '/newsletter/confirm/'.$mail->subscriptionId.'/'.$mail->token;
        $this->post($path)->assertOk();
        $this->post($path)->assertOk();
        Event::assertDispatchedTimes(SubscriptionConfirmed::class, 1);
        $subscription = Subscription::first();
        $url = URL::signedRoute('section-engine-newsletter.unsubscribe', ['subscription' => $subscription]);
        $this->post($url)->assertOk();
        $this->post($url)->assertOk();
        Event::assertDispatchedTimes(SubscriptionUnsubscribed::class, 1);
        $subscription->subscriber->delete();
        Event::assertDispatchedTimes(SubscriberDeleted::class, 1);
    }

    public function test_prune_deletes_only_old_unconfirmed_pending_rows_with_events(): void
    {
        $old = SubscriptionEvent::factory()->create();
        $old->subscription->update(['requested_at' => now()->subDays(31)]);
        $fresh = SubscriptionEvent::factory()->create();
        $confirmed = SubscriptionEvent::factory()->create();
        $confirmed->subscription->update(['status' => 'confirmed', 'requested_at' => now()->subDays(90), 'confirmed_at' => now()->subDays(89)]);
        $this->artisan('section-engine-newsletter:prune')->assertSuccessful();
        $this->assertDatabaseMissing('section_engine_newsletter_subscriptions', ['id' => $old->subscription_id]);
        $this->assertDatabaseMissing('section_engine_newsletter_events', ['id' => $old->id]);
        $this->assertNotNull($fresh->fresh());
        $this->assertNotNull($confirmed->fresh());
    }

    public function test_prune_anonymizes_all_old_unsubscribed_ip_and_user_agent_proof(): void
    {
        $event = SubscriptionEvent::factory()->create(['type' => 'unsubscribed']);
        $subscription = $event->subscription;
        $subscription->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()->subYears(4), 'requested_ip' => '192.0.2.1', 'requested_user_agent' => 'request', 'confirmed_ip' => '192.0.2.2', 'confirmed_user_agent' => 'confirm', 'unsubscribed_ip' => '192.0.2.3']);
        $recent = SubscriptionEvent::factory()->create();
        $recent->subscription->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        $snapshot = $subscription->consent_text_snapshot;
        $this->artisan('section-engine-newsletter:prune')->assertSuccessful();
        foreach (['requested_ip', 'requested_user_agent', 'confirmed_ip', 'confirmed_user_agent', 'unsubscribed_ip'] as $field) {
            $this->assertNull($subscription->fresh()->$field);
        }
        $this->assertNull($event->fresh()->ip);
        $this->assertNull($event->fresh()->user_agent);
        $this->assertSame($snapshot, $subscription->fresh()->consent_text_snapshot);
        $this->assertNotNull($recent->fresh()->ip);
    }

    public function test_prune_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command ?? '', 'section-engine-newsletter:prune'));
        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events->first()->expression);
    }
}
