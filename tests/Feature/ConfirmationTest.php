<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tok3\SectionEngineNewsletter\Enums\SubscriptionStatus;
use Tok3\SectionEngineNewsletter\Mail\ConfirmSubscriptionMail;
use Tok3\SectionEngineNewsletter\Models\Subscription;

class ConfirmationTest extends SubscribeTest
{
    public function test_signup_sends_one_localized_mail_and_respects_cooldown(): void
    {
        Mail::fake();
        $payload = $this->formPayload();
        $this->postJson('/newsletter/subscribe', $payload)->assertOk();
        Mail::assertSent(ConfirmSubscriptionMail::class, fn ($mail) => $mail->locale === 'en' && $mail->hasTo('new@example.org') && strlen($mail->token) === 64);
        $this->postJson('/newsletter/subscribe', $payload)->assertOk();
        Mail::assertSentCount(1);
        $this->travel(11)->minutes();
        $this->postJson('/newsletter/subscribe', $payload)->assertOk();
        Mail::assertSentCount(2);
        Subscription::first()->update(['status' => 'confirmed']);
        $this->postJson('/newsletter/subscribe', $payload)->assertOk();
        Mail::assertSentCount(2);
    }

    public function test_mail_is_queued_on_the_configured_connection_and_mailer(): void
    {
        Mail::fake();
        config(['section-engine-newsletter.queue' => 'redis', 'section-engine-newsletter.mailer' => 'log']);
        $this->postJson('/newsletter/subscribe', $this->formPayload())->assertOk();
        Mail::assertQueued(ConfirmSubscriptionMail::class, fn ($mail) => $mail->connection === 'redis' && $mail->mailer === 'log');
        Mail::assertNothingSent();
    }

    public function test_get_does_not_confirm_post_confirms_once_with_full_proof(): void
    {
        Mail::fake();
        $this->postJson('/newsletter/subscribe', $this->formPayload())->assertOk();
        $mail = Mail::sent(ConfirmSubscriptionMail::class)->first();
        $url = '/newsletter/confirm/'.$mail->subscriptionId.'/'.$mail->token;
        $this->get($url)->assertOk()->assertSee('method="post"', false);
        $this->assertSame(SubscriptionStatus::Pending, Subscription::first()->status);
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::9'])->withHeader('User-Agent', 'Confirm browser')->post($url)->assertOk();
        $subscription = Subscription::first();
        $this->assertSame(SubscriptionStatus::Confirmed, $subscription->status);
        $this->assertSame('2001:db8::9', $subscription->confirmed_ip);
        $this->assertDatabaseHas('section_engine_newsletter_events', ['type' => 'confirmed', 'ip' => '2001:db8::9', 'user_agent' => 'Confirm browser']);
        $this->post($url)->assertOk();
        $this->assertSame(1, $subscription->events()->where('type', 'confirmed')->count());
    }

    public function test_wrong_and_expired_tokens_cannot_confirm_and_expiry_offers_resend(): void
    {
        Mail::fake();
        $this->postJson('/newsletter/subscribe', $this->formPayload())->assertOk();
        $mail = Mail::sent(ConfirmSubscriptionMail::class)->first();
        $this->post('/newsletter/confirm/'.$mail->subscriptionId.'/wrong')->assertNotFound();
        $this->travel(49)->hours();
        $url = '/newsletter/confirm/'.$mail->subscriptionId.'/'.$mail->token;
        $this->get($url)->assertOk()->assertSee('name="context"', false)->assertSee('expired');
        $this->post($url)->assertStatus(410);
        $this->assertSame(SubscriptionStatus::Pending, Subscription::first()->status);
    }

    public function test_transport_delivery_writes_confirmation_sent_proof(): void
    {
        config(['mail.default' => 'array', 'mail.mailers.array' => ['transport' => 'array']]);
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::7'])->withHeader('User-Agent', 'Mail browser')->postJson('/newsletter/subscribe', $this->formPayload())->assertOk();
        $this->assertDatabaseHas('section_engine_newsletter_events', ['type' => 'confirmation_sent', 'ip' => '2001:db8::7', 'user_agent' => 'Mail browser']);
    }
}
