<?php

namespace Tok3\SectionEngineNewsletter;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tok3\SectionEngine\Support\LiveTagRegistry;
use Tok3\SectionEngineNewsletter\LiveTags\NewsletterLiveTagProvider;

class SectionEngineNewsletterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('section-engine-newsletter')->hasConfigFile()->hasViews()->hasTranslations()->hasRoute('web')->hasMigration('create_section_engine_newsletter_tables');
    }

    public function packageBooted(): void
    {
        RateLimiter::for('section-engine-newsletter', fn (Request $request): array => [
            Limit::perMinute(config('section-engine-newsletter.rate_limits.per_ip'))->by('newsletter:ip:'.$request->ip()),
            Limit::perHour(config('section-engine-newsletter.rate_limits.per_email'))->by('newsletter:email:'.hash('sha256', mb_strtolower(trim((string) $request->input('email'))).'|'.$request->attributes->get('newsletter_list'))),
        ]);
        app(LiveTagRegistry::class)->register(NewsletterLiveTagProvider::class);
    }
}
