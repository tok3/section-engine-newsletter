<?php

namespace Tok3\SectionEngineNewsletter\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tok3\SectionEngineNewsletter\Database\Factories\SubscriptionFactory;
use Tok3\SectionEngineNewsletter\Enums\SubscriptionStatus;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $table = 'section_engine_newsletter_subscriptions';

    protected $guarded = [];

    protected $hidden = ['confirmation_token_hash'];

    protected function casts(): array
    {
        return ['status' => SubscriptionStatus::class, 'requested_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime', 'unsubscribed_at' => 'immutable_datetime', 'confirmation_expires_at' => 'immutable_datetime'];
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
