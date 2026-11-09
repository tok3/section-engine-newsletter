<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\URL;
use Tok3\SectionEngineNewsletter\Enums\SubscriptionStatus;
use Tok3\SectionEngineNewsletter\Models\Subscription;

class UnsubscribeTest extends SubscribeTest
{
    public function test_signed_get_does_not_unsubscribe_and_post_unsubscribes_once_with_proof(): void
    {
        $subscription = Subscription::factory()->create(['status' => 'confirmed', 'source_url' => 'http://localhost/articles']);
        $url = URL::signedRoute('section-engine-newsletter.unsubscribe.show', ['subscription' => $subscription]);
        $this->get($url)->assertOk()->assertSee('data-test-theme-layout', false)->assertSee('<meta name="robots" content="noindex, nofollow">', false)->assertSee('method="post"', false);
        $this->assertSame(SubscriptionStatus::Confirmed, $subscription->fresh()->status);
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::77'])->withHeader('User-Agent', 'Unsubscribe browser')->post($url)
            ->assertOk()->assertSee('data-test-theme-layout', false)->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('href="http://localhost/articles"', false);
        $this->assertSame(SubscriptionStatus::Unsubscribed, $subscription->fresh()->status);
        $this->assertDatabaseHas('section_engine_newsletter_events', ['type' => 'unsubscribed', 'ip' => '2001:db8::77', 'user_agent' => 'Unsubscribe browser']);
        $this->post($url)->assertOk()->assertSee('data-test-theme-layout', false);
        $this->assertSame(1, $subscription->events()->count());
    }

    public function test_signature_is_required_on_every_unsubscribe_endpoint(): void
    {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);
        $path = '/newsletter/unsubscribe/'.$subscription->id;
        $this->get($path)->assertForbidden();
        $this->post($path)->assertForbidden();
        $this->post($path.'/one-click', ['List-Unsubscribe' => 'One-Click'])->assertForbidden();
        $this->assertSame(SubscriptionStatus::Confirmed, $subscription->fresh()->status);
    }

    public function test_signed_one_click_post_is_csrf_exempt_and_requires_the_rfc_body(): void
    {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);
        $url = URL::signedRoute('section-engine-newsletter.unsubscribe.one-click', ['subscription' => $subscription]);
        $this->postJson($url, [])->assertUnprocessable();
        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk()->assertSee('data-test-theme-layout', false)->assertSee('You have been unsubscribed.');
        $this->assertSame(SubscriptionStatus::Unsubscribed, $subscription->fresh()->status);
        $route = app('router')->getRoutes()->getByName('section-engine-newsletter.unsubscribe.one-click');
        $middleware = app('router')->gatherRouteMiddleware($route);
        $this->assertNotContains(PreventRequestForgery::class, $middleware);
        $this->assertNotContains(ValidateCsrfToken::class, $middleware);
    }

    public function test_unsubscribe_result_never_links_to_a_foreign_source(): void
    {
        $subscription = Subscription::factory()->create(['status' => 'confirmed', 'source_url' => 'https://attacker.example/phish']);
        $url = URL::signedRoute('section-engine-newsletter.unsubscribe', ['subscription' => $subscription]);

        $this->post($url)->assertOk()->assertSee('data-test-theme-layout', false)->assertDontSee('attacker.example', false);
    }

    public function test_resubscription_is_pending_and_old_confirmation_is_invalid(): void
    {
        $payload = $this->formPayload();
        $this->postJson('/newsletter/subscribe', $payload)->assertOk();
        $subscription = Subscription::firstOrFail();
        $oldHash = $subscription->confirmation_token_hash;
        $this->post(URL::signedRoute('section-engine-newsletter.unsubscribe', ['subscription' => $subscription]))->assertOk();
        $this->postJson('/newsletter/subscribe', $payload)->assertOk();
        $this->assertSame(SubscriptionStatus::Pending, $subscription->fresh()->status);
        $this->assertNotSame($oldHash, $subscription->fresh()->confirmation_token_hash);
        $this->assertDatabaseHas('section_engine_newsletter_events', ['type' => 'resubscribe_requested']);
    }
}
