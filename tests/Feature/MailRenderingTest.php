<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mime\Email;
use Tok3\SectionEngineNewsletter\Mail\ConfirmSubscriptionMail;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Tests\TestCase;

class MailRenderingTest extends TestCase
{
    /** @return array{string, string, string} */
    private function renderMail(string $locale, ?string $logoUrl = null): array
    {
        config([
            'app.name' => 'Host Site',
            'section-engine-newsletter.mail.brand_name' => 'Example Dispatch',
            'section-engine-newsletter.mail.logo_url' => $logoUrl,
            'section-engine-newsletter.mail.footer' => ['en' => 'English legal footer', 'de' => 'Deutscher Rechtshinweis'],
        ]);
        $token = str_repeat('m', 64);
        $list = NewsletterList::factory()->create([
            'key' => 'weekly-'.(NewsletterList::query()->count() + 1),
            'name' => 'Weekly Review',
            'confirmation_intro' => ['en' => 'English custom introduction.', 'de' => 'Deutscher eigener Einleitungstext.'],
        ]);
        $subscription = Subscription::factory()->create([
            'list_id' => $list->id,
            'source_locale' => $locale,
            'confirmation_token_hash' => hash('sha256', $token),
            'confirmation_expires_at' => now()->addHour(),
        ]);

        $mailable = (new ConfirmSubscriptionMail($subscription->id, $token, '192.0.2.4', 'Mail rendering test'))->locale($locale);
        Mail::mailer()->to('reader@example.org')->send($mailable);

        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $sent = $messages->last()->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $sent);

        return [(string) $sent->getHtmlBody(), (string) $sent->getTextBody(), route('section-engine-newsletter.confirm.show', ['subscription' => $subscription, 'token' => $token])];
    }

    public static function locales(): array
    {
        return [
            'English' => ['en', 'Hello from Example Dispatch', 'Please confirm your subscription to Weekly Review.', 'English custom introduction.', 'Confirm subscription', 'English legal footer'],
            'German' => ['de', 'Hallo von Example Dispatch', 'Bitte bestätige deine Anmeldung für Weekly Review.', 'Deutscher eigener Einleitungstext.', 'Anmeldung bestätigen', 'Deutscher Rechtshinweis'],
        ];
    }

    #[DataProvider('locales')]
    public function test_markdown_mail_renders_localized_html_and_text_parts(string $locale, string $greeting, string $listText, string $intro, string $button, string $footer): void
    {
        [$html, $text, $confirmationUrl] = $this->renderMail($locale);

        foreach ([$greeting, $listText, $intro, $button, $confirmationUrl, $footer] as $expected) {
            $this->assertStringContainsString($expected, $html);
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertNotSame('', trim($text));
    }

    public function test_logo_is_rendered_only_when_configured(): void
    {
        [$withoutLogo] = $this->renderMail('en');
        $this->assertStringNotContainsString('<img', $withoutLogo);

        [$withLogo] = $this->renderMail('en', 'https://brand.example/logo.svg');
        $this->assertStringContainsString('<img src="https://brand.example/logo.svg"', $withLogo);
    }
}
