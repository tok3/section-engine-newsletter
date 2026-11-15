<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tok3\SectionEngine\Support\LiveTag;
use Tok3\SectionEngine\Support\LiveTagRegistry;
use Tok3\SectionEngine\Support\LiveTokenRenderContext;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Tests\TestCase;

/** After signing up the notice names the submitted address and offers to enter another one. */
class SubmittedAddressTest extends TestCase
{
    private const EMAIL = "o'brien&co@example.org";

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('web')->get('/example', fn (): string => app(LiveTagRegistry::class)->get('newsletter')->render(
            new LiveTag('newsletter', 'news', [], '{{ newsletter:news }}', true),
            LiveTokenRenderContext::frontend('de', url: 'http://localhost/example'),
        ));
    }

    public function test_json_names_the_address_and_is_never_cached(): void
    {
        NewsletterList::factory()->create(['key' => 'news']);

        $response = $this->postJson('/newsletter/subscribe', $this->payload())->assertOk()->assertExactJson([
            'status' => 'success',
            'title' => 'Fast geschafft',
            'message' => 'Wir haben dir eine E-Mail an '.self::EMAIL.' geschickt. Erst wenn du die Anmeldung dort bestätigst, bist du eingetragen.',
            'email' => self::EMAIL,
            'change' => 'Andere Adresse eingeben',
        ]);

        $this->assertNoStore($response->headers->get('Cache-Control'));
    }

    public function test_without_javascript_the_address_is_highlighted_escaped_and_the_form_can_return(): void
    {
        NewsletterList::factory()->create(['key' => 'news']);

        $redirect = $this->post('/newsletter/subscribe', $this->payload())->assertRedirect();
        $this->assertStringNotContainsString('brien', (string) $redirect->headers->get('Location'));
        $this->assertStringNotContainsString(rawurlencode(self::EMAIL), (string) $redirect->headers->get('Location'));

        $page = $this->get('/example')->assertOk();
        $this->assertNoStore($page->headers->get('Cache-Control'));
        $html = $page->getContent();
        $this->assertStringContainsString('o&#039;brien&amp;co@example.org', $html);
        $xpath = $this->xpath($html);
        $notice = '//*[@data-newsletter-success]';
        $this->assertSame(self::EMAIL, $xpath->query($notice.'//p[contains(@class, "sen-newsletter__text")]/mark[@class="sen-newsletter__email"]')->item(0)?->textContent);
        $this->assertSame(0, $xpath->query($notice.'//mark//*')->length);
        $this->assertSame('Wir haben dir eine E-Mail an '.self::EMAIL.' geschickt. Erst wenn du die Anmeldung dort bestätigst, bist du eingetragen.', trim($xpath->query($notice.'//p[contains(@class, "sen-newsletter__text")]')->item(0)?->textContent ?? ''));
        $link = $xpath->query($notice.'//a[@data-newsletter-change]')->item(0);
        $this->assertSame('Andere Adresse eingeben', trim($link?->textContent ?? ''));
        $this->assertSame('http://localhost/example#newsletter-news-0', $link?->attributes->getNamedItem('href')?->nodeValue);

        // The flash is gone: the link target shows the form again under the same anchor, without the address.
        $again = $this->get('/example')->assertOk()->getContent();
        $form = $this->xpath($again);
        $this->assertSame(1, $form->query('//*[@id="newsletter-news-0"]//*[@data-newsletter-form]')->length);
        $this->assertStringNotContainsString('brien', $again);
    }

    public function test_list_texts_can_place_the_address_and_old_texts_stay_unchanged(): void
    {
        $list = NewsletterList::factory()->create(['key' => 'news', 'success_text' => ['de' => 'Post an :email ist unterwegs.']]);
        $this->postJson('/newsletter/subscribe', $this->payload())->assertOk()->assertJsonPath('message', 'Post an '.self::EMAIL.' ist unterwegs.');

        $list->update(['success_text' => ['de' => 'Eigener Hinweis ohne Adresse.']]);
        $this->postJson('/newsletter/subscribe', $this->payload('zweite@example.org'))->assertOk()
            ->assertJsonPath('message', 'Eigener Hinweis ohne Adresse.')
            ->assertJsonPath('email', 'zweite@example.org');
    }

    private function assertNoStore(?string $header): void
    {
        $this->assertNotNull($header);
        $this->assertStringContainsString('no-store', $header);
        $this->assertStringContainsString('private', $header);
    }

    /** @return array{email: string, context: string, website: string, consent: string} */
    private function payload(string $email = self::EMAIL): array
    {
        preg_match('/name="context" value="([^"]+)"/', $this->get('/example')->getContent(), $match);
        $this->travel(4)->seconds();

        return ['email' => $email, 'context' => html_entity_decode($match[1]), 'website' => '', 'consent' => '1'];
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new \DOMXPath($dom);
    }
}
