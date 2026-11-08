<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Tok3\SectionEngine\Models\Page;
use Tok3\SectionEngine\Support\LiveTag;
use Tok3\SectionEngine\Support\LiveTagRegistry;
use Tok3\SectionEngine\Support\LiveTokenRenderContext;
use Tok3\SectionEngine\Support\PageRenderer;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Tests\TestCase;

class FormTest extends TestCase
{
    private function render(string $key = 'news', array $options = [], bool $editor = false): string
    {
        $page = new Page(['id' => 123, 'lang' => 'de']);

        return app(LiveTagRegistry::class)->get('newsletter')->render(
            new LiveTag('newsletter', $key, $options, '{{ newsletter:'.$key.' }}', true),
            $editor ? LiveTokenRenderContext::editor('de', $page, 'https://example.org/news') : LiveTokenRenderContext::frontend('de', $page, 'https://example.org/news'),
        );
    }

    public function test_form_has_encrypted_page_context_and_accessible_controls(): void
    {
        NewsletterList::factory()->create(['key' => 'news']);
        $html = $this->render(options: ['tag' => 'NEWS_2026']);
        $this->assertStringContainsString('<form', $html);
        preg_match('/name="context" value="([^"]+)"/', $html, $m);
        $context = json_decode(Crypt::decryptString(html_entity_decode($m[1])), true);
        $this->assertSame('news', $context['list']);
        $this->assertSame(123, $context['page_id']);
        $this->assertSame('https://example.org/news', $context['url']);
        $this->assertSame('de', $context['locale']);
        $this->assertSame('news_2026', $context['tag']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $context['form_id']);
        foreach (['<label for=', 'aria-describedby=', 'aria-live="polite"', 'name="website"', 'tabindex="-1"', 'method="post"', 'name="_token"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function test_invalid_tag_is_dropped_without_hiding_form(): void
    {
        NewsletterList::factory()->create(['key' => 'news']);
        foreach (['bad/tag', str_repeat('a', 65), '<script>'] as $tag) {
            $html = $this->render(options: ['tag' => $tag]);
            preg_match('/name="context" value="([^"]+)"/', $html, $m);
            $this->assertNull(json_decode(Crypt::decryptString(html_entity_decode($m[1])), true)['tag']);
        }
    }

    public function test_variants_fallback_and_output_escape(): void
    {
        NewsletterList::factory()->create(['key' => 'news', 'consent_text' => ['de' => '<script>alert(1)</script>']]);
        $this->assertStringContainsString('newsletter-form--compact', $this->render(options: ['variant' => 'compact']));
        $html = $this->render(options: ['variant' => '../../evil']);
        $this->assertStringContainsString('newsletter-form--default', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_unknown_and_inactive_lists_are_hidden_or_marked(): void
    {
        $this->assertSame('', $this->render('missing'));
        NewsletterList::factory()->create(['key' => 'news', 'active' => false]);
        $this->assertSame('', $this->render());
        $this->assertStringContainsString('data-section-engine-newsletter-missing="news"', $this->render(editor: true));
        config(['section-engine.design_mode' => true]);
        $this->assertStringContainsString('<mark', $this->render('missing'));
    }

    public function test_preview_disables_all_controls_and_offers_no_script(): void
    {
        NewsletterList::factory()->create(['key' => 'news', 'name' => 'News']);
        $html = $this->render(options: ['tag' => 'topic'], editor: true);
        $this->assertStringContainsString('Newsletter: News, Tag: topic', $html);
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        foreach (['input', 'button'] as $type) {
            foreach ($dom->getElementsByTagName($type) as $control) {
                $this->assertTrue($control->hasAttribute('disabled'));
            }
        }
        $this->assertStringNotContainsString('<script', $html);
        $this->assertDatabaseCount('section_engine_newsletter_subscriptions', 0);
    }

    public function test_insert_options_only_include_active_lists(): void
    {
        NewsletterList::factory()->create(['key' => 'news', 'name' => 'News']);
        NewsletterList::factory()->create(['active' => false]);
        $this->assertSame([['label' => 'News', 'snippet' => '{{ newsletter:news }}']], app(LiveTagRegistry::class)->get('newsletter')->insertOptions());
    }

    public function test_core_page_renderer_passes_page_context(): void
    {
        NewsletterList::factory()->create(['key' => 'news']);
        $page = new Page(['id' => 456, 'lang' => 'de', 'slug' => 'news', 'blocks' => [['type' => 'html', 'data' => ['editorContent' => '{{ newsletter:news }}']]]]);
        $html = (string) app(PageRenderer::class)->render($page);
        $this->assertStringContainsString('name="context"', $html);
        preg_match('/name="context" value="([^"]+)"/', $html, $m);
        $this->assertSame(456, json_decode(Crypt::decryptString(html_entity_decode($m[1])), true)['page_id']);
    }

    public function test_enhancement_is_only_included_once_per_page(): void
    {
        NewsletterList::factory()->create(['key' => 'news']);
        $this->assertSame(1, substr_count($this->render().$this->render(), 'data-newsletter-enhancement'));
    }
}
