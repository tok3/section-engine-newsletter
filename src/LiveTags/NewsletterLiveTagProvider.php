<?php

namespace Tok3\SectionEngineNewsletter\LiveTags;

use Tok3\SectionEngine\Contracts\LiveTagProvider;
use Tok3\SectionEngine\Support\LiveTag;
use Tok3\SectionEngine\Support\LiveTokenRenderContext;

class NewsletterLiveTagProvider implements LiveTagProvider
{
    public function prefix(): string
    {
        return 'newsletter';
    }

    public function label(): string
    {
        return 'Newsletter';
    }

    public function render(LiveTag $tag, LiveTokenRenderContext $context): string
    {
        return '';
    }

    public function insertOptions(): array
    {
        return [];
    }
}
