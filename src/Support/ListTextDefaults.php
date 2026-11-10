<?php

namespace Tok3\SectionEngineNewsletter\Support;

use Illuminate\Support\Facades\Lang;
use LogicException;

class ListTextDefaults
{
    /** @return array{consent_text: string, success_text: string, confirmation_subject: string, confirmation_intro: string} */
    public static function forLocale(string $locale): array
    {
        if (! Lang::hasForLocale('section-engine-newsletter::newsletter.default_consent', $locale)) {
            $locale = 'en';
        }

        return [
            'consent_text' => self::translation('default_consent', $locale),
            'success_text' => self::translation('success', $locale),
            'confirmation_subject' => self::translation('mail_subject', $locale),
            'confirmation_intro' => self::translation('mail_intro', $locale),
        ];
    }

    private static function translation(string $key, string $locale): string
    {
        $translation = Lang::get('section-engine-newsletter::newsletter.'.$key, locale: $locale);
        if (! is_string($translation)) {
            throw new LogicException('Newsletter list default translation must be a string: '.$key);
        }

        return $translation;
    }
}
