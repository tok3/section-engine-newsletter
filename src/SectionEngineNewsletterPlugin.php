<?php

namespace Tok3\SectionEngineNewsletter;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Tok3\SectionEngineNewsletter\Filament\Resources\NewsletterListResource;

class SectionEngineNewsletterPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'tok3-section-engine-newsletter';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([NewsletterListResource::class]);
    }

    public function boot(Panel $panel): void {}
}
