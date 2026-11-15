<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tok3\SectionEngine\Support\LiveTag;
use Tok3\SectionEngine\Support\LiveTagRegistry;
use Tok3\SectionEngine\Support\LiveTokenRenderContext;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Tests\TestCase;

/** Confirmation, result and unsubscribe pages: a small heading, one sentence, a button or link. */
class PageLayoutTest extends TestCase
{
    public function test_confirmation_page_has_a_small_heading_one_sentence_and_a_button(): void
    {
        [$subscription, $url] = $this->pending();

        $xpath = $this->xpath($this->get($url)->assertOk()->assertSee('<title>Anmeldung bestätigen', false)->getContent());

        $this->assertPage($xpath, 'Anmeldung bestätigen', 'Ein Klick noch, dann bekommst du den Newsletter per E-Mail.');
        $this->assertSame('Anmeldung bestätigen', trim($xpath->query('//section[contains(@class, "sen-newsletter--page")]//form//button')->item(0)?->textContent ?? ''));

        $subscription->update(['source_url' => null]);
        $result = $this->xpath($this->post($url)->assertOk()->getContent());
        $this->assertPage($result, 'Anmeldung bestätigt', 'Ab jetzt bekommst du den Newsletter per E-Mail.');
    }

    public function test_invalid_and_expired_links_and_unsubscribe_use_the_same_layout(): void
    {
        [$subscription, $url] = $this->pending();

        $this->assertPage($this->xpath($this->get('/newsletter/confirm/'.$subscription->id.'/'.str_repeat('b', 64))->assertNotFound()->getContent()), 'Link ungültig', 'Dieser Bestätigungslink ist ungültig.');

        $subscription->update(['confirmation_expires_at' => now()->subMinute()]);
        $this->assertPage($this->xpath($this->get($url)->assertStatus(410)->getContent()), 'Bestätigungslink abgelaufen', 'Dieser Bestätigungslink ist abgelaufen. Bitte fordere eine neue E-Mail an.');

        $unsubscribe = URL::signedRoute('section-engine-newsletter.unsubscribe.show', ['subscription' => $subscription]);
        $page = $this->xpath($this->get($unsubscribe)->assertOk()->getContent());
        $this->assertPage($page, 'Vom Newsletter abmelden', 'Ein Klick, dann bekommst du keine Newsletter-E-Mails mehr.');
        $this->assertPage($this->xpath($this->post($unsubscribe)->assertOk()->getContent()), 'Abgemeldet', 'Du bist abgemeldet.');
    }

    public function test_page_heading_is_styled_small_by_default(): void
    {
        $styles = (string) file_get_contents(__DIR__.'/../../resources/views/form/styles.blade.php');

        $this->assertMatchesRegularExpression('/:where\(\.sen-newsletter__title\)\s*\{[^}]*font-size:\s*1\.5rem/', $styles);
    }

    public function test_success_state_has_a_heading_and_one_sentence_with_and_without_javascript(): void
    {
        Route::middleware('web')->get('/example', fn (): string => app(LiveTagRegistry::class)->get('newsletter')->render(
            new LiveTag('newsletter', 'news', [], '{{ newsletter:news }}', true),
            LiveTokenRenderContext::frontend('de', url: 'http://localhost/example'),
        ));
        NewsletterList::factory()->create(['key' => 'news']);
        $payload = $this->payload();

        $this->postJson('/newsletter/subscribe', $payload)->assertOk()->assertExactJson([
            'status' => 'success',
            'title' => 'Fast geschafft',
            'message' => 'Wir haben dir eine E-Mail an new@example.org geschickt. Erst wenn du die Anmeldung dort bestätigst, bist du eingetragen.',
            'email' => 'new@example.org',
            'change' => 'Andere Adresse eingeben',
        ]);

        $this->post('/newsletter/subscribe', $this->payload('other@example.org'))->assertRedirect();
        $xpath = $this->xpath($this->get('/example')->assertOk()->getContent());
        $notice = '//*[@data-newsletter-success]';
        $this->assertSame(0, $xpath->query('//*[@data-newsletter-form]')->length);
        $this->assertSame('Fast geschafft', trim($xpath->query($notice.'//h2[@class="sen-newsletter__title"]')->item(0)?->textContent ?? ''));
        $this->assertSame('Wir haben dir eine E-Mail an other@example.org geschickt. Erst wenn du die Anmeldung dort bestätigst, bist du eingetragen.', trim($xpath->query($notice.'//p[contains(@class, "sen-newsletter__text")]')->item(0)?->textContent ?? ''));
    }

    /** @return array{email: string, context: string, website: string, consent: string} */
    private function payload(string $email = 'new@example.org'): array
    {
        preg_match('/name="context" value="([^"]+)"/', $this->get('/example')->getContent(), $match);
        $this->travel(4)->seconds();

        return ['email' => $email, 'context' => html_entity_decode($match[1]), 'website' => '', 'consent' => '1'];
    }

    /** @return array{Subscription, string} */
    private function pending(): array
    {
        $token = str_repeat('a', 64);
        $list = NewsletterList::factory()->create(['key' => 'news']);
        $subscription = Subscription::factory()->create([
            'list_id' => $list->id,
            'source_locale' => 'de',
            'source_url' => 'http://localhost/example',
            'confirmation_token_hash' => hash('sha256', $token),
            'confirmation_expires_at' => now()->addHour(),
        ]);

        return [$subscription, '/newsletter/confirm/'.$subscription->id.'/'.$token];
    }

    private function assertPage(\DOMXPath $xpath, string $title, string $text): void
    {
        $section = '//section[contains(@class, "sen-newsletter--page")]';
        $headings = $xpath->query($section.'//h1');
        $this->assertSame(1, $headings->length);
        $this->assertSame('sen-newsletter__title', $headings->item(0)?->attributes->getNamedItem('class')?->nodeValue);
        $this->assertSame($title, trim($headings->item(0)?->textContent ?? ''));
        $this->assertSame($text, trim($xpath->query($section.'//p[contains(@class, "sen-newsletter__text")]')->item(0)?->textContent ?? ''));
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new \DOMXPath($dom);
    }
}
