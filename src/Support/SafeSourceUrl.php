<?php

namespace Tok3\SectionEngineNewsletter\Support;

class SafeSourceUrl
{
    public function get(?string $sourceUrl): ?string
    {
        $sourceUrl = trim((string) $sourceUrl);
        if ($sourceUrl === '') {
            return null;
        }
        if (preg_match('/[\\x00-\\x1F\\x7F\\\\]/', $sourceUrl) === 1) {
            return null;
        }

        $source = parse_url($sourceUrl);
        if ($source === false || isset($source['user']) || isset($source['pass'])) {
            return null;
        }

        if (! isset($source['scheme']) && ! isset($source['host'])) {
            if (str_starts_with($sourceUrl, '//')) {
                return null;
            }

            return str_starts_with($sourceUrl, '/') ? $sourceUrl : '/'.$sourceUrl;
        }

        $application = parse_url((string) config('app.url'));
        if ($application === false
            || ! isset($source['scheme'], $source['host'], $application['scheme'], $application['host'])
            || strcasecmp($source['scheme'], $application['scheme']) !== 0
            || strcasecmp($source['host'], $application['host']) !== 0) {
            return null;
        }

        return $sourceUrl;
    }
}
