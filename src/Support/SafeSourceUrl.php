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

        $source = parse_url($sourceUrl);
        if ($source === false || isset($source['user']) || isset($source['pass'])) {
            return null;
        }

        if (! isset($source['scheme']) && ! isset($source['host'])) {
            return str_starts_with($sourceUrl, '//') ? null : $sourceUrl;
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
