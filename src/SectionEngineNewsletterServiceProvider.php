<?php

namespace Tok3\SectionEngineNewsletter;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tok3\SectionEngine\Support\LiveTagRegistry;
use Tok3\SectionEngineNewsletter\LiveTags\NewsletterLiveTagProvider;

class SectionEngineNewsletterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('section-engine-newsletter')->hasConfigFile()->hasViews()->hasTranslations()->hasRoute('web');
    }

    public function packageBooted(): void
    {
        app(LiveTagRegistry::class)->register(NewsletterLiveTagProvider::class);
    }
}
