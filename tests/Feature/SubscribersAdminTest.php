<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tok3\SectionEngineNewsletter\Filament\Resources\Subscribers\Pages\ListSubscribers;
use Tok3\SectionEngineNewsletter\Filament\Resources\Subscribers\Pages\ViewSubscriber;
use Tok3\SectionEngineNewsletter\Filament\Widgets\SignupWidget;
use Tok3\SectionEngineNewsletter\Mail\ConfirmSubscriptionMail;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Models\SubscriptionEvent;
use Tok3\SectionEngineNewsletter\Services\ManageSubscribers;
use Tok3\SectionEngineNewsletter\Support\SourcePage;
use Tok3\SectionEngineNewsletter\Tests\AdminTestCase;
use Tok3\SectionEngineNewsletter\Tests\Fixtures\AlternatePage;

class SubscribersAdminTest extends AdminTestCase
{
    public function test_manual_add_uses_resolved_default_locale(): void
    {
        config([
            'section-engine.locales.default' => 'de',
            'section-engine-newsletter.default_locale' => null,
        ]);

        Livewire::test(ListSubscribers::class)
            ->mountAction('add')
            ->assertActionDataSet(['locale' => 'de']);

        config(['section-engine-newsletter.default_locale' => 'en']);

        Livewire::test(ListSubscribers::class)
            ->mountAction('add')
            ->assertActionDataSet(['locale' => 'en']);
    }

    public function test_manual_add_only_requests_confirmation_and_logs_actor(): void
    {
        Mail::fake();
        $list = NewsletterList::factory()->create();
        Livewire::test(ListSubscribers::class)->callAction('add', ['email' => ' Manual@Example.org ', 'list_id' => $list->id, 'locale' => 'en', 'tag' => 'test'])->assertHasNoActionErrors();
        $subscription = Subscription::firstOrFail();
        $this->assertSame('pending', $subscription->status->value);
        $this->assertSame('manual@example.org', $subscription->subscriber->email);
        Mail::assertSent(ConfirmSubscriptionMail::class);
        $this->assertDatabaseHas('section_engine_newsletter_events', ['type' => 'admin_action']);
        $this->assertSame(1, $subscription->events()->where('type', 'admin_action')->first()->meta['actor_id']);
    }

    public function test_filters_apply_to_the_same_subscription_and_csv_respects_them(): void
    {
        $target = Subscription::factory()->create(['status' => 'confirmed', 'tag' => 'topic', 'source_page_id' => 44, 'requested_at' => '2026-11-01']);
        $other = Subscription::factory()->create(['status' => 'pending']);
        Subscription::factory()->create(['subscriber_id' => $target->subscriber_id, 'status' => 'pending']);
        $filter = ['list_id' => $target->list_id, 'status' => 'confirmed', 'tag' => 'topic', 'source_page_id' => '44', 'from' => '2026-10-31', 'until' => '2026-11-02'];
        $table = Livewire::test(ListSubscribers::class)->filterTable('subscription', $filter)->assertCanSeeTableRecords([$target->subscriber])->assertCanNotSeeTableRecords([$other->subscriber]);
        $table->callAction('csv')->assertFileDownloaded('newsletter-subscribers.csv');
        foreach (['list_id' => 99999, 'status' => 'pending', 'tag' => 'other', 'source_page_id' => 55, 'from' => '2026-11-02', 'until' => '2026-10-30'] as $field => $value) {
            Livewire::test(ListSubscribers::class)->filterTable('subscription', array_replace($filter, [$field => $value]))->assertCanNotSeeTableRecords([$target->subscriber]);
        }
        $response = app(ManageSubscribers::class)->csv($table->instance()->getFilteredTableQuery(), $filter);
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString($target->subscriber->email, $csv);
        $this->assertStringNotContainsString($other->subscriber->email, $csv);
        $this->assertSame(2, count(array_filter(explode("\n", trim($csv)))));
    }

    public function test_manual_unsubscribe_logs_full_admin_proof_and_delete_erases_everything(): void
    {
        $event = SubscriptionEvent::factory()->create();
        $subscription = $event->subscription;
        Livewire::test(ListSubscribers::class)->callTableAction('unsubscribe', $subscription->subscriber, ['subscription_id' => $subscription->id]);
        $this->assertSame('unsubscribed', $subscription->fresh()->status->value);
        $this->assertDatabaseHas('section_engine_newsletter_events', ['type' => 'admin_action']);
        Livewire::test(ListSubscribers::class)->callTableAction('delete', $subscription->subscriber);
        foreach (['subscribers', 'subscriptions', 'events'] as $table) {
            $this->assertDatabaseCount('section_engine_newsletter_'.$table, 0);
        }
    }

    public function test_consent_proof_is_complete_and_access_protected(): void
    {
        $event = SubscriptionEvent::factory()->create(['user_agent' => '<proof-browser>', 'meta' => ['action' => 'original']]);
        $url = route('section-engine-newsletter.proof', $event->subscription->subscriber);
        $this->get($url)->assertOk()->assertSee('2001:db8::1234')->assertSee('&lt;proof-browser&gt;', false)->assertSee('original')->assertSee($event->subscription->consent_text_snapshot);
        Livewire::test(ViewSubscriber::class, ['record' => $event->subscription->subscriber_id])->assertSee('2001:db8::1234');
        auth()->logout();
        $this->get($url)->assertForbidden();
        $this->expectException(HttpException::class);
        app(ManageSubscribers::class)->erase($event->subscription->subscriber);
    }

    public function test_source_page_reference_survives_archive_and_force_deletion(): void
    {
        DB::table('section_engine_pages')->insert(['id' => 42, 'external_id' => 'historical', 'title' => 'Source page', 'slug' => 'source', 'deleted_at' => now()]);
        $subscription = Subscription::factory()->create(['source_page_id' => 42, 'source_url' => 'https://example.org/original']);
        $source = app(SourcePage::class);
        $this->assertStringContainsString('Source page', $source->html($subscription)->toHtml());
        DB::table('section_engine_pages')->where('id', 42)->delete();
        $this->assertSame(42, $subscription->fresh()->source_page_id);
        app()->setLocale('de');
        $this->assertStringContainsString('Seite gelöscht', $source->html($subscription)->toHtml());
        $this->assertStringContainsString('https://example.org/original', $source->html($subscription)->toHtml());
    }

    public function test_source_uses_the_explicitly_configured_model_including_trashed(): void
    {
        Schema::create('alternate_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->softDeletes();
        });
        DB::table('alternate_pages')->insert(['id' => 42, 'title' => 'Configured archived page', 'deleted_at' => now()]);
        config(['section-engine.models.page' => AlternatePage::class]);
        $subscription = Subscription::factory()->create(['source_page_id' => 42]);
        $this->assertStringContainsString('Configured archived page', app(SourcePage::class)->html($subscription)->toHtml());
    }

    public function test_dashboard_only_counts_last_thirty_days_per_list_and_tag(): void
    {
        $subscription = Subscription::factory()->create(['tag' => 'topic']);
        Subscription::factory()->create(['list_id' => $subscription->list_id, 'tag' => 'topic', 'requested_at' => now()->subDays(31)]);
        $counts = app(SignupWidget::class)->counts();
        $this->assertSame(1, $counts['lists'][$subscription->list->name]);
        $this->assertSame(1, $counts['tags']['topic']);
    }
}
