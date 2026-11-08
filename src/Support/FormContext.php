<?php

namespace Tok3\SectionEngineNewsletter\Support;

use Illuminate\Support\Facades\Crypt;
use Tok3\SectionEngine\Support\LiveTokenRenderContext;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;

class FormContext
{
    public static function normalizeTag(?string $tag): ?string
    {
        $tag = $tag === null ? '' : strtolower($tag);

        return preg_match('/\A[a-z0-9_-]{1,64}\z/', $tag) === 1 ? $tag : null;
    }

    /** @param array<string, string>|null $texts */
    public static function localized(?array $texts, string $locale, string $fallback = ''): string
    {
        return $texts[$locale] ?? $texts[config('section-engine-newsletter.default_locale', 'en')] ?? $fallback;
    }

    /** @return array{list: string, tag: ?string, page_id: mixed, url: string, locale: string, form_id: string, rendered_at: int} */
    public function create(NewsletterList $list, LiveTokenRenderContext $context, ?string $tag): array
    {
        return [
            'list' => $list->key,
            'tag' => self::normalizeTag($tag),
            'page_id' => $context->page?->getKey(),
            'url' => $context->url ?? url('/'),
            'locale' => $context->locale,
            'form_id' => bin2hex(random_bytes(8)),
            'rendered_at' => now()->timestamp,
        ];
    }

    /** @param array<string, mixed> $context */
    public function encrypt(array $context): string
    {
        return Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR));
    }
}
