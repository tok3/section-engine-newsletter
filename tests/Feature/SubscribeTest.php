<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Tok3\SectionEngine\Support\LiveTag;
use Tok3\SectionEngine\Support\LiveTagRegistry;
use Tok3\SectionEngine\Support\LiveTokenRenderContext;
use Tok3\SectionEngineNewsletter\Enums\SubscriptionStatus;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Models\SubscriptionEvent;
use Tok3\SectionEngineNewsletter\Tests\TestCase;

class SubscribeTest extends TestCase
{
    protected function formPayload(string $email = 'new@example.org', string $tag = 'topic'): array
    {
        NewsletterList::firstOrCreate(['key' => 'news'], ['name' => 'News', 'consent_text' => ['en' => 'Original consent'], 'active' => true]);
        $html = app(LiveTagRegistry::class)->get('newsletter')->render(new LiveTag('newsletter', 'news', ['tag' => $tag], '{{ newsletter:news }}', true), LiveTokenRenderContext::frontend('en', url: 'http://localhost/example'));
        preg_match('/name="context" value="([^"]+)"/', $html, $match);
        $this->travel(4)->seconds();

        return ['email' => $email, 'context' => html_entity_decode($match[1]), 'website' => '', 'consent' => '1'];
    }

    public function test_new_signup_is_pending_with_hashed_token_and_full_proof(): void
    {
        $payload = $this->formPayload(' NEW@Example.org ');
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::1234'])->withHeader('User-Agent', 'Consent browser')->postJson('/newsletter/subscribe', $payload)->assertOk()->assertJsonStructure(['status', 'message']);
        $subscription = Subscription::with('subscriber', 'events')->firstOrFail();
        $this->assertSame('new@example.org', $subscription->subscriber->email);
        $this->assertSame(SubscriptionStatus::Pending, $subscription->status);
        $this->assertSame(64, strlen($subscription->confirmation_token_hash));
        $this->assertSame('topic', $subscription->tag);
        $this->assertSame('2001:db8::1234', $subscription->requested_ip);
        $this->assertSame('Consent browser', $subscription->requested_user_agent);
        $this->assertSame('requested', $subscription->events->first()->type->value);
        $this->assertSame('2001:db8::1234', $subscription->events->first()->ip);
        $this->assertSame(hash('sha256', $subscription->consent_text_snapshot), $subscription->consent_hash);
    }

    public function test_all_subscription_states_return_identical_json(): void
    {
        $payload = $this->formPayload();
        $response = $this->postJson('/newsletter/subscribe', $payload)->assertOk()->json();
        $subscription = Subscription::firstOrFail();
        $token = $subscription->confirmation_token_hash;
        $this->assertSame($response, $this->postJson('/newsletter/subscribe', $payload)->assertOk()->json());
        $this->assertSame($token, $subscription->fresh()->confirmation_token_hash);
        $this->travel(11)->minutes();
        $this->assertSame($response, $this->postJson('/newsletter/subscribe', $payload)->assertOk()->json());
        $this->assertNotSame($token, $subscription->fresh()->confirmation_token_hash);
        $subscription->update(['status' => 'confirmed']);
        $this->assertSame($response, $this->postJson('/newsletter/subscribe', $payload)->assertOk()->json());
        $this->assertSame(SubscriptionStatus::Confirmed, $subscription->fresh()->status);
        $subscription->update(['status' => 'unsubscribed']);
        $this->travel(61)->minutes();
        $this->assertSame($response, $this->postJson('/newsletter/subscribe', $payload)->assertOk()->json());
        $this->assertSame(SubscriptionStatus::Pending, $subscription->fresh()->status);
        $this->assertDatabaseHas('section_engine_newsletter_events', ['type' => 'resubscribe_requested']);
    }

    public function test_invalid_inputs_store_nothing(): void
    {
        $payload = $this->formPayload();
        foreach ([['website' => 'spam'], ['context' => 'tampered'], ['email' => 'invalid'], ['consent' => null]] as $change) {
            $this->postJson('/newsletter/subscribe', array_replace($payload, $change))->assertUnprocessable();
        }
        $this->assertDatabaseCount('section_engine_newsletter_subscribers', 0);
        $this->assertDatabaseCount('section_engine_newsletter_events', 0);
    }

    public function test_expired_and_too_fast_contexts_are_rejected(): void
    {
        $payload = $this->formPayload();
        $this->travelBack();
        $this->postJson('/newsletter/subscribe', $payload)->assertUnprocessable();
        $this->travel(169)->hours();
        $this->postJson('/newsletter/subscribe', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('section_engine_newsletter_subscribers', 0);
    }

    public function test_per_email_and_ip_rate_limits_return_generic_success_without_writes(): void
    {
        config(['section-engine-newsletter.rate_limits.per_email' => 1, 'section-engine-newsletter.rate_limits.per_ip' => 2]);
        $payload = $this->formPayload();
        $generic = $this->postJson('/newsletter/subscribe', $payload)->assertOk()->json();
        $count = SubscriptionEvent::count();
        $this->assertSame($generic, $this->postJson('/newsletter/subscribe', $payload)->assertOk()->json());
        $this->assertDatabaseCount('section_engine_newsletter_events', $count);
        $this->postJson('/newsletter/subscribe', array_replace($payload, ['email' => 'second@example.org']))->assertOk();
        $this->assertSame($generic, $this->postJson('/newsletter/subscribe', array_replace($payload, ['email' => 'third@example.org']))->assertOk()->json());
        $this->assertDatabaseCount('section_engine_newsletter_subscribers', 2);
    }

    public function test_redirect_is_bound_to_the_submitted_form_and_checkbox_can_be_optional(): void
    {
        config(['section-engine-newsletter.require_checkbox' => false]);
        $payload = $this->formPayload();
        unset($payload['consent']);
        $context = json_decode(Crypt::decryptString($payload['context']), true);
        $this->post('/newsletter/subscribe', $payload)->assertRedirect('http://localhost/example#newsletter-'.$context['form_id'])->assertSessionHas('section-engine-newsletter.'.$context['form_id'].'.message');
        $this->assertDatabaseCount('section_engine_newsletter_subscriptions', 1);
    }

    public function test_redirect_feedback_returns_to_only_the_submitted_form_instance(): void
    {
        Route::middleware('web')->get('/example', function (): string {
            $provider = app(LiveTagRegistry::class)->get('newsletter');
            $tag = new LiveTag('newsletter', 'news', [], '{{ newsletter:news }}', true);
            $context = LiveTokenRenderContext::frontend('en', url: 'http://localhost/example');

            return $provider->render($tag, $context).$provider->render($tag, $context);
        });
        NewsletterList::factory()->create(['key' => 'news']);
        $html = $this->get('/example')->assertOk()->getContent();
        preg_match_all('/name="context" value="([^"]+)"/', $html, $matches);
        $encrypted = html_entity_decode($matches[1][1]);
        $context = json_decode(Crypt::decryptString($encrypted), true);
        $this->travel(4)->seconds();
        $this->post('/newsletter/subscribe', ['context' => $encrypted, 'email' => 'a@example.org', 'consent' => '1'])->assertRedirect();
        $html = $this->get('/example')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'Please check your inbox.'));
        $this->assertStringContainsString('id="newsletter-'.$context['form_id'].'"', $html);
    }

    public function test_non_js_validation_errors_are_visible_on_the_original_form(): void
    {
        $payload = $this->formPayload();
        $context = json_decode(Crypt::decryptString($payload['context']), true);
        $this->post('/newsletter/subscribe', array_replace($payload, ['email' => 'bad']))
            ->assertRedirect('http://localhost/example#newsletter-'.$context['form_id'])
            ->assertSessionHas('section-engine-newsletter.'.$context['form_id'].'.errors.email');
    }

    public function test_the_exact_rendered_consent_survives_list_edits(): void
    {
        $payload = $this->formPayload();
        NewsletterList::first()->update(['consent_text' => ['en' => 'Changed since render']]);
        $this->postJson('/newsletter/subscribe', $payload)->assertOk();
        $proof = Subscription::firstOrFail()->consent_text_snapshot;
        $this->assertStringContainsString('Original consent', $proof);
        $this->assertStringNotContainsString('Changed since render', $proof);
    }
}
