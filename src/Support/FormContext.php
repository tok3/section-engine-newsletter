<?php

namespace Tok3\SectionEngineNewsletter\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
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

    /** @return array{list: string, tag: ?string, page_id: mixed, url: string, locale: string, form_id: string, rendered_at: int, consent_snapshot: string, instance: int} */
    public function create(NewsletterList $list, LiveTokenRenderContext $context, ?string $tag): array
    {
        $instance = request()->attributes->get('newsletter_form_instance', 0);
        request()->attributes->set('newsletter_form_instance', $instance + 1);

        return [
            'instance' => $instance,
            'list' => $list->key,
            'tag' => self::normalizeTag($tag),
            'page_id' => $context->page?->getKey(),
            'url' => $context->url ?? url('/'),
            'locale' => $context->locale,
            'form_id' => bin2hex(random_bytes(8)),
            'rendered_at' => now()->timestamp,
            'consent_snapshot' => self::localized($list->consent_text, $context->locale).(config('section-engine-newsletter.require_checkbox') ? "\n".__('section-engine-newsletter::newsletter.checkbox', [], $context->locale) : ''),
        ];
    }

    /** @return array<string, mixed>|null */
    public function decrypt(mixed $encrypted): ?array
    {
        if (! is_string($encrypted)) {
            return null;
        }
        try {
            $data = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        if (! is_array($data)) {
            return null;
        }
        $validator = Validator::make($data, [
            'list' => ['required', 'string'], 'tag' => ['nullable', 'string', 'regex:/\\A[a-z0-9_-]{1,64}\\z/'],
            'page_id' => ['present', 'nullable', 'integer'], 'url' => ['required', 'url:http,https'], 'locale' => ['required', 'string', 'max:32'],
            'instance' => ['required', 'integer', 'min:0'],
            'form_id' => ['required', 'regex:/\\A[a-f0-9]{16}\\z/'], 'rendered_at' => ['required', 'integer'], 'consent_snapshot' => ['required', 'string'],
        ]);

        return $validator->fails() ? null : $data;
    }

    /** @param array<string, mixed> $context */
    public function encrypt(array $context): string
    {
        return Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR));
    }
}
