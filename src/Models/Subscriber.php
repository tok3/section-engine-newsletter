<?php

namespace Tok3\SectionEngineNewsletter\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Tok3\SectionEngineNewsletter\Database\Factories\SubscriberFactory;
use Tok3\SectionEngineNewsletter\Events\SubscriberDeleted;

/**
 * @property int $id
 * @property string $email
 * @property string $locale
 */
class Subscriber extends Model
{
    /** @use HasFactory<SubscriberFactory> */
    use HasFactory;

    protected $table = 'section_engine_newsletter_subscribers';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::deleted(fn (self $subscriber) => DB::afterCommit(fn () => SubscriberDeleted::dispatch($subscriber)));
    }

    protected static function newFactory(): SubscriberFactory
    {
        return SubscriberFactory::new();
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtolower(trim($value)));
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
