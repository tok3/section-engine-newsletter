<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Models\Subscriber;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Models\SubscriptionEvent;
use Tok3\SectionEngineNewsletter\Tests\TestCase;

class ModelsTest extends TestCase
{
    public function test_subscriptions_store_a_separate_cooldown_marker(): void
    {
        $this->assertTrue(Schema::hasColumn('section_engine_newsletter_subscriptions', 'last_requested_at'));
        $marker = CarbonImmutable::parse('2026-11-10 12:34:56');
        $subscription = Subscription::factory()->create(['last_requested_at' => $marker]);

        $this->assertSame($marker->toISOString(), $subscription->fresh()->last_requested_at?->toISOString());
    }

    public function test_cooldown_marker_migration_backfills_the_last_request_time(): void
    {
        $migration = require __DIR__.'/../../database/migrations/add_last_requested_at_to_section_engine_newsletter_subscriptions_table.php.stub';
        $migration->down();
        $requestedAt = CarbonImmutable::parse('2026-11-09 08:15:00');
        $subscription = Subscription::factory()->create(['requested_at' => $requestedAt]);

        $migration->up();

        $stored = DB::table('section_engine_newsletter_subscriptions')->where('id', $subscription->id)->value('last_requested_at');
        $this->assertNotNull($stored);
        $this->assertSame($requestedAt->toISOString(), CarbonImmutable::parse((string) $stored)->toISOString());
    }

    public function test_lists_store_optional_localized_success_text(): void
    {
        $this->assertTrue(Schema::hasColumn('section_engine_newsletter_lists', 'success_text'));
        $list = NewsletterList::factory()->create();
        DB::table('section_engine_newsletter_lists')->where('id', $list->id)->update(['success_text' => json_encode(['de' => 'Fast geschafft!'])]);
        $this->assertSame(['de' => 'Fast geschafft!'], $list->fresh()->success_text);
    }

    public function test_subscriber_normalizes_email(): void
    {
        $this->assertTrue(class_exists(Subscriber::class), 'Subscriber model is available');
        $subscriber = Subscriber::create(['email' => '  USER@Example.org ', 'locale' => 'en']);
        $this->assertSame('user@example.org', $subscriber->fresh()->email);
    }

    public function test_subscriber_email_is_unique(): void
    {
        Subscriber::factory()->create(['email' => 'a@example.org']);
        $this->expectException(QueryException::class);
        Subscriber::factory()->create(['email' => ' A@example.org ']);
    }

    public function test_list_key_is_unique(): void
    {
        NewsletterList::factory()->create(['key' => 'news']);
        $this->expectException(QueryException::class);
        NewsletterList::factory()->create(['key' => 'news']);
    }

    public function test_subscription_pair_is_unique(): void
    {
        $subscription = Subscription::factory()->create();
        $this->expectException(QueryException::class);
        Subscription::factory()->create(['subscriber_id' => $subscription->subscriber_id, 'list_id' => $subscription->list_id]);
    }

    public function test_erasure_cascades_to_subscriptions_and_events(): void
    {
        $event = SubscriptionEvent::factory()->create();
        $event->subscription->subscriber->delete();
        $this->assertDatabaseCount('section_engine_newsletter_subscribers', 0);
        $this->assertDatabaseCount('section_engine_newsletter_subscriptions', 0);
        $this->assertDatabaseCount('section_engine_newsletter_events', 0);
    }

    public function test_list_soft_delete_retains_proof_and_force_delete_cascades(): void
    {
        $event = SubscriptionEvent::factory()->create();
        $list = $event->subscription->list;
        $list->delete();
        $this->assertSoftDeleted($list);
        $this->assertDatabaseCount('section_engine_newsletter_events', 1);
        $list->forceDelete();
        $this->assertDatabaseCount('section_engine_newsletter_events', 0);
    }

    public function test_events_cannot_be_updated(): void
    {
        $event = SubscriptionEvent::factory()->create();
        $this->expectException(LogicException::class);
        $event->update(['ip' => '127.0.0.2']);
    }

    public function test_events_cannot_be_deleted(): void
    {
        $event = SubscriptionEvent::factory()->create();
        $this->expectException(LogicException::class);
        $event->delete();
    }
}
