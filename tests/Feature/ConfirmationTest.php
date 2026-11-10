<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tok3\SectionEngine\Support\LiveTag;
use Tok3\SectionEngine\Support\LiveTagRegistry;
use Tok3\SectionEngine\Support\LiveTokenRenderContext;
use Tok3\SectionEngineNewsletter\Enums\SubscriptionStatus;
use Tok3\SectionEngineNewsletter\Mail\ConfirmSubscriptionMail;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Models\Subscription;

class ConfirmationTest extends SubscribeTest
{
    /** @return array{Subscription, string} */
    private function confirmation(?string $sourceUrl): array
    {
        $token = str_repeat('a', 64);
        $list = NewsletterList::query()->firstOrCreate(
            ['key' => 'news'],
            ['name' => 'News', 'consent_text' => ['en' => 'I would like newsletter updates.'], 'active' => true],
        );
        $subscription = Subscription::factory()->create([
            'list_id' => $list->id,
            'source_url' => $sourceUrl,
            'confirmation_token_hash' => hash('sha256', $token),
            'confirmation_expires_at' => now()->addHour(),
        ]);

        return [$subscription, '/newsletter/confirm/'.$subscription->id.'/'.$token];
    }

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

    public function test_spanish_signup_uses_spanish_for_notice_mail_and_theme_pages(): void
    {
        Mail::fake();
        config(['section-engine.locales.available' => ['de' => 'Deutsch', 'en' => 'English', 'es' => 'Español']]);
        NewsletterList::factory()->create([
            'key' => 'news',
            'consent_text' => ['es' => 'Quiero recibir novedades por correo electrónico.'],
        ]);
        $formHtml = app(LiveTagRegistry::class)->get('newsletter')->render(
            new LiveTag('newsletter', 'news', [], '{{ newsletter:news }}', true),
            LiveTokenRenderContext::frontend('es', url: 'http://localhost/example'),
        );
        foreach (['lang="es"', 'Correo electrónico', 'Quiero recibir novedades por correo electrónico.', 'Acepto recibir este boletín.', 'Suscribirme'] as $text) {
            $this->assertStringContainsString($text, $formHtml);
        }

        $this->postJson('/newsletter/subscribe', $this->formPayload(locale: 'es'))
            ->assertOk()
            ->assertJsonPath('message', '¡Casi listo! Te hemos enviado un correo electrónico. Confirma tu suscripción con el botón que contiene; solo entonces quedarás suscrito. ¿No has recibido el correo? Revisa también la carpeta de spam.');

        $mail = Mail::sent(ConfirmSubscriptionMail::class)->first();
        $this->assertSame('es', $mail->locale);
        $mailHtml = $mail->render();
        $this->assertStringContainsString('Confirma tu suscripción al boletín', $mail->subject);
        $this->assertStringContainsString('Confirma que deseas recibir este boletín.', $mailHtml);
        $this->assertStringContainsString('Confirmar suscripción', $mailHtml);

        $subscription = Subscription::firstOrFail();
        $confirmationUrl = '/newsletter/confirm/'.$subscription->id.'/'.$mail->token;
        $this->get($confirmationUrl)
            ->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertSee('Confirmar la suscripción al boletín');

        $subscription->update(['source_url' => null]);
        $this->post($confirmationUrl)
            ->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertSee('Tu suscripción al boletín está confirmada.');

        $unsubscribeUrl = URL::signedRoute('section-engine-newsletter.unsubscribe.show', ['subscription' => $subscription]);
        $this->get($unsubscribeUrl)
            ->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertSee('Darse de baja del boletín')
            ->assertSee('Darme de baja');
        $this->post($unsubscribeUrl)
            ->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertSee('Te has dado de baja.');
    }

    public function test_confirmed_subscription_outside_cooldown_gets_new_mail_without_losing_confirmation_proof(): void
    {
        Mail::fake();
        $payload = $this->formPayload();
        $response = $this->postJson('/newsletter/subscribe', $payload)->assertOk()->json();
        $firstMail = Mail::sent(ConfirmSubscriptionMail::class)->first();
        $subscription = Subscription::firstOrFail();

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::31'])
            ->withHeader('User-Agent', 'Initial confirmation browser')
            ->post('/newsletter/confirm/'.$subscription->id.'/'.$firstMail->token)
            ->assertRedirect();
        $subscription->refresh();
        $confirmedAt = $subscription->confirmed_at?->toISOString();
        $confirmedIp = $subscription->confirmed_ip;
        $confirmedUserAgent = $subscription->confirmed_user_agent;
        $firstTokenHash = $subscription->confirmation_token_hash;
        $firstRequestedAt = $subscription->requested_at;

        $this->travel(11)->minutes();
        $this->assertSame($response, $this->postJson('/newsletter/subscribe', $payload)->assertOk()->json());

        Mail::assertSentCount(2);
        $resentMail = Mail::sent(ConfirmSubscriptionMail::class)->last();
        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Confirmed, $subscription->status);
        $this->assertSame($confirmedAt, $subscription->confirmed_at?->toISOString());
        $this->assertSame($confirmedIp, $subscription->confirmed_ip);
        $this->assertSame($confirmedUserAgent, $subscription->confirmed_user_agent);
        $this->assertNotSame($firstTokenHash, $subscription->confirmation_token_hash);
        $this->assertSame(hash('sha256', $resentMail->token), $subscription->confirmation_token_hash);
        $this->assertTrue($subscription->requested_at->isAfter($firstRequestedAt));
        $requested = $subscription->events()->where('type', 'requested')->reorder()->latest('id')->firstOrFail();
        $this->assertTrue($requested->meta['already_confirmed']);
        $this->assertTrue($requested->meta['mail_requested']);

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::32'])
            ->withHeader('User-Agent', 'Repeated confirmation browser')
            ->post('/newsletter/confirm/'.$subscription->id.'/'.$resentMail->token)
            ->assertRedirect();
        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Confirmed, $subscription->status);
        $this->assertSame($confirmedAt, $subscription->confirmed_at?->toISOString());
        $this->assertSame($confirmedIp, $subscription->confirmed_ip);
        $this->assertSame($confirmedUserAgent, $subscription->confirmed_user_agent);
        $this->assertSame(1, $subscription->events()->where('type', 'confirmed')->count());
    }

    public function test_confirmed_subscription_within_cooldown_gets_identical_response_without_mail(): void
    {
        Mail::fake();
        $payload = $this->formPayload();
        $response = $this->postJson('/newsletter/subscribe', $payload)->assertOk()->json();
        $mail = Mail::sent(ConfirmSubscriptionMail::class)->first();
        $subscription = Subscription::firstOrFail();
        $this->post('/newsletter/confirm/'.$subscription->id.'/'.$mail->token)->assertRedirect();
        $tokenHash = $subscription->fresh()->confirmation_token_hash;

        $this->assertSame($response, $this->postJson('/newsletter/subscribe', $payload)->assertOk()->json());

        Mail::assertSentCount(1);
        $this->assertSame(SubscriptionStatus::Confirmed, $subscription->fresh()->status);
        $this->assertSame($tokenHash, $subscription->fresh()->confirmation_token_hash);
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
        $this->get($url)->assertOk()->assertSee('data-test-theme-layout', false)->assertSee('<meta name="robots" content="noindex, nofollow">', false)->assertSee('method="post"', false);
        $this->assertSame(SubscriptionStatus::Pending, Subscription::first()->status);
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::9'])->withHeader('User-Agent', 'Confirm browser')->post($url)
            ->assertRedirect('http://localhost/example#newsletter-news')->assertSessionHas('section-engine-newsletter.confirmed', 'news');
        $subscription = Subscription::first();
        $this->assertSame(SubscriptionStatus::Confirmed, $subscription->status);
        $this->assertSame('2001:db8::9', $subscription->confirmed_ip);
        $this->assertDatabaseHas('section_engine_newsletter_events', ['type' => 'confirmed', 'ip' => '2001:db8::9', 'user_agent' => 'Confirm browser']);
        $this->post($url)->assertRedirect('http://localhost/example#newsletter-news');
        $this->assertSame(1, $subscription->events()->where('type', 'confirmed')->count());
    }

    public function test_wrong_and_expired_tokens_cannot_confirm_and_expiry_offers_resend(): void
    {
        Mail::fake();
        $this->postJson('/newsletter/subscribe', $this->formPayload())->assertOk();
        $mail = Mail::sent(ConfirmSubscriptionMail::class)->first();
        $this->get('/newsletter/confirm/'.$mail->subscriptionId.'/wrong')->assertNotFound()->assertSee('data-test-theme-layout', false);
        $this->post('/newsletter/confirm/'.$mail->subscriptionId.'/wrong')->assertNotFound()->assertSee('data-test-theme-layout', false);
        $this->travel(49)->hours();
        $url = '/newsletter/confirm/'.$mail->subscriptionId.'/'.$mail->token;
        $this->get($url)->assertStatus(410)->assertSee('data-test-theme-layout', false)->assertSee('name="context"', false)->assertSee('expired');
        $this->post($url)->assertStatus(410)->assertSee('data-test-theme-layout', false)->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->assertSame(SubscriptionStatus::Pending, Subscription::first()->status);
    }

    public function test_transport_delivery_writes_confirmation_sent_proof(): void
    {
        config(['mail.default' => 'array', 'mail.mailers.array' => ['transport' => 'array']]);
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::7'])->withHeader('User-Agent', 'Mail browser')->postJson('/newsletter/subscribe', $this->formPayload())->assertOk();
        $this->assertDatabaseHas('section_engine_newsletter_events', ['type' => 'confirmation_sent', 'ip' => '2001:db8::7', 'user_agent' => 'Mail browser']);
    }

    public function test_confirmation_returns_to_same_origin_source_with_list_anchor_and_flash(): void
    {
        [$subscription, $url] = $this->confirmation('http://localhost/articles?edition=morning#old');

        $this->post($url)
            ->assertRedirect('http://localhost/articles?edition=morning#newsletter-news')
            ->assertSessionHas('section-engine-newsletter.confirmed', 'news');
        $this->assertSame(SubscriptionStatus::Confirmed, $subscription->fresh()->status);
    }

    public function test_confirmation_returns_to_a_relative_source_path(): void
    {
        [, $url] = $this->confirmation('/articles?edition=morning');

        $this->post($url)->assertRedirect('/articles?edition=morning#newsletter-news');
    }

    public function test_foreign_or_missing_sources_render_themed_result_pages(): void
    {
        foreach (['https://elsewhere.example/articles', null] as $sourceUrl) {
            [, $url] = $this->confirmation($sourceUrl);
            $this->post($url)->assertOk()->assertSee('data-test-theme-layout', false)->assertSee('Your newsletter subscription is confirmed.');
        }
    }

    public static function unsafeSources(): array
    {
        return [
            'foreign subdomain' => ['http://localhost.attacker.example/phish'],
            'scheme mismatch' => ['https://localhost/phish'],
            'protocol relative' => ['//attacker.example/phish'],
            'userinfo confusion' => ['http://localhost@attacker.example/phish'],
            'non-http scheme' => ['javascript:alert(1)'],
        ];
    }

    #[DataProvider('unsafeSources')]
    public function test_confirmation_never_redirects_to_manipulated_source_urls(string $sourceUrl): void
    {
        [, $url] = $this->confirmation($sourceUrl);

        $this->post($url)->assertOk()->assertSee('data-test-theme-layout', false)->assertHeaderMissing('Location');
    }

    public function test_confirmation_notice_replaces_every_matching_list_form_only(): void
    {
        NewsletterList::factory()->create(['key' => 'news']);
        NewsletterList::factory()->create(['key' => 'other']);
        Route::middleware('web')->get('/articles', function (): string {
            $provider = app(LiveTagRegistry::class)->get('newsletter');
            $context = LiveTokenRenderContext::frontend('en', url: 'http://localhost/articles');

            return $provider->render(new LiveTag('newsletter', 'news', [], '{{ newsletter:news }}', true), $context)
                .$provider->render(new LiveTag('newsletter', 'news', ['variant' => 'compact'], '{{ newsletter:news variant="compact" }}', true), $context)
                .$provider->render(new LiveTag('newsletter', 'other', [], '{{ newsletter:other }}', true), $context);
        });

        $html = $this->withSession(['section-engine-newsletter.confirmed' => 'news'])->get('/articles')->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $notices = $xpath->query('//*[@data-newsletter-confirmed]');
        $forms = $xpath->query('//*[@data-newsletter-form]');
        $this->assertSame(2, $notices->length);
        $this->assertSame(1, $forms->length);
        foreach ($notices as $notice) {
            $this->assertSame('status', $notice->attributes->getNamedItem('role')?->nodeValue);
        }
        $this->assertSame(1, substr_count($html, 'data-newsletter-enhancement'));
        $this->assertStringContainsString('Your newsletter subscription is confirmed.', $html);
    }
}
