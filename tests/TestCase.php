<?php

namespace Tok3\SectionEngineNewsletter\Tests;

use Filament\Support\SupportServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Tok3\SectionEngine\SectionEngineServiceProvider;
use Tok3\SectionEngineNewsletter\SectionEngineNewsletterServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['view']->addNamespace('newsletter-tests', __DIR__.'/Fixtures/views');
        config(['section-engine.theme.view' => 'newsletter-tests::theme']);
    }

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
        (require __DIR__.'/../database/migrations/add_success_text_to_section_engine_newsletter_lists_table.php.stub')->up();
        (require __DIR__.'/../database/migrations/add_last_requested_at_to_section_engine_newsletter_subscriptions_table.php.stub')->up();
        Schema::create('section_engine_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('page_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('link_type')->default('page');
            $table->string('slug')->nullable();
            $table->string('type')->default('header');
            $table->string('target')->default('_self');
            $table->string('lang')->default('de');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
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
