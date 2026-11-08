<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tok3\SectionEngineNewsletter\Filament\Resources\Lists\Pages\CreateNewsletterList;
use Tok3\SectionEngineNewsletter\Filament\Resources\Lists\Pages\EditNewsletterList;
use Tok3\SectionEngineNewsletter\Filament\Resources\Lists\Pages\ListNewsletterLists;
use Tok3\SectionEngineNewsletter\Filament\Resources\NewsletterListResource;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Tests\AdminTestCase;

class ListsAdminTest extends AdminTestCase
{
    public function test_plugin_registers_resource_and_access_requires_core_access(): void
    {
        $this->assertContains(NewsletterListResource::class, Filament::getCurrentPanel()->getResources());
        $this->assertTrue(NewsletterListResource::canViewAny());
        auth()->logout();
        $this->assertFalse(NewsletterListResource::canViewAny());
        $this->get('/admin/newsletter-lists')->assertRedirect('/admin/login');
    }

    public function test_table_counts_each_subscription_status(): void
    {
        $list = NewsletterList::factory()->create();
        foreach (['pending', 'confirmed', 'unsubscribed'] as $status) {
            Subscription::factory()->create(['list_id' => $list->id, 'status' => $status]);
        }
        Livewire::test(ListNewsletterLists::class)->assertSuccessful()->assertCanSeeTableRecords([$list])->assertTableColumnStateSet('confirmed_count', 1, $list)->assertTableColumnStateSet('pending_count', 1, $list)->assertTableColumnStateSet('unsubscribed_count', 1, $list)->assertTableColumnStateSet('snippet', '{{ newsletter:'.$list->key.' }}', $list);
    }

    public function test_create_validates_key_and_default_locale_consent_and_unique_key(): void
    {
        NewsletterList::factory()->create(['key' => 'taken']);
        Livewire::test(CreateNewsletterList::class)->fillForm(['key' => 'bad key', 'name' => 'News', 'consent_text' => ['en' => 'Consent']])->call('create')->assertHasFormErrors(['key']);
        Livewire::test(CreateNewsletterList::class)->fillForm(['key' => 'taken', 'name' => 'News', 'consent_text' => ['en' => 'Consent']])->call('create')->assertHasFormErrors(['key']);
        Livewire::test(CreateNewsletterList::class)->fillForm(['key' => 'valid_key', 'name' => 'News', 'consent_text' => ['en' => 'Consent']])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('section_engine_newsletter_lists', ['key' => 'valid_key']);
    }

    public function test_edit_and_soft_delete_list(): void
    {
        $list = NewsletterList::factory()->create();
        Livewire::test(EditNewsletterList::class, ['record' => $list->id])
            ->fillForm(['name' => 'Updated'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Updated', $list->fresh()->name);
        Livewire::test(ListNewsletterLists::class)->callTableAction('delete', $list);
        $this->assertSoftDeleted($list);
    }

    public function test_embedded_usage_is_obtained_from_documented_finder(): void
    {
        $list = NewsletterList::factory()->create(['key' => 'news']);
        DB::table('section_engine_pages')->insert(['id' => 1, 'external_id' => 'test-page', 'title' => 'A page', 'slug' => 'test', 'lang' => 'en', 'blocks' => json_encode([['type' => 'html', 'data' => ['editorContent' => '{{ newsletter:news }}']]])]);
        $html = NewsletterListResource::usageHtml($list)->toHtml();
        $this->assertStringContainsString('A page', $html);
        $this->assertStringContainsString('/admin/pages/1/edit', $html);
    }
}
