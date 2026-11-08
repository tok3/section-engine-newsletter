<?php

namespace Tok3\SectionEngineNewsletter;

use Filament\Contracts\Plugin;
use Filament\Panel;

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

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}
