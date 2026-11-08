<?php

namespace Tok3\SectionEngineNewsletter\Tests;

use Filament\Support\SupportServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Tok3\SectionEngine\SectionEngineServiceProvider;
use Tok3\SectionEngineNewsletter\SectionEngineNewsletterServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        $providers = [LivewireServiceProvider::class, SupportServiceProvider::class, SectionEngineServiceProvider::class];
        if (class_exists(SectionEngineNewsletterServiceProvider::class)) {
            $providers[] = SectionEngineNewsletterServiceProvider::class;
        }

        return $providers;
    }

    protected function defineDatabaseMigrations(): void
    {
        (require __DIR__.'/../database/migrations/create_section_engine_newsletter_tables.php.stub')->up();
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('app.locale', 'en');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.mailers.array', ['transport' => 'array']);
        $app['config']->set('section-engine.design_mode', false);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
    }
}
