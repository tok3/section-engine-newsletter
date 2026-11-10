<?php

namespace Tok3\SectionEngineNewsletter\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tok3\SectionEngineNewsletter\Database\Factories\SubscriptionFactory;
use Tok3\SectionEngineNewsletter\Enums\SubscriptionStatus;

/**
 * @property int $id
 * @property int $subscriber_id
 * @property int $list_id
 * @property SubscriptionStatus $status
 * @property string|null $tag
 * @property int|null $source_page_id
 * @property string|null $source_url
 * @property string $source_locale
 * @property string $consent_text_snapshot
 * @property string $consent_hash
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $last_requested_at
 * @property string|null $requested_ip
 * @property string|null $requested_user_agent
 * @property CarbonImmutable|null $confirmed_at
 * @property string|null $confirmed_ip
 * @property string|null $confirmed_user_agent
 * @property CarbonImmutable|null $unsubscribed_at
 * @property string|null $unsubscribed_ip
 * @property string|null $confirmation_token_hash
 * @property CarbonImmutable|null $confirmation_expires_at
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $table = 'section_engine_newsletter_subscriptions';

    protected $guarded = [];

    protected $hidden = ['confirmation_token_hash'];

    protected function casts(): array
    {
        return ['status' => SubscriptionStatus::class, 'requested_at' => 'immutable_datetime', 'last_requested_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime', 'unsubscribed_at' => 'immutable_datetime', 'confirmation_expires_at' => 'immutable_datetime'];
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    /** @return BelongsTo<Subscriber, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /** @return BelongsTo<NewsletterList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(NewsletterList::class, 'list_id')->withTrashed();
    }

    /** @return HasMany<SubscriptionEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class)->orderBy('id');
    }
}
