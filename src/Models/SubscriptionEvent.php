<?php

namespace Tok3\SectionEngineNewsletter\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Tok3\SectionEngineNewsletter\Database\Factories\SubscriptionEventFactory;
use Tok3\SectionEngineNewsletter\Enums\SubscriptionEventType;

/**
 * @property int $id
 * @property SubscriptionEventType $type
 * @property string|null $ip
 * @property string|null $user_agent
 * @property CarbonImmutable $created_at
 */
class SubscriptionEvent extends Model
{
    /** @use HasFactory<SubscriptionEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'section_engine_newsletter_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => SubscriptionEventType::class, 'meta' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->setAttribute('created_at', $event->getAttribute('created_at') ?? now());
        });
        static::updating(fn () => throw new LogicException('Newsletter proof events are append-only.'));
        static::deleting(fn () => throw new LogicException('Newsletter proof events are append-only.'));
    }

    protected static function newFactory(): SubscriptionEventFactory
    {
        return SubscriptionEventFactory::new();
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
