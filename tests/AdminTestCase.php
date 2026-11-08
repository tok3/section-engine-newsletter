<?php

namespace Tok3\SectionEngineNewsletter\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Tok3\SectionEngineNewsletter\Tests\Fixtures\Admin;
use Workbench\App\Providers\NewsletterAdminPanelProvider;

abstract class AdminTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class,
            ActionsServiceProvider::class, FormsServiceProvider::class, InfolistsServiceProvider::class,
            NotificationsServiceProvider::class, SchemasServiceProvider::class, TablesServiceProvider::class,
            WidgetsServiceProvider::class, FilamentServiceProvider::class, NewsletterAdminPanelProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        foreach (['pages', 'live_tokens', 'section_patterns'] as $table) {
            (require __DIR__.'/../vendor/tok3/section-engine/database/migrations/create_section_engine_'.$table.'_table.php.stub')->up();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('newsletter-workbench-admin'));
        $admin = new Admin;
        $admin->forceFill(['id' => 1, 'email' => 'admin@example.org']);
        $this->actingAs($admin);
    }
}
